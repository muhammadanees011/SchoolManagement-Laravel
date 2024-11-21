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
use Illuminate\Support\Facades\Log;
use App\Services\MicrosoftGraphService;

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

    protected $graphService;

    /**
     * Create a new command instance.
     *
     * @param MicrosoftGraphService $graphService
     * @return void
     */
    public function __construct(MicrosoftGraphService $graphService)
    {
        parent::__construct();
        $this->graphService = $graphService;
    }

    protected $newSchools = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Cron Job running at ". now());
        $this->syncStudents();
        $this->syncStaff();
        $this->updateStudents();
        $this->updateStaff();
        // $this->archiveUsers();
        $this->SyncCourses();
        $this->sync_student_course();
        $this->sendEmailToETC();
        // $this->archiveCourses();
        // $this->archiveStudentCourse();
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
            }

            //----------STORE NEW STUDENT------------
            $randomPassword = Str::random(10);
            $studentName = $record->firstName . ' ' . $record->surname;
            try{
                $user = new User();
                $user->first_name = $record->firstName;
                $user->last_name = $record->surname;
                $user->email = $record->eMail;
                $user->password = bcrypt($randomPassword);
                $user->role = 'student';
                $user->created_at = now();
                $user->updated_at = now();
                $user->save();
                $userId = $user->id;
    
                $role = \Spatie\Permission\Models\Role::where('name', 'student')->where('guard_name', 'api')->first();
                $user->assignRole($role);
                //-----------SAVE STUDENT----------------        
                $student=new Student();
                $student->user_id = $userId;
                if($school){
                    $student->school_id = $school->id;
                }
                $loginId=$record->loginID ?: null;
                if($loginId){
                    $modifiedLoginID = substr_replace($loginId, 'B', 0, 1); // Replace first character with 'B'
                    $student->student_id = $modifiedLoginID;
                }else{
                    $student->student_id = $loginId;
                }
                $student->upn = $record->UPN ?: null;
                $student->mifare_id  = $record->miFareID ?: null;
                $student->fsm_amount = $record->fsmAmount ? : null;
                $student->purse_type = $record->purseType ?: null;
                $student->site = $record->site ?: null;
                $student->save();

                // //----------GRAPHAPI SEND WELCOME MAIL--------------
                // $data = [
                //     'title'=>'Congratulations you have successfully created your StudentPay account!',
                //     'body'=>$randomPassword,
                //     'user_name'=>$studentName,
                // ];
        
                // $to = [$record->eMail];
                // $subject = 'Welcome Email';
                // $bodyView = 'emails.WelcomeEmail';
                // $status = $this->graphService->sendEmail($to, $subject, $bodyView,null,null, $data);

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
        Log::info('Student Sync completed successfully');
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
            }

           // ----------STORE NEW STAFF------------
            $randomPassword = Str::random(10);
            $studentName = $record->firstName . ' ' . $record->surname;
            try{

                $user = new User();
                $user->first_name = $record->firstName;
                $user->last_name = $record->surname;
                $user->email = $record->eMail;
                $user->password = bcrypt($randomPassword);
                $user->role = 'staff';
                $user->created_at = now();
                $user->updated_at = now();
                $user->save();
                $userId = $user->id;
    
                $role = \Spatie\Permission\Models\Role::where('name', 'staff')->where('guard_name', 'api')->first();
                $user->assignRole($role);

                //-----------SAVE STAFF----------------
                $staff=new Staff();
                $staff->user_id = $userId;
                if($school){
                    $staff->school_id = $school->id;
                }
                $staff->staff_id = $record->loginID ? : null ;
                $staff->upn = $record->UPN  ? : null ;
                $staff->mifare_id = $record->miFareID  ? : null ;
                $staff->site = $record->site  ? : null ;
                $staff->save();

                //----------GRAPHAPI SEND WELCOME MAIL--------------
                // $data = [
                //     'title'=>'Congratulations you have successfully created your StudentPay account!',
                //     'body'=>$randomPassword,
                //     'user_name'=>$studentName,
                // ];
        
                // $to = [$record->eMail];
                // $subject = 'Welcome Email';
                // $bodyView = 'emails.WelcomeEmail';
                // $status = $this->graphService->sendEmail($to, $subject, $bodyView,null,null, $data);


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
        Log::info('Staff Sync completed successfully');
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
        Log::info('Courses Sync completed successfully');
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
                $course=Course::where('CourseCode',$record->CourseCode)->first();
                $course->total_enrollments=$course->total_enrollments + 1;

                $studentCourse=new StudentCourse();
                $studentCourse->StudentID = $record->StudentID;
                $studentCourse->CourseCode = $record->CourseCode;
                $studentCourse->CourseDescription = $course->CourseDescription;
                $studentCourse->save();
                $course->save();

                } catch (\Exception $e) {
            }
    
        }

        Log::info('Student_Course Sync completed successfully');
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

        Log::info('Archive Course completed successfully');
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

        Log::info('Archive Student_Course completed successfully');
    }

    public function updateStudents(){
        //  update student data
        $tables = DB::connection('remote_mysql')->table('ebStudent')->get();
        foreach ($tables as $record) {
            try{
                $school = School::where('title', 'like', '%' . $record->site . '%')->first();
                //-----------UPDATE STUDENT----------------
                $user=User::where('email',$record->eMail)->first();
                $student=Student::where('user_id',$user->id)->first();
                if($student){
                    if($student->site==$school->title){

                    }else{
                        // if site updated update the count
                        $school->students_count=$school->students_count + 1;
                        $school->save();

                        // and also update the count of site we are moving from
                        $school = School::where('title', 'like', '%' . $student->site . '%')->first();
                        $school->students_count=$school->students_count - 1;
                        $school->save();
                    }
                $student->upn = $record->UPN ?: null;
                $student->mifare_id  = $record->miFareID ?: null;
                $student->fsm_amount = $record->fsmAmount;
                $student->purse_type = $record->purseType ?: null;
                $student->site = $record->site ?: null;
                $student->save();
                }
                } catch (\Exception $e) {
            }
        }

        Log::info('Student Update completed successfully');
    }

    public function updateStaff(){
        //  update staff data
        $tables = DB::connection('remote_mysql')->table('ebStaff')->get();
        foreach ($tables as $record) {
            try{
                $school = School::where('title', 'like', '%' . $record->site . '%')->first();
                //-----------UPDATE STUDENT----------------
                $user=User::where('email',$record->eMail)->first();
                $staff=Staff::where('user_id',$user->id)->first();
                if($staff){
                    if($staff->site==$school->title){

                    }else{
                        // if site updated update the count
                        $school->teachers_count=$school->teachers_count + 1;
                        $school->save();

                        // and also update the count of site we are moving from
                        $school = School::where('title', 'like', '%' . $staff->site . '%')->first();
                        $school->teachers_count=$school->teachers_count - 1;
                        $school->save();
                    }
                $staff->staff_id = $record->loginID ?: null;
                $staff->upn = $record->UPN ?: null;
                $staff->mifare_id  = $record->miFareID ?: null;
                $staff->site = $record->site ?: null;
                $staff->save();
                }
                } catch (\Exception $e) {
            }
        }

        Log::info('Staff Update completed successfully');
    }

    // any account that currently exists in our database, 
    // but not in the synchronisation data, should be deemed as no-longer active
    public function archiveUsers()
    {
        $studentTables = DB::connection('remote_mysql')->table('ebStudent')->get();
        $staffTables = DB::connection('remote_mysql')->table('ebStaff')->get();
        $users = DB::table('users')->whereIn('role', ['student', 'staff'])->get();
        $studentEmails = $studentTables->pluck('eMail')->toArray();
        $staffEmails = $staffTables->pluck('eMail')->toArray();
        $tableEmails = array_merge($studentEmails, $staffEmails);        
        $userEmails = $users->pluck('email')->toArray();
        $newEmails = array_diff($userEmails, $tableEmails);
        $newRecords = $users->whereIn('email', $newEmails);
        foreach ($newRecords as $record) {
            $user = User::where('email', $record->email)->first();
            $user->status = 'deleted';
            $user->save();
        }
        Log::info('Archive Users completed successfully');
    }


    public function sendEmailToETC()
    {
        $total_students=Student::count();
        $today_students=Student::whereDate('created_at', Carbon::today())->count();

        $total_staff=Staff::count();
        $today_staff=Staff::whereDate('created_at', Carbon::today())->count();

        // $data['total_students']=$total_students;
        // $data['today_students']=$today_students;
        // $data['total_staff']=$total_staff;
        // $data['today_staff']=$today_staff;
        // $data['new_schools']=$this->newSchools;
        //----------SEND ETC MAIL--------------
        // Mail::to('itsanees011@gmail.com')->send(new ETCEmail($data));
        // Mail::to('abeer.waseem@xepos.co.uk')->send(new ETCEmail($data));
        // Mail::to('amir@xepos.co.uk')->send(new ETCEmail($data));
        // Mail::to('Phillip.Iverson@the-etc.ac.uk')->send(new ETCEmail($data));
        // Mail::to('Nick.Coules@the-etc.ac.uk')->send(new ETCEmail($data));

        $data = [
            'total_students'=>$total_students,
            'today_students'=>$today_students,
            'total_staff'=>$total_staff,
            'today_staff'=>$today_staff,
            'new_schools'=>$this->newSchools,
        ];

        $to = ['itsanees011@gmail.com'];
        $subject = 'StudentPay Data Updated';
        $bodyView = 'emails.ETCEmail';
        $status = $this->graphService->sendEmail($to, $subject, $bodyView,null,null, $data);
    }
}
