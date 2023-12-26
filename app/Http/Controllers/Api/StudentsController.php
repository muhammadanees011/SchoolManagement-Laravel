<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Student;
use App\Models\Wallet;
use App\Models\User;
use App\Models\School;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class StudentsController extends Controller
{
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
    }
    public function getStudentsDataFromRemoteDB(){
        // $tables = DB::connection('remote_mysql')
        // ->select('SHOW TABLES');

        $tables = DB::connection('remote_mysql')->table('ebStudent')->whereDate('created',today())->get();
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
                $student=new Student();
                $student->user_id = $userId;
                $student->school_id = $school->id;
                $student->upn = $record->UPN;
                $student->mifare_id = $record->miFareID;
                $student->fsm_amount = $record->fsmAmount;
                $student->purse_type = $record->purseType;
                $student->site = $record->site;
                $student->save();
                //------------SEND WELCOME MAIL------------
                $mailData = [
                    'title' => 'Congratulations you have successfully created your StudentPay account!',
                    'body' => $randomPassword,
                    'user_name'=> $studentName,
                ];
                Mail::to($record->eMail)->send(new WelcomeEmail($mailData));
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
                $userWallet->ballance=0;
                $userWallet->save();
                $res="Email is sent successfully.";
                return response()->json($res);
                // return response()->json($customer, 200);
                } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
    
        }


        // try {
        //     $records = DB::connection('remote_mysql');
        //         // ->select(DB::raw('CALL your_stored_procedure_name()'));
        //     // If the connection and procedure call were successful, you can proceed with handling $records.
        // } catch (\Exception $e) {
        //     // An exception occurred, indicating a connection or procedure call failure.
        //     // You can log the error, print a message, or handle it based on your requirements.
        //     echo "Error: " . $e->getMessage();
        // }
        return response()->json($tables);
    }
    public function getStudentBalance($id){
        $wallet=Wallet::where('user_id',$id)->first();
        $ballance=$wallet->ballance;
        return response()->json($ballance, 200);
    }
    //-------------GET ALL STUDENTS-------------
    public function index(){
        $students=Student::with('user')->get();
        return response()->json($students, 200);
    }
    //-------------CREATE STUDENT--------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'student_id' =>'required|numeric',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
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
            'state'=>'required|string|max:255',
            'status'=>'required|string|max:255'
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
            $user->state=$request->state;
            $user->status = $request->status;
            $user->save();

            $student=new Student();
            $student->user_id = $user->id;
            $student->student_id = $request->student_id;
            $student->school_id = $request->school_id;
            $student->stage = $request->stage;
            $student->dob = $request->date_of_birth;
            $student->emergency_contact_name = $request->emergency_contact_name;
            $student->emergency_contact_phone = $request->emergency_contact_phone;
            $student->allergies = $request->allergies;
            $student->medical_conditions = $request->medical_conditions;
            $student->enrollment_date = $request->enrollment_date;
            $student->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->ballance=0;
            $userWallet->save();
            DB::commit();
            $response['message'] = ['Successfully created the Student'];
            $response['user']=$user;
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
    //-------------EDIT STUDENT------------------
    public function edit($id){
        $school=Student::with('user')->find($id);
        return response()->json($school, 200);
    }
    //-------------UPDATE STUDENT--------------
    public function update(Request $request,$id){
        $student=Student::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'student_id' =>'required|numeric',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$student->user->id,
            'phone' => 'required|string|unique:users,phone,'.$student->user->id,
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
            'state'=>'required|string|max:255',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $student->student_id = $request->student_id;;
            $student->stage = $request->stage;
            $student->dob = $request->date_of_birth;
            $student->emergency_contact_name = $request->emergency_contact_name;
            $student->emergency_contact_phone = $request->emergency_contact_phone;
            $student->allergies = $request->allergies;
            $student->medical_conditions = $request->medical_conditions;
            $student->enrollment_date = $request->enrollment_date;
            $student->user->update([
                'phone' => $request->phone,
                'email' => $request->email,
                'first_name'=>$request->first_name,
                'last_name'=>$request->last_name,
                'address'=>$request->address,
                'country'=>$request->country,
                'city'=>$request->city,
                'zip'=>$request->zip,
                'state'=>$request->state,
                'status'=>$request->status,
            ]);
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
