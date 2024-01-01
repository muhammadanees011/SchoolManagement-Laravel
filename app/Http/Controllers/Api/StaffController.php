<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;
use App\Models\User;
use App\Models\Wallet;
use App\Mail\WelcomeEmail;

class StaffController extends Controller
{
    //-------------GET ALL STAFF-------------
    public function getAllStaff($admin_id=null){
        if($admin_id==null){
            $staff=Staff::with('user','school')->get();
        }else{
            $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $staff = Staff::whereIn('school_id', $schoolIds)->with('user', 'school')->get();
        }
        return response()->json($staff, 200);
    }
    //-------------CREATE STAFF--------------
    public function createStaff(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'staff_id' =>'required|numeric',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:6|confirmed',
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
            $user->role='staff';
            $user->address=$request->address;
            $user->country=$request->country;
            $user->city=$request->city;
            $user->zip=$request->zip;
            $user->state=$request->state;
            $user->status = $request->status;
            $user->save();

            $staff=new Staff();
            $staff->user_id = $user->id;
            $staff->staff_id = $request->staff_id;
            $staff->school_id = $request->school_id;
            $staff->save();

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
            $response['message'] = ['Successfully created the Staff'];
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

    //-------------EDIT STAFF------------------
    public function editStaff($id){
        $school=Staff::with('user')->find($id);
        return response()->json($school, 200);
    }

    //-------------UPDATE STAFF--------------
    public function updateStaff(Request $request,$id){
        $staff=Staff::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'staff_id' =>'required|numeric',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$staff->user->id,
            'phone' => 'required|string|unique:users,phone,'.$staff->user->id,
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
            $staff->school_id = $request->school_id;;
            $staff->staff_id = $request->staff_id;;
            $staff->user->update([
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
            $staff->save();
            DB::commit();
            $response = ['Successfully Updated the Staff'];
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

    //-------------DELETE STAFF----------------
    public function deleteStaff($id){
        try {
            DB::beginTransaction();
            $staff =User::findOrFail($id);
            $staff->delete();
            DB::commit();
            $response = ['Successfully deleted Staff'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['staff not found'];
                return response()->json($response, 404);

            }
        }
    }
}
