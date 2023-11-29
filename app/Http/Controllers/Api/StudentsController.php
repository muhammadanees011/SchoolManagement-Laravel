<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentsController extends Controller
{
    //-------------GET ALL STUDENTS-------------
    public function index(){
        $students=Student::with('user')->get();
        return response()->json($students, 200);
    }
    //-------------CREATE STUDENT--------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'student_id' =>'required|numeric|max:255',
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
            $student->student_id = $request->student_id;;
            $student->school_id = $request->school_id;;
            $student->stage = $request->stage;
            $student->dob = $request->date_of_birth;
            $student->emergency_contact_name = $request->emergency_contact_name;
            $student->emergency_contact_phone = $request->emergency_contact_phone;
            $student->allergies = $request->allergies;
            $student->medical_conditions = $request->medical_conditions;
            $student->enrollment_date = $request->enrollment_date;
            $student->save();
            DB::commit();
            $response = ['Successfully created the Student'];
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
