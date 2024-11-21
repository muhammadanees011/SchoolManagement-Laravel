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
use Illuminate\Support\Facades\Auth;

class OrganizationAdminsController extends Controller
{
    //-------------GET ALL ORGANIZATION ADMINS--------------
    public function getAllOrganizationAdmins(){
        // $user=Auth::user();
        // if($user->role=='organization_admin'){
        //     $organizationAdmin=OrganizationAdmin::where('user_id',$user->id)->first();
        //     if ($organizationAdmin) {
        //         $organizationId = $organizationAdmin->organization_id;
        //         $admins = User::with('OrganizationAdmin.organization','UserRole.Role')
        //         ->whereHas('OrganizationAdmin', function ($query) use ($organizationId) {
        //             $query->where('organization_id', $organizationId);
        //         })
        //         ->where('role', 'organization_admin')
        //         ->get();
        //     }
        // }else if($user->role=='super_admin'){
            $admins=User::with('OrganizationAdmin.organization','UserRole.Role')
            ->whereNotIn('role', ['student', 'staff', 'parent','super_admin'])
            ->get();
        // }
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
            'password' => 'required|string|min:6|confirmed',
            'status'=>'required|string|max:255',
            'role'=>'required|string|max:255'
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
            $user->password=Hash::make($request['password']);
            // $user->role='organization_admin';
            $user->role=$request->role;
            $user->status = $request->status;
            $user->save();
            $role = \Spatie\Permission\Models\Role::where('name', $request->role)->where('guard_name', 'api')->first();
            $user->assignRole($role);

            $organization_admin=new OrganizationAdmin();
            $organization_admin->user_id = $user->id;
            $organization_admin->organization_id = $request->organization_id;
            $organization_admin->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->save();
            DB::commit();
            //------------SEND WELCOME MAIL------------
            // $studentName = $request->first_name . ' ' . $request->last_name;
            // $mailData = [
            // 'title' => 'Congratulations you have successfully created your StudentPay account!',
            // 'body' => $request['password'],
            // 'user_name'=> $studentName,
            // ];
            // Mail::to($request->email)->send(new WelcomeEmail($mailData));
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
        $admin=User::with('OrganizationAdmin.organization','UserRole.Role')->where('id',$id)->first();
        return response()->json($admin, 200);
    }

    //-------------UPDATE ORGANIZATION ADMIN--------------
    public function updateOrganizationAdmin(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'organization_id'=>'required',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'status'=>'required|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|string',
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
            $user->status = $request->status;
            $user->role=$request->role;
            if($request->password){
                $user->password=Hash::make($request->password);
            }
            $user->save();
            $user->syncRoles([]);
            $role = \Spatie\Permission\Models\Role::where('name', $request->role)->where('guard_name', 'api')->first();
            $user->assignRole($role);
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
