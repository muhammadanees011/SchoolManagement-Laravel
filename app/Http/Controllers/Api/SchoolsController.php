<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\School;
use App\Models\User;
use App\Models\SchoolShop;
use App\Models\Staff;
use App\Models\Student;
use App\Models\OrganizationAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Resources\Organization\list;

class SchoolsController extends Controller
{
    //-------------GET TOTAL SCHOOLS------------
    public function totalSchools(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $schools=School::count();
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $schools=School::where('organization_id',$admin->organization_id)->count();
        }else if($user->role=='staff'){
            $staff=Staff::with('school')->where('user_id',$user->id)->first();
            $schools=School::where('organization_id',$staff->school->organization_id)->count();
        }else if($user->role=='student'){
            $student=Student::with('school')->where('user_id',$user->id)->first();
            $schools=School::where('organization_id',$student->school->organization_id)->count();
        }else if($user->role=='parent'){
            $schools=1;
        }
        return response()->json($schools, 200);
    }
    //-------------GET ALL SCHOOLS------------
    public function index($admin_id=null){
        if($admin_id==null){
            $schools=School::with('user','organization')->get();
        }else{
            $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
            $schools=School::where('organization_id',$admin->organization_id)->with('user','organization')->get();
        }
        return response()->json($schools, 200);
    }
    //-------------CREATE SCHOOL--------------
    public function create(Request $request,$admin_id=null){
        $validator = Validator::make($request->all(), [
            'organization_id' => 'nullable',
            'title' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|unique:users',
            'website'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'tagline'=>'required|string|max:255',
            'teachers_count'=>'required|numeric',
            'students_count'=>'required|numeric',
            'stages'=>'required|string|max:255',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            if($admin_id==null){
                $organizationID=$request->organization_id;
            }else{
                $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
                $organizationID=$admin->organization_id;
            }
            $school=new School();
            $school->organization_id = $organizationID;
            $school->title = $request->title;
            $school->email = $request->email;
            $school->phone = $request->phone;
            $school->tagline = $request->tagline;
            $school->address = $request->address;
            $school->country = $request->country;
            $school->website = $request->website;
            $school->city = $request->city;
            $school->zip = $request->zip;
            $school->stages = $request->stages;
            $school->status = $request->status;
            $school->teachers_count = $request->teachers_count;
            $school->students_count = $request->students_count;
            $school->save();
            DB::commit();
            $response = ['Successfully created the School'];
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
    //-------------EDIT SCHOOL------------
    public function edit($id){
        $school=School::with('user')->find($id);
        return response()->json($school, 200);
    }
    //-------------UPDATE SCHOOL--------------
    public function update(Request $request,$id,$admin_id=null){
        $school = School::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'organization_id'=>'nullable',
            'title' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string',
            'website'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'tagline'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'teachers_count'=>'required|numeric',
            'students_count'=>'required|numeric',
            'stages'=>'required|string|max:255',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            if($admin_id==null){
                $organizationID=$request->organization_id;
                $school->organization_id = $organizationID;
            }
            $school->organization_id = $request->organization_id;
            $school->title = $request->title;
            $school->email = $request->email;
            $school->phone = $request->phone;
            $school->tagline = $request->tagline;
            $school->address = $request->address;
            $school->country = $request->country;
            $school->city = $request->city;
            $school->zip = $request->zip;
            $school->stages = $request->stages;
            $school->status = $request->status;
            $school->teachers_count = $request->teachers_count;
            $school->students_count = $request->students_count;
            $school->save();
            DB::commit();
            $response = ['Successfully Updated the School'];
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
    //-------------DELETE SCHOOL----------------
    public function delete($id){
        try {
            DB::beginTransaction();
            $school =School::findOrFail($id);
            $school->delete();
            DB::commit();
            $response = ['Successfully deleted School'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['school not found'];
                return response()->json($response, 404);

            }
        }
    }
}
