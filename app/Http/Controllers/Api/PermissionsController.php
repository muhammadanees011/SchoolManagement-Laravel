<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\UserPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UserPermissionResource;

class PermissionsController extends Controller
{
    //---------------GET ALL PERMISSIONS----------------
    public function getAllPermissions(){
        $permissions=Permission::get();
        return response()->json($permissions, 200);
    }
    //---------------GET USER's PERMISSIONS----------------
    public function getUserPermissions($id){
        // $permissions=UserPermission::where('user_id',$id)->get();
        $permissions = UserPermissionResource::collection(UserPermission::where('user_id',$id)->get());
        return response()->json($permissions, 200);
    }
    //---------------UPDATE USER PERMISSIONS----------------
    public function updateUserPermissions(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' =>['required',Rule::exists('users', 'id')],
            'permission_id' =>['required',Rule::exists('permissions', 'id')],
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
        $checkPermission=UserPermission::where('user_id',$request->user_id)
        ->where('permission_id',$request->permission_id)->first();
        if($checkPermission){
            $checkPermission->delete();
        }else{
        DB::beginTransaction();
        $permission=new UserPermission();
        $permission->user_id=$request->user_id;
        $permission->permission_id=$request->permission_id;
        $permission->save();
        DB::commit();
        }
        $response=['Successfully updated permissions!'];        
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
}
