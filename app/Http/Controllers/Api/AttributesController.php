<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\OrganizationAdmin;
use App\Models\Staff;
use App\Models\School;
use App\Models\Attribute;



class AttributesController extends Controller
{
    //-------------GET ATTRIBUTES------------------
    public function getAllAttributes(){
        $attributes=Attribute::get();
        return response()->json($attributes, 200);
    }
    //-------------CREATE ATTRIBUTE----------------
    public function createAttribute(Request $request){
        $validator = Validator::make($request->all(), [
            'organization_id' => 'nullable',
            'name' => 'required|string|max:255',
            'description' => 'nullable',
            'user_role'=>'required'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            if($request->user_role=='super_admin'){
                $organizationID=$request->organization_id;
            }else{
                $user=Auth::user();
                if($user->role=="organization_admin"){
                    $admin=OrganizationAdmin::where('user_id',$user->id)->first();
                    $organizationID=$admin->organization_id;
                }else if($user->role=="staff"){
                    $staff=Staff::where('user_id',$user->id)->first();
                    $school=School::where('organization_id',$staff->school_id)->first();
                    $organizationID=$school->organization_id;
                }
            }
            $attribute=new Attribute();
            $attribute->organization_id = $organizationID;
            $attribute->name = $request->name;
            $attribute->description = $request->description;
            $attribute->save();
            DB::commit();
            $response = ['Successfully created the Attribute'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json('Something went wrong', 200);
            }
        }
    }

    //-------------EDIT ATTRIBUTE----------------
    public function editAttribute($id){
        try {
            $attribute=Attribute::find($id);
            return response()->json($attribute, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json('Something went wrong', 200);
            }
        }
    }

    //-------------UPDATE ATTRIBUTE----------------
    public function updateAttribute(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'organization_id' => 'nullable',
            'name' => 'required|string|max:255',
            'description' => 'nullable',
            'user_role'=>'required'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            if($request->user_role=='super_admin'){
                $organizationID=$request->organization_id;
            }else{
                $user=Auth::user();
                if($user->role=="organization_admin"){
                    $admin=OrganizationAdmin::where('user_id',$user->id)->first();
                    $organizationID=$admin->organization_id;
                }else if($user->role=="staff"){
                    $staff=Staff::where('user_id',$user->id)->first();
                    $school=School::where('organization_id',$staff->school_id)->first();
                    $organizationID=$school->organization_id;
                }
            }
            $attribute=Attribute::find($id);
            $attribute->organization_id = $organizationID;
            $attribute->name = $request->name;
            $attribute->description = $request->description;
            $attribute->save();
            DB::commit();
            $response = ['Successfully updated the Attribute'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json('Something went wrong', 200);
            }
        }
    }

    //-------------DELETE ATTRIBUTE----------------
    public function deleteAttribute($id){
        try {
            $attribute=Attribute::find($id);
            $attribute->delete();
            $response = ['Successfully Deleted the Attribute'];
            return response()->json($user, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json('Something went wrong', 200);
            }
        }
    }

}
