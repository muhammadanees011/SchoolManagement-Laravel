<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class RolesController extends Controller
{
    //----------------GET ALL ROLES---------------
    public function getAllRoles(){
        $roles=Role::all();
        return response()->json($roles, 200);
    }
    //----------------CREATE ROLE-----------------
    public function createRole(Request $request){
        $validator = Validator::make($request->all(), [
            'name' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{ 
            Role::firstOrCreate(['guard_name' => 'api','name' => $request->name]);
            $response['message']="Successfully Created The Role";
            return response()->json($response, 200);

            } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['Something went wrong'];
                return response()->json($response, 404);

            }
        }
    }
    //----------------FIND ROLE-----------------
    public function findRole($id){
        $role =Role::find($id);
        return response()->json($role, 200);
    }
    //----------------UPDATE ROLE-----------------
    public function updateRole(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'name' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{ 
            $role=Role::find($id);
            $role->name=$request->name;
            $role->save();
            $response['message']="Successfully Updated The Role";
            return response()->json($response, 200);
            } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['Something went wrong'];
                return response()->json($response, 404);

            }
        }
    }
    //----------------DELETE ROLE-----------------
    public function deleteRole($id){
        $role =Role::find($id);
        $role->delete();
        $response['message']="Successfully Deleted The Role";
        return response()->json($response, 200);
    }
    //----------------GIVE PERMISSION TO ROLE----------------
    public function givePermission(Request $request,$id){
        $role =Role::find($id);
        if($role->hasPermissionTo($request->permission)){
            $role->revokePermissionTo($request->permission);
            $response['message']="Permission Removed";
            return response()->json($response, 200);
        }else{
            $role->givePermissionTo($request->permission);
            $response['message']="Permission Added";
            return response()->json($response, 200);
        }

    }
    //----------------REMOVE PERMISSION TO ROLE----------------
    public function removePermission(Request $request,$id){
        $role =Role::find($id);
        if($role->hasPermissionTo($request->permission)){
            $role->revokePermissionTo($request->permission);
            $response['message']="Permission Removed";
            return response()->json($response, 200);
        }else{
            $response['message']="Permission Not Exist";
            return response()->json($response, 422);
        }
    }
    //----------------PERMISSIONS OF A ROLE----------------
    public function getPermissionsOfaRole($id){
        // $users = Auth::user();
        // $users=$users->getPermissionsViaRoles();
        $role =Role::find($id);
        $permissions=$role->permissions;
        return response()->json($permissions,200);
    }
    //----------------PERMISSIONS OF A USER----------------
    public function getUserRolePermissions($id){
        $user=User::where('id',$id)->first();
        $permissions=$user->getPermissionsViaRoles();
        // $role =Role::find($id);
        // $permissions=$role->permissions;
        return response()->json($permissions,200);
    }
}
