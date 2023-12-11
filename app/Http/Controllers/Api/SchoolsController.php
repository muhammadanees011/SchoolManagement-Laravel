<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\School;
use App\Models\User;
use App\Models\SchoolShop;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Resources\Organization\list;

class SchoolsController extends Controller
{
    //-------------GET ALL SCHOOLS------------
    public function index(){
        $schools=School::with('user','organization')->get();
        return response()->json($schools, 200);
    }
    //-------------CREATE SCHOOL--------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'organization_id' => ['required',Rule::exists('organizations', 'id')],
            'title' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'website'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'state'=>'required|string|max:255',
            'tagline'=>'required|string|max:255',
            'description'=>'required|string|max:255',
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
            $user = new User();
            $user->email=$request->email;
            $user->phone=$request->phone;
            $user->password=Hash::make($request['password']);
            $user->role='school_user';
            $user->save();
            $school=new School();
            $school->user_id = $user->id;
            $school->organization_id = $request->organization_id;
            $school->title = $request->title;
            $school->description =$request->description;
            $school->tagline = $request->tagline;
            $school->address = $request->address;
            $school->country = $request->country;
            $school->website = $request->website;
            $school->city = $request->city;
            $school->zip = $request->zip;
            $school->state = $request->state;
            $school->stages = $request->stages;
            $school->status = $request->status;
            $school->teachers_count = $request->teachers_count;
            $school->students_count = $request->students_count;
            $school->save();
            $shop=new SchoolShop();
            $shop->school_id=$school->id;
            $shop->save();
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
    public function update(Request $request,$id){
        $school = School::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$school->user->id,
            'phone' => 'required|string|unique:users,phone,'.$school->user->id,
            'website'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'state'=>'required|string|max:255',
            'tagline'=>'required|string|max:255',
            'description'=>'required|string|max:255',
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
            $school->title = $request->title;
            $school->description =$request->description;
            $school->tagline = $request->tagline;
            $school->address = $request->address;
            $school->country = $request->country;
            $school->city = $request->city;
            $school->zip = $request->zip;
            $school->state = $request->state;
            $school->stages = $request->stages;
            $school->status = $request->status;
            $school->teachers_count = $request->teachers_count;
            $school->students_count = $request->students_count;
            $school->user->update([
                'phone' => $request->phone,
                'email' => $request->email,
            ]);
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
