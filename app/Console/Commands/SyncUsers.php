<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use App\Mail\ETCEmail;
use Carbon\Carbon;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Student;
use App\Models\School;
use App\Models\Organization;
use App\Models\Staff;

class SyncUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $newSchools = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Cron Job running at ". now());
        $this->storeNewStudent();
        $this->storeNewStaff();
        $this->updateData();
        $this->archiveUsers();
        $this->sendEmailToETC();
    }

    private function storeNewStudent(){
        // $tables = DB::connection('remote_mysql')->table('ebStudent')->whereDate('created',today())->get();
        $tables = DB::connection('remote_mysql')->table('ebStudent')->get();
        $users = DB::table('users')->get();
        
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();
        // Identify emails that are in $tables but not in $users
        $newEmails = array_diff($tableEmails, $userEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $tables->whereIn('eMail', $newEmails);

        foreach ($newRecords as $record) {

            $school = School::where('title', 'like', '%' . $record->site . '%')->first();
            if($school){
                $school->students_count=$school->students_count + 1;
                $school->save();
            }else{
                if (!in_array($record->site, $this->newSchools)) {
                    $this->newSchools[] = $record->site;
                }
                continue;
                // $organization=Organization::where('name','Education Training Collective')->first();
                // $newSchool=new School();
                // $newSchool->organization_id=$organization->id;
                // $newSchool->title=$record->site;
                // $newSchool->students_count=1;
                // $newSchool->save();
                // $school=$newSchool;
            }

            //----------STORE NEW STUDENT------------
            $randomPassword = Str::random(10);
            $studentName = $record->firstName . ' ' . $record->surname;
            try{
                $userId=DB::table('users')->insertGetId([
                    'first_name' => $record->firstName,
                    'last_name' => $record->surname,
                    'email' => $record->eMail,
                    'password' => bcrypt($randomPassword),
                    'role' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                //-----------SAVE STUDENT----------------        
                $student=new Student();
                $student->user_id = $userId;
                if($school){
                    $student->school_id = $school->id;
                }
                $student->student_id = $record->loginID ?: null;
                $student->upn = $record->UPN ?: null;
                $student->mifare_id  = $record->miFareID ?: null;
                $student->fsm_amount = $record->fsmAmount;
                $student->purse_type = $record->purseType ?: null;
                $student->site = $record->site ?: null;
                $student->save();
                // //----------SEND WELCOME MAIL--------------
                // // $mailData = [
                // //     'title' => 'Congratulations you have successfully created your StudentPay account!',
                // //     'body' => $randomPassword,
                // //     'user_name'=> $studentName,
                // // ];
                // // Mail::to($record->eMail)->send(new WelcomeEmail($mailData));
                // // ----------CREATE STRIPE CUSTOMER------------
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                $customer=$stripe->customers->create([
                'name' => $studentName,
                'email' => $record->eMail,
                ]);
                $user=User::where('id',$userId)->first();
                $user->stripe_id=$customer->id;
                $user->created_at=now();
                $user->updated_at=now();
                $user->save();
                //----------CREATE STUDENT WALLET-------------
                $userWallet=new Wallet();
                $userWallet->user_id=$userId;
                $userWallet->ballance= 0;
                $userWallet->save();
                } catch (\Exception $e) {
            }
    
        }
    }

    private function storeNewStaff(){
        // $tables = DB::connection('remote_mysql')->table('ebStaff')->whereDate('created',today())->get();
        $tables = DB::connection('remote_mysql')->table('ebStaff')->get();
        $users = DB::table('users')->get();
        
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();
        // Identify emails that are in $tables but not in $users
        $newEmails = array_diff($tableEmails, $userEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $tables->whereIn('eMail', $newEmails);

        foreach ($newRecords as $record) {
            // $school=School::where('title',$record->site)->first();
            $school = School::where('title', 'like', '%' . $record->site . '%')->first();
            if($school){
                $school->teachers_count=$school->teachers_count + 1;
                $school->save();
            }else{
                if (!in_array($record->site, $this->newSchools)) {
                    $this->newSchools[] = $record->site;
                }
                continue;
                // $organization=Organization::where('name','Education Training Collective')->first();
                // $newSchool=new School();
                // $newSchool->organization_id=$organization->id;
                // $newSchool->title=$record->site;
                // $newSchool->teachers_count=1;
                // $newSchool->save();
                // $school=$newSchool;
            }

           // ----------STORE NEW STAFF------------
            $randomPassword = Str::random(10);
            $studentName = $record->firstName . ' ' . $record->surname;
            try{
                $userId=DB::table('users')->insertGetId([
                    'first_name' => $record->firstName,
                    'last_name' => $record->surname,
                    'email' => $record->eMail,
                    'password' => bcrypt($randomPassword),
                    'role' => 'staff',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                //-----------SAVE STAFF----------------
                $staff=new Staff();
                $staff->user_id = $userId;
                if($school){
                    $staff->school_id = $school->id;
                }
                $staff->staff_id = $record->loginID;
                $staff->upn = $record->UPN;
                $staff->mifare_id = $record->miFareID;
                $staff->site = $record->site;
                $staff->save();
                //----------SEND WELCOME MAIL--------------
                // $mailData = [
                //     'title' => 'Congratulations you have successfully created your StudentPay account!',
                //     'body' => $randomPassword,
                //     'user_name'=> $studentName,
                // ];
                // Mail::to($record->eMail)->send(new WelcomeEmail($mailData));
                //----------CREATE STRIPE CUSTOMER------------
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                $customer=$stripe->customers->create([
                'name' => $studentName,
                'email' => $record->eMail,
                ]);
                $user=User::where('id',$userId)->first();
                $user->stripe_id=$customer->id;
                $user->created_at=now();
                $user->updated_at=now();
                $user->save();
                //----------CREATE STUDENT WALLET-------------
                $userWallet=new Wallet();
                $userWallet->user_id=$userId;
                $userWallet->ballance= 0;
                $userWallet->save();
                } catch (\Exception $e) {
            }
    
        }
    }

    public function updateData(){
        $tables = DB::connection('remote_mysql')->table('ebStudent')->get();
        foreach ($tables as $record) {
            try{
                //-----------UPDATE STUDENT----------------
                $user=User::where('email',$record->eMail)->first();
                $student=Student::where('user_id',$user->id)->first();
                if($student){
                $student->upn = $record->UPN ?: null;
                $student->mifare_id  = $record->miFareID ?: null;
                $student->fsm_amount = $record->fsmAmount;
                $student->purse_type = $record->purseType ?: null;
                $student->save();
                }
                } catch (\Exception $e) {
            }
        }
    }

    // any account that currently exists in the XEPos data, 
    // but not in the synchronisation data, should be deemed as no-longer active
    public function archiveUsers()
    {
        $tables = DB::connection('remote_mysql')->table('ebStudent')->get();
        $users = DB::table('users')->get();
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();
        // Identify emails that are in $users but not in $tables
        $newEmails = array_diff($userEmails,$tableEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $users->whereIn('email', $newEmails);
        foreach ($newRecords as $record) {
            $user=User::where('email',$record->email)->first();
            $user->status='deleted';
            $user->save();
        }
    }

    public function sendEmailToETC()
    {
        $total_students=Student::count();
        $today_students=Student::whereDate('created_at', Carbon::today())->count();

        $total_staff=Staff::count();
        $today_staff=Staff::whereDate('created_at', Carbon::today())->count();
        $data['total_students']=$total_students;
        $data['today_students']=$today_students;
        $data['total_staff']=$total_staff;
        $data['today_staff']=$today_staff;
        $data['new_schools']=$this->newSchools;
        //----------SEND ETC MAIL--------------
        Mail::to('itsanees011@gmail.com')->send(new ETCEmail($data));
        Mail::to('abeer.waseem@xepos.co.uk')->send(new ETCEmail($data));
        Mail::to('amir@xepos.co.uk')->send(new ETCEmail($data));
        Mail::to('Phillip.Iverson@the-etc.ac.uk')->send(new ETCEmail($data));
        Mail::to('Nick.Coules@the-etc.ac.uk')->send(new ETCEmail($data));
    }

    public function checkIfStudentExist($record){
        $user=User::where('email',$record->eMail)->first();
        if($user){
            $student=Student::where('user_id',$user->id)->first();
            if($student){
                // do nothing
                return false;
            }else{
                $user->delete();
                return true;
            }
        }else{
            return true;
        }
        
    }

    public function checkIfStudentHasSchool($record){
        $user=User::where('email',$record->eMail)->first();
        if($user){
            $student=Student::where('user_id',$user->id)->first();
            if($student && $student->school_id){
                // do nothing
                return false;
            }else if($student && $student->school_id==null){
                $school = School::where('title', 'like', '%' . $record->site . '%')->first();
                $organization=Organization::where('name','Education Training Collective')->first();
                if($school){
                }else{
                    $school=new School();
                    $school->organization_id=$organization->id;
                    $school->title=$record->site;
                    $school->save();
                }
                $student->school_id=$school->id;
                $student->save();
                return true;
            }
        }else{
            return true;
        }
        
    }

    public function removeSchools(){
        School::whereNotNull('id')->delete();
    }
    public function removeAllStudents(){
        User::where('role','student')->delete();
    }
}
