<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Student;
use App\Models\School;
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Cron Job running at ". now());
        $this->storeNewStudent();
        $this->storeNewStaff();
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
                $school=School::where('title',$record->site)->first();
                $school->students_count=$school->students_count + 1;
                $school->save();
                $student=new Student();
                $student->user_id = $userId;
                $student->school_id = $school->id;
                $student->student_id = $record->loginID;
                $student->upn = $record->UPN;
                $student->mifare_id = $record->miFareID;
                $student->fsm_amount = $record->fsmAmount;
                $student->purse_type = $record->purseType;
                $student->site = $record->site;
                $student->save();
                //----------SEND WELCOME MAIL--------------
                $mailData = [
                    'title' => 'Congratulations you have successfully created your StudentPay account!',
                    'body' => $randomPassword,
                    'user_name'=> $studentName,
                ];
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
            //----------STORE NEW STUDENT------------
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
                $school=School::where('title',$record->site)->first();
                $school->staff_count=$school->staff_count + 1;
                $school->save();
                $staff=new Staff();
                $staff->user_id = $userId;
                $staff->school_id = $school->id;
                $student->staff_id = $record->loginID;
                $staff->upn = $record->UPN;
                $staff->mifare_id = $record->miFareID;
                $staff->site = $record->site;
                $staff->save();
                //----------SEND WELCOME MAIL--------------
                $mailData = [
                    'title' => 'Congratulations you have successfully created your StudentPay account!',
                    'body' => $randomPassword,
                    'user_name'=> $studentName,
                ];
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
                $userWallet->save();
                } catch (\Exception $e) {
            }
    
        }
    }
}
