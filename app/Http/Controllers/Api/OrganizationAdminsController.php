<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Models\Wallet;
use App\Models\OrganizationAdmin;
use App\Models\Role;
use App\Models\OrganizationAdminRole;

class OrganizationAdminsController extends Controller
{
    //-------------GET ALL ORGANIZATION ADMINS--------------
    public function getAllOrganizationAdmins(){
        // $admins=OrganizationAdmin::with('admin','organization')->get();
        $admins=User::with('OrganizationAdmin.organization')->where('role','organization_admin')->get();
        return response()->json($admins, 200);
    }

    //-------------GET ADMINS BY ORGANIZATION--------------
    public function getAdminsByOrganizationId($id){
        $admins=OrganizationAdmin::with('admin')->where('organization_id',$id)->get();
        return response()->json($admins, 200);
    }

    //-------------CREATE ORGANIZATION ADMIN--------------
    public function createOrganizationAdmin(Request $request){
        $validator = Validator::make($request->all(), [
            'organization_id'=>'required',
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
            $user->role='organization_admin';
            $user->address=$request->address;
            $user->country=$request->country;
            $user->city=$request->city;
            $user->zip=$request->zip;
            $user->state=$request->state;
            $user->status = $request->status;
            $user->save();

            $organization_admin=new OrganizationAdmin();
            $organization_admin->user_id = $user->id;
            $organization_admin->organization_id = $request->organization_id;
            $organization_admin->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->ballance=0;
            $userWallet->save();
            //------------SEND WELCOME MAIL------------
            $studentName = $request->first_name . ' ' . $request->last_name;
            $mailData = [
            'title' => 'Congratulations you have successfully created your StudentPay account!',
            'body' => $request['password'],
            'user_name'=> $studentName,
            ];
            Mail::to($request->email)->send(new WelcomeEmail($mailData));
            DB::commit();
            $response['message'] = ['Successfully created the Organization Admin'];
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

    //-------------EDIT ORGANIZATION ADMIN--------------
    public function editOrganizationAdmin($id){
        $admin=User::with('OrganizationAdmin.organization')->where('id',$id)->first();
        return response()->json($admin, 200);
    }

    //-------------UPDATE ORGANIZATION ADMIN--------------
    public function updateOrganizationAdmin(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'organization_id'=>'required',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string',
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
            $user =User::find($id);
            $user->first_name=$request->first_name;
            $user->last_name=$request->last_name;
            $user->email=$request->email;
            $user->phone=$request->phone;
            $user->address=$request->address;
            $user->country=$request->country;
            $user->city=$request->city;
            $user->zip=$request->zip;
            $user->state=$request->state;
            $user->status = $request->status;
            $user->save();
            $admin=OrganizationAdmin::where('user_id',$id)->first();
            $admin->organization_id=$request->organization_id;
            $admin->save();
            DB::commit();
            $response['message'] = ['Successfully updated the Organization Admin'];
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

    //-------------DELETE ORGANIZATION ADMIN----------------
    public function deleteOrganizationAdmin($id){
        try {
            $admin =User::findOrFail($id);
            $admin->delete();
            $response = ['Successfully deleted Organization Admin'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['admin not found'];
                return response()->json($response, 404);

            }
        }
    }
}
