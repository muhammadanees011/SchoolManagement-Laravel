<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Student;
use App\Models\Staff;
use App\Models\Wallet;
use App\Models\User;
use App\Models\School;
use App\Models\OrganizationAdmin;
use App\Models\TransactionHistory;
use App\Models\Parents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Resources\StudentResource;
use App\Http\Resources\StaffResource;
use Illuminate\Support\Facades\Auth;

class StudentsController extends Controller
{
    //-------------GET TOTAL STUDENTS--------------
    public function getTotalStudents(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $student = Student::count();
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $student = Student::whereIn('school_id',$schoolIds)->count();
        }else if($user->role=='staff'){
            $staff=Staff::with('school')->where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$staff->school->organization_id)->pluck('id')->toArray();
            $student = Student::whereIn('school_id',$schoolIds)->count();
        }else if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            $student=Student::where('school_id',$student->school_id)->count();
        }else if($user->role=='parent'){
            $student=1;
        }
        return response()->json($student, 200);
    }
    //-------------GET FREE SCHOOL MEAL AMOUNT--------------
    public function getAmountFSM($student_id){
        $student = Student::where('user_id', $student_id)->first();
        return response()->json($student, 200);
    }
    //-----------TEMP METHODS FRO REMOTE DB--------------
    public function deleteStudentFromRemoteDB(Request $request){
        DB::connection('remote_mysql')->table('ebStudent')
        ->where('ID', $request->student_id)->delete();
    }
    public function storeStudentInRemoteDB(Request $request){
        DB::connection('remote_mysql')->table('ebStudent')->insert([
            'firstName' => $request->firstName,
            'surname' => $request->surname,
            'UPN' =>  $request->UPN,
            'eMail' =>  $request->eMail,
            'site' =>  $request->site,
            'miFareID' =>  $request->miFareID,
            'purseType' =>  $request->purseType,
            'fsmAmount' =>  $request->fsmAmount,
            'created' => now(),
            'modified' => now(),
        ]);
    }//-----------END TEMP METHODS FRO REMOTE DB--------------

    //--------------GET STUDENTS/STAFF DATA-----------
    public function getStudentStaff(Request $request){
        $validator = Validator::make($request->all(), [
            'user_type' => 'nullable|in:student,staff',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->user_type=='student'){
            $response['students']=StudentResource::collection(Student::with('user','school')->get());
        }else if($request->user_type=='staff'){
            $response['staff']=StaffResource::collection(Staff::with('user','school')->get());
        }else if($request->user_type==null){
            $response['students']=StudentResource::collection(Student::with('user','school')->get());
            $response['staff']=StaffResource::collection(Staff::with('user','school')->get());
        }
        return $response;

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
    //--------------GET STUDENTS DATA------------
    public function getStudentsDataFromRemoteDB(){
        // $tables = DB::connection('remote_mysql')
        // ->select('SHOW TABLES');
        $tables = DB::connection('remote_mysql')->table('ebStudent')->get();
        $users = User::get();
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();

        $students=Student::get();
        $userIds = $users->pluck('id')->toArray();
        $studentIds = $students->pluck('user_id')->toArray();
        $newIds = array_diff($studentIds, $userIds);
        return $userIds;
        $newUsers=User::whereIn('id',$newIds)->pluck('email')->toArray();
        $otherUsers=DB::connection('remote_mysql')->table('ebStudent')->whereIn('eMail',$newUsers)->get();

        // Identify emails that are in $tables but not in $users
        $newEmails = array_diff($tableEmails, $userEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $tables->whereIn('eMail', $newEmails);
        return $students;
        foreach ($newRecords as $record) {
            //----------STORE NEW STUDENT------------
            $randomPassword = Str::random(10);
            $studentName = $record->firstName . ' ' . $record->surname;
            // try{
                // $userId=DB::table('users')->insertGetId([
                //     'first_name' => $record->firstName,
                //     'last_name' => $record->surname,
                //     'email' => $record->eMail,
                //     'password' => bcrypt($randomPassword),
                //     'role' => 'student',
                //     'created_at' => now(),
                //     'updated_at' => now(),
                // ]);
                // //-----------SAVE STUDENT----------------
                // // $school=School::where('title',$record->site)->first();
                // $school = School::where('title', 'like', '%' . $record->site . '%')->first();
                // if($school){
                // $school->students_count=$school->students_count + 1;
                // $school->save();
                // }
                // $student=new Student();
                // $student->user_id = $userId;
                // if($school){
                //     $student->school_id = $school->id;
                // }
                // $student->student_id = $record->loginID;
                // $student->upn = $record->UPN;
                // $student->mifare_id = $record->miFareID;
                // $student->fsm_amount = $record->fsmAmount;
                // $student->purse_type = $record->purseType;
                // $student->site = $record->site;
                // $student->save();
                // //------------SEND WELCOME MAIL------------
                // $mailData = [
                //     'title' => 'Congratulations you have successfully created your StudentPay account!',
                //     'body' => $randomPassword,
                //     'user_name'=> $studentName,
                // ];
                // // Mail::to($record->eMail)->send(new WelcomeEmail($mailData));
                // //----------CREATE STRIPE CUSTOMER------------
                // $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                // $customer=$stripe->customers->create([
                // 'name' => $studentName,
                // 'email' => $record->eMail,
                // ]);
                // $user=User::where('id',$userId)->first();
                // $user->stripe_id=$customer->id;
                // $user->created_at=now();
                // $user->updated_at=now();
                // $user->save();
                // //----------CREATE STUDENT WALLET-------------
                // $userWallet=new Wallet();
                // $userWallet->user_id=$userId;
                // $userWallet->save();
                // $res="Email is sent successfully.";
                // return response()->json($res);
                // return response()->json($customer, 200);
            //     } catch (\Exception $e) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => $e->getMessage()
            //     ], 500);
            // }
    
        }
        $res="No New Students Found.";
        return response()->json($res);
    }
    //--------------GET STAFF DATA------------
    public function getStaffDataFromRemoteDB(){
        // $tables = DB::connection('remote_mysql')
        // ->select('SHOW TABLES');

        $tables = DB::connection('remote_mysql')->table('ebStaff')->get();
        return $tables;
        $users = DB::table('users')->get();
        
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();
        // Identify emails that are in $tables but not in $users
        $newEmails = array_diff($tableEmails, $userEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $tables->whereIn('eMail', $newEmails);
        foreach ($newRecords as $record) {
            //----------STORE NEW STAFF------------
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
                if($school){
                    $staff->school_id = $school->id;
                }
                $student->staff_id = $record->loginID;
                $staff->upn = $record->UPN;
                $staff->mifare_id = $record->miFareID;
                $staff->site = $record->site;
                $staff->save();
                //------------SEND WELCOME MAIL------------
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
                //----------CREATE STAFF WALLET-------------
                $userWallet=new Wallet();
                $userWallet->user_id=$userId;
                $userWallet->save();
                // $res="Email is sent successfully.";
                // return response()->json($res);
                // return response()->json($customer, 200);
                } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
    
        }
    }

    public function getStudentBalance($id){
        $wallet=Wallet::where('user_id',$id)->first();
        $ballance=$wallet ? $wallet->ballance:0;
        return response()->json($ballance, 200);
    }
    //-------------GET ALL STUDENTS-------------
    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => ['required',Rule::exists('users', 'id')],
            'role' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->role=='super_admin'){
            $students=Student::with('user.balance','school')->paginate(60);
        }else if($request->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$request->user_id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $students = Student::whereIn('school_id', $schoolIds)->with('user.balance', 'school')->paginate(60);
        }else if($request->role=='staff'){
            $user=Staff::where('user_id',$request->user_id)->first();
            $students = Student::where('school_id', $user->school_id)->with('user.balance', 'school')->paginate(60);
        }else if($request->role=='parent'){
            $studentIds=Parents::where('parent_id',$request->user_id)->pluck('student_id')->toArray();
            $students = Student::where('id', $studentIds)->with('user.balance', 'school')->get();
        }
        return response()->json($students, 200);
    }
    //-------------CREATE STUDENT--------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'student_id' =>'required|string|max:255',
            'attribute_id' =>['nullable',Rule::exists('attributes', 'id')],
            'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'date_of_birth' => 'required|date|before_or_equal:today',
            'enrollment_date' => 'required|date|before_or_equal:today',
            'stage' => 'required|string|max:255',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:255',
            'allergies' => 'required|string|max:255',
            'medical_conditions' => 'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'status'=>'required|string|max:255',
            'fsm'=>'required|boolean'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $user = new User();
            $user->first_name=$request->first_name;
            $user->last_name=$request->last_name;
            $user->email=$request->email;
            $user->phone=$request->phone;
            $user->password=Hash::make($request['password']);
            $user->role='student';
            $user->address=$request->address;
            $user->country=$request->country;
            $user->city=$request->city;
            $user->zip=$request->zip;
            $user->status = $request->status;
            $user->save();

            $student=new Student();
            $student->user_id = $user->id;
            $student->student_id = $request->student_id;
            $student->school_id = $request->school_id;
            $student->attribute_id = $request->attribute_id;
            $student->attributes =$request["attributes"];
            $student->stage = $request->stage;
            $student->dob = $request->date_of_birth;
            $student->emergency_contact_name = $request->emergency_contact_name;
            $student->emergency_contact_phone = $request->emergency_contact_phone;
            $student->allergies = $request->allergies;
            $student->medical_conditions = $request->medical_conditions;
            $student->enrollment_date = $request->enrollment_date;
            $student->fsm_activated= $request->fsm;
            $student->save();

            $school=School::where('id',$request->school_id)->first();
            $school->students_count=$school->students_count + 1;
            $school->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->ballance=0;
            $userWallet->save();
            DB::commit();
            //------------SEND WELCOME MAIL------------
            $studentName = $request->first_name . ' ' . $request->last_name;
            $mailData = [
            'title' => 'Congratulations you have successfully created your StudentPay account!',
            'body' => $request['password'],
            'user_name'=> $studentName,
            ];
            Mail::to($request->email)->send(new WelcomeEmail($mailData));
            $response['message'] = ['Successfully created the Student'];
            $response['user']=$user;
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                return response()->json($exception, 500);
            } else {
            return response()->json($exception, 500);
            }
        }
    }
    //-------------EDIT STUDENT------------------
    public function edit($id){
        $student=Student::with('user')->find($id);
        $wallet=Wallet::where('user_id',$student->user_id)->first();
        $student['balance']=$wallet->ballance ? $wallet->ballance :0;
        return response()->json($student, 200);
    }
    //-------------UPDATE STUDENT--------------
    public function update(Request $request,$id){
        $student=Student::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'student_id' =>'required|string|max:255',
            'attribute_id' =>['nullable',Rule::exists('attributes', 'id')],
            'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$student->user->id,
            'phone' => 'nullable|string|unique:users,phone,'.$student->user->id,
            'date_of_birth' => 'required|date|before_or_equal:today',
            'enrollment_date' => 'required|date|before_or_equal:today',
            'stage' => 'required|string|max:255',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:255',
            'allergies' => 'required|string|max:255',
            'medical_conditions' => 'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'status'=>'required|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'fsm'=>'required|boolean',
            'balance' => 'nullable|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $student->school_id = $request->school_id;
            $student->student_id = $request->student_id;
            $student->attribute_id = $request->attribute_id;
            $student->attributes =$request["attributes"];
            $student->stage = $request->stage;
            $student->dob = $request->date_of_birth;
            $student->emergency_contact_name = $request->emergency_contact_name;
            $student->emergency_contact_phone = $request->emergency_contact_phone;
            $student->allergies = $request->allergies;
            $student->medical_conditions = $request->medical_conditions;
            $student->enrollment_date = $request->enrollment_date;
            $student->fsm_activated = $request->fsm;
            $updateData = [
                'phone' => $request->phone,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'address' => $request->address,
                'country' => $request->country,
                'city' => $request->city,
                'zip' => $request->zip,
                'status' => $request->status,
            ];
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }
            $wallet=Wallet::where('user_id',$student->user_id)->first();
            $wallet->ballance=$request->balance;
            $wallet->save();
            $student->user->update($updateData);
            $student->save();
            DB::commit();
            $response = ['Successfully Updated the Student'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json($exception, 500);
            }
        }
    }
    //-------------DELETE STUDENT----------------
    public function delete($id){
        try {
            DB::beginTransaction();
            $student =Student::findOrFail($id);
            $student->delete();
            DB::commit();
            $response = ['Successfully deleted Student'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['student not found'];
                return response()->json($response, 404);

            }
        }
    }
}
