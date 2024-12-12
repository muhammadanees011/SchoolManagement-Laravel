<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Mail\ETCEmail;
use App\Models\Student;
use App\Models\Staff;
use App\Models\Wallet;
use App\Models\User;
use App\Models\School;
use App\Models\OrganizationAdmin;
use App\Models\TransactionHistory;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Models\Parents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Resources\StudentResource;
use App\Http\Resources\StudentDetailsResource;
use App\Http\Resources\StaffDetailsResource;
use App\Http\Resources\StaffResource;
use Illuminate\Support\Facades\Auth;

class StudentsController extends Controller
{
    //-------------GET TOTAL STUDENTS--------------
    public function getTotalStudents(){
        $user=Auth::user();
        if($user->role!=='staff' && $user->role!=='student'){
            $student = Student::count();
        }
        else if($user->role=='staff'){
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
    //---------------------SEARCH STUDENT POS SYSTEM-----------------
    public function searchStudent(Request $request){
        $validator = Validator::make($request->all(), [
            'searchString' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
    
        $students = Student::with(['user' => function ($query) {
            $query->with('balance');
        }, 'school'])
        ->where(function ($query) use ($request) {
            $query->whereHas('user', function ($subquery) use ($request) {
                $subquery->where('first_name', 'like', '%' . $request->searchString . '%')
                ->orWhere('last_name', 'like', '%' . $request->searchString . '%')
                ->orWhere('mifare_id', 'like', '%' . $request->searchString . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->searchString . '%']);
            });
        })->get();
        if($students->isEmpty()){
            $students = Staff::with(['user' => function ($query) {
                $query->with('balance');
            }, 'school'])
            ->where(function ($query) use ($request) {
                $query->whereHas('user', function ($subquery) use ($request) {
                    $subquery->where('first_name', 'like', '%' . $request->searchString . '%')
                    ->orWhere('last_name', 'like', '%' . $request->searchString . '%')
                    ->orWhere('mifare_id', 'like', '%' . $request->searchString . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->searchString . '%']);
                });
            })->get();
            $response['students']=StaffDetailsResource::collection($students);
        }else{
            $response['students']=StudentDetailsResource::collection($students);
        }
        return response()->json($response, 200);
    }

    //---------------------SEARCH STUDENTSTAFF POS SYSTEM-----------------
    public function searchStudentStaff(Request $request){
        $validator = Validator::make($request->all(), [
            'searchString' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        //-----------SEARCH FROM STUDENTS------------
        $students = Student::with(['user' => function ($query) {
            $query->with('balance');
        }, 'school'])
        ->where(function ($query) use ($request) {
            $query->whereHas('user', function ($subquery) use ($request) {
                $subquery->where('first_name', 'like', '%' . $request->searchString . '%')
                ->orWhere('last_name', 'like', '%' . $request->searchString . '%')
                ->orWhere('mifare_id', 'like', '%' . $request->searchString . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->searchString . '%']);
            });
        })->get();
        $studentsResult=StudentDetailsResource::collection($students);

        //-----------SEARCH FROM STAFF------------
        $staff = Staff::with(['user' => function ($query) {
            $query->with('balance');
        }, 'school'])
        ->where(function ($query) use ($request) {
            $query->whereHas('user', function ($subquery) use ($request) {
                $subquery->where('first_name', 'like', '%' . $request->searchString . '%')
                ->orWhere('last_name', 'like', '%' . $request->searchString . '%')
                ->orWhere('mifare_id', 'like', '%' . $request->searchString . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->searchString . '%']);
            });
        })->get();
        $staffResult=StaffDetailsResource::collection($staff);

        $response['students'] = ($studentsResult)->merge($staffResult);

        return response()->json($response, 200);
    }
    //--------------FILTER STUDENT---------------- 
    public function filterStudent(Request $request){
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'value' => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->type=='MIFare Id'){
            $students=Student::with('user.balance','school')
            ->where('mifare_id', 'like', '%' . $request->value . '%')
            ->whereHas('user', function($query) use ($request) {
                $query->where('status', $request->status);
            })->paginate(60);
        }else if($request->type=='Student Id'){
            $students=Student::with('user.balance','school')
            ->where('student_id', 'like', '%' . $request->value . '%')
            ->whereHas('user', function($query) use ($request) {
                $query->where('status', $request->status);
            })->paginate(60);
        }else if($request->type=='Name'){
            $students = Student::with(['user' => function ($query) {
                $query->with('balance');
            }, 'school'])
            ->where(function ($query) use ($request) {
                $query->whereHas('user', function ($subquery) use ($request) {
                    $subquery->where('first_name', 'like', '%' . $request->value . '%')
                    ->orWhere('last_name', 'like', '%' . $request->value . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
                });
            })
            ->whereHas('user', function($query) use ($request) {
                $query->where('status', $request->status);
            })->paginate(60);
        }else if($request->type=='Email'){
            $students = Student::with(['user' => function ($query) {
                $query->with('balance');
            }, 'school'])
            ->whereHas('user', function ($subquery) use ($request) {
                $subquery->where('email', 'like', '%' . $request->value . '%')
                ->where('status',$request->status);
            })->paginate(60);
        }
        return response()->json($students, 200);
    }
    //--------------GET STUDENT DETAILS POS----------------
    public function getStudentDetails(Request $request){
        $validator = Validator::make($request->all(), [
            'student_id' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $student =Student::where('mifare_id', $request->student_id)->first();
        if($student){
            $response['student']=new StudentDetailsResource($student);
            return response()->json($response, 200);
        }
        else if(!$student){
            $student =Staff::where('mifare_id', $request->student_id)->first(); 
            if($student){
                $response['student']=new StaffDetailsResource($student); 
                return response()->json($response, 200);
            }else if(!$student){
                $response['message']=["user not found"];
                return response()->json($response, 422);
            }
        }
    }

    //--------------GET STUDENTSTAFF DETAILS POS----------------
    public function getStudentStaffDetails(Request $request){
        $validator = Validator::make($request->all(), [
            'mifare_id' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $student =Student::where('mifare_id', $request->mifare_id)->first();
        if($student){
            $response['student']=new StudentDetailsResource($student);
            return response()->json($response, 200);
        }
        else if(!$student){
            $student =Staff::where('mifare_id', $request->mifare_id)->first(); 
            if($student){
                $response['student']=new StaffDetailsResource($student); 
                return response()->json($response, 200);
            }else if(!$student){
                $response['message']=["user not found"];
                return response()->json($response, 422);
            }
        }
    }

    //--------------GET STUDENTS DATA------------
    public function getStudentsDataFromRemoteDB(){
        $tables = DB::connection('remote_mysql')->table('ebStudent')->get();
        return response()->json($tables);

        $res="No New Students Found.";
        return response()->json($res);
    }
    //--------------GET STAFF DATA------------
    public function getStaffDataFromRemoteDB(){
        // $tables = DB::connection('remote_mysql')
        // ->select('SHOW TABLES');

        $tables = DB::connection('remote_mysql')->table('ebStaff')->get();
        $users = DB::table('users')->get();
        
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();
        // Identify emails that are in $tables but not in $users
        $newEmails = array_diff($tableEmails, $userEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $tables->whereIn('eMail', $newEmails);
        return $newRecords;
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
            'entries_per_page'=>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $user=Auth::user();

        if($user->role!=='student' && $user->role!=='staff' && $user->role!=='parent'){
            $students=Student::with('user.balance','school')
            ->whereHas('user', function($query) {
                $query->where('status', 'active');
            })->orderBy('created_at', 'desc')->paginate($request->entries_per_page);
        }else if($user->role=='staff'){
            $user=Staff::where('user_id',$user->id)->first();
            $students = Student::where('school_id', $user->school_id)->with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'active');
            })->orderBy('created_at', 'desc')->paginate($request->entries_per_page);
        }else if($user->role=='parent'){
            $studentIds=Parents::where('parent_id',$user->id)->pluck('student_id')->toArray();
            $students = Student::where('id', $studentIds)->with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'active');
            })->orderBy('created_at', 'desc')->get();
        }
        return response()->json($students, 200);
    }
    //-------------GET ALL STUDENTS-------------
    public function archivedStudents(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => ['required',Rule::exists('users', 'id')],
            'role' =>'required',
            'entries_per_page'=>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $user=Auth::user();
        if($user->role!=='student' && $user->role!=='staff' && $user->role!=='parent'){
            $students = Student::with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'deleted');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->entries_per_page);
        }else if($request->role=='staff'){
            $user=Staff::where('user_id',$user->id)->first();
            $students = Student::where('school_id', $user->school_id)->with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'deleted');
            })->orderBy('created_at', 'desc')->paginate($request->entries_per_page);
        }else if($request->role=='parent'){
            $studentIds=Parents::where('parent_id',$user->id)->pluck('student_id')->toArray();
            $students = Student::where('id', $studentIds)->with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'deleted');
            })->orderBy('created_at', 'desc')->paginate($request->entries_per_page);
        }
        return response()->json($students, 200);
    }
    //-------------BULK DELETE STUDENTS--------
    public function bulkDeleteStudents(Request $request)
    {
        // $ids = $request->all();
        // User::whereIn('id', $ids)->delete();
        $ids = $request->all();
        foreach ($ids as $id) {
            $user = User::find($id);
            if ($user) {
                $student=Student::where('user_id',$id)->first();
                $school = School::where('id', $student->school_id)->first();
                
                if ($school) {
                    $school->teachers_count = $school->teachers_count - 1;
                    $school->save();
                }
                $user->delete();
            }
        }
        return response()->json(['message' => 'Students deleted successfully'], 200);
    }
    public function bulkRestoreStudents(Request $request)
    {
        $ids = $request->all();
        foreach ($ids as $record) {
            $user=User::where('id',$record)->first();
            $user->status='active';
            $user->save();
        }
        return response()->json(['message' => 'Students restored successfully'], 200);
    }
    //-------------CREATE STUDENT--------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'student_id' =>'required|string|max:255',
            'mifare_id' =>'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            // 'password' => 'nullable|string|min:6|confirmed',
            // 'date_of_birth' => 'nullable|date|before_or_equal:today',
            'status'=>'required|string|max:255',
            'fsm'=>'required|boolean',
            'balance'=>'nullable|numeric'
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
            $randomPassword = Str::random(10);
            $user->password=Hash::make($randomPassword);
            $user->role='student';
            $user->status = $request->status;
            $user->save();

            $role = \Spatie\Permission\Models\Role::where('name','student')->where('guard_name', 'api')->first();
            $user->assignRole($role);

            $student=new Student();
            $student->user_id = $user->id;
            $student->student_id = $request->student_id;
            $student->mifare_id = $request->mifare_id;
            $student->school_id = $request->school_id;
            // $student->dob = $request->date_of_birth;
            $student->fsm_amount= $request->fsm ? 0:null;
            $student->save();

            $school=School::where('id',$request->school_id)->first();
            $school->students_count=$school->students_count + 1;
            $school->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->ballance=$request->balance ? $request->balance: 0;
            $userWallet->save();
            DB::commit();

            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $customer=$stripe->customers->create([
            'name' => $user->first_name."".$user->last_name,
            'email' => $user->email,
            ]);
            $user=User::where('id',$user->id)->first();
            $user->stripe_id=$customer->id;
            $user->created_at=now();
            $user->updated_at=now();
            $user->save();

            //------------SEND WELCOME MAIL------------
            $studentName = $request->first_name . ' ' . $request->last_name;
            $mailData = [
            'title' => 'Congratulations you have successfully created your StudentPay account!',
            'body' => $randomPassword,
            'user_name'=> $studentName,
            ];
            Mail::to($request->email)->send(new WelcomeEmail($mailData));
            // Check if email sending failed
            if (count(Mail::failures()) > 0) {
                return response()->json(['errors' => ['Failed to send Email']], 500);
            }
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
            'mifare_id' =>'required|string|max:255',
            'student_id' =>'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$student->user->id,
            // 'date_of_birth' => 'nullable|date|before_or_equal:today',
            'status'=>'required|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'fsm'=>'required|boolean',
            // 'balance' => 'nullable|numeric',
            'add_amount' => 'nullable|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $student->school_id = $request->school_id;
            $student->student_id = $request->student_id;
            $student->mifare_id = $request->mifare_id;
            // $student->dob = $request->date_of_birth;
            $student->fsm_activated = $request->fsm;
            $updateData = [
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'status' => $request->status,
            ];
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }
            $wallet=Wallet::where('user_id',$student->user_id)->first();
            $wallet->ballance=($wallet->ballance ? $wallet->ballance:0) + ($request->add_amount ? $request->add_amount:0);
            $wallet->save();
            $student->user->update($updateData);
            $student->save();

            //--------Save Transaction History-----------
            if($request->add_amount){
                $history=new TransactionHistory();
                $history->user_id=$student->user_id;
                $history->type='admin_top_up';
                $history->amount=$request->add_amount;
                $history->save();
            }
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
            $student =User::find($id);
            $student->status ='deleted';
            // $student->delete();
            $student->save();
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
