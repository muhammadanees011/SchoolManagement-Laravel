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
use App\Models\Course;
use App\Models\StudentCourse;
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
        $this->syncStudents();
        $this->syncStaff();
        $this->updateData();
        $this->archiveUsers();
        $this->SyncCourses();
        $this->sync_student_course();
        $this->sendEmailToETC();
        $this->archiveCourses();
        $this->archiveStudentCourse();
    }

    private function syncStudents(){
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

    private function syncStaff(){
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

    private function SyncCourses(){
        $tables = DB::connection('remote_mysql')->table('ePOS_Course')->get();
        $courses = DB::table('courses')->get();
        
        $incommingCourses = $tables->pluck('CourseCode')->toArray();
        $existingCourses = $courses->pluck('CourseCode')->toArray();
        // Identify courses that are in $tables but not in $courses
        $newCourses = array_diff($incommingCourses, $existingCourses);
        // Fetch the records corresponding to the new courses
        $newRecords = $tables->whereIn('CourseCode', $newCourses);
        foreach ($newRecords as $record) {
           //----------STORE NEW COURSE------------
            try{
                $course=new Course();
                $course->CourseCode = $record->CourseCode;
                $course->CourseLevel = $record->CourseLevel;
                $course->CourseDescription = $record->CourseDescription;
                $course->status = $record->Status;
                $course->save();
                } catch (\Exception $e) {
            }
    
        }
    }

    private function sync_student_course(){
        $tables = DB::connection('remote_mysql')->table('ePOS_StudentCourse')->get();
        $student_course = DB::table('student_course')->get();

        // Combine StudentID and CourseCode for incoming and existing courses
        $incommingCourses = $tables->map(function ($item) {
            return $item->StudentID . '_' . $item->CourseCode;
        })->toArray();

        $existingCourses = $student_course->map(function ($item) {
            return $item->StudentID . '_' . $item->CourseCode;
        })->toArray();

        // Identify combined keys (StudentID and CourseCode) that are in $tables but not in $student_course
        $newCourses = array_diff($incommingCourses, $existingCourses);

        // Fetch the records corresponding to the new courses
        $newRecords = $tables->filter(function ($item) use ($newCourses) {
            return in_array($item->StudentID . '_' . $item->CourseCode, $newCourses);
        });

        foreach ($newRecords as $record) {
           //----------STORE NEW COURSE------------
            try{
                $course=new StudentCourse();
                $course->StudentID = $record->StudentID;
                $course->CourseCode = $record->CourseCode;
                $course->save();
                } catch (\Exception $e) {
            }
    
        }
    }

    private function archiveCourses(){
        $tables = DB::connection('remote_mysql')->table('ePOS_Course')->get();
        $courses = DB::table('courses')->get();
        
        // Get the list of CourseCode from both incoming and existing courses
        $incommingCourses = $tables->pluck('CourseCode')->toArray();
        $existingCourses = $courses->pluck('CourseCode')->toArray();
        
        // Identify existing courses that are not in incoming courses (to be archived)
        $oldCourses = array_diff($existingCourses, $incommingCourses);
        
        // Update the status of old courses to 'archived'
        DB::table('courses')
            ->whereIn('CourseCode', $oldCourses)
            ->update(['status' => 'archived']);
        
    }

    private function archiveStudentCourse() {
        $tables = DB::connection('remote_mysql')->table('ePOS_StudentCourse')->get();
        $student_course = DB::table('student_course')->get();
    
        // Combine StudentID and CourseCode for incoming and existing courses
        $incommingCourses = $tables->map(function ($item) {
            return $item->StudentID . '_' . $item->CourseCode;
        })->toArray();
    
        $existingCourses = $student_course->map(function ($item) {
            return $item->StudentID . '_' . $item->CourseCode;
        })->toArray();
    
        // Identify existing courses that are not in incoming courses (to be archived)
        $oldCourses = array_diff($existingCourses, $incommingCourses);
    
        // Update the status of old courses to 'archived' instead of deleting them
        DB::table('student_course')->where(function($query) use ($oldCourses) {
            foreach ($oldCourses as $oldCourse) {
                [$studentID, $courseCode] = explode('_', $oldCourse);
                $query->orWhere([
                    ['StudentID', '=', $studentID],
                    ['CourseCode', '=', $courseCode]
                ]);
            }
        })->update(['status' => 'archived']);
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

    // any account that currently exists in our database, 
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
}
