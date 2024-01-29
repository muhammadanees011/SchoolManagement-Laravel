<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PermissionsController extends Controller
{
       //----------------GET ALL PERMISSIONS---------------
       public function getAllPermissions(){
        $permissions=Permission::all();
        return response()->json($permissions, 200);
    }
    //----------------CREATE PERMISSION-----------------
    public function createPermission(Request $request){
        $validator = Validator::make($request->all(), [
            'name' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{ 
            Permission::firstOrCreate(['guard_name' => 'api','name' => $request->name]);
            $response['message']="Successfully Created The Permission";
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
    //------------------FIND PERMISSION--------------
    public function findPermission($id){
        $permission =Permission::find($id);
        return response()->json($permission, 200);
    }
    //----------------UPDATE PERMISSION-----------------
    public function updatePermission(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'name' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{ 
            $permission=Permission::find($id);
            $permission->name=$request->name;
            $permission->save();
            $response['message']="Successfully Updated The Permission";
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
    //------------------DELETE PERMISSION--------------
    public function deletePermission($id){
        $permission =Permission::find($id);
        $permission->delete();
        $response['message']="Successfully Deleted The Permission";
        return response()->json($response, 200);
    }
}
