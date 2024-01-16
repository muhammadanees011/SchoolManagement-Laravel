<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\OrganizationAdmin;
use App\Models\Staff;
use App\Models\School;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TripsController extends Controller
{
    //------------CREATE TRIP--------------
    public function createTrip(Request $request){
        $validator = Validator::make($request->all(), [
            'organization_id' =>['nullable',Rule::exists('organizations', 'id')],
            'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'title' => 'required|string',
            'description' => 'nullable|string',
            'total_booking' => 'required|numeric',
            'accomodation_details'=>'required|string',
            'start_date'=>'required|string',
            'end_date'=>'required|string',
            'budget'=>'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $user=Auth::user();
            if($user->role=="super_admin"){
                $organization_id=$request->organization_id;
            }else if($user->role=="organization_admin"){
                $admin=OrganizationAdmin::where('user_id',$user->id)->first();
                $organization_id=$admin->organization_id;
            }
            else if($user->role=="staff"){
                $staff=Staff::where('user_id',$user->id)->first();
                $school=School::where('id',$staff->school_id)->first();                
                $organization_id=$school->organization_id;
            }
            $trip = new Trip();
            $trip->organization_id=$organization_id;
            $trip->attributes =$request["attributes"];
            $trip->title=$request->title;
            $trip->description=$request->description;
            $trip->accomodation_details=$request->accomodation_details;
            $trip->start_date=$request->start_date;
            $trip->end_date=$request->end_date;
            $trip->budget=$request->budget;
            $trip->save();
            DB::commit();
            $response = ['Successfully Created Trip'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                return response()->json($exception, 500);
            } else {
                return response()->json($exception, 500);
            }
        }
        
    }
    //-------------FIND TRIP-------------======
    public function findTrip($id){
        $trip=Trip::findOrFail($id);
        return response()->json($trip, 200);
    }
    //------------UPDATE TRIP--------------
    public function updateTrip(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'organization_id' =>['nullable',Rule::exists('organizations', 'id')],
            'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'title' => 'required|string',
            'description' => 'nullable|string',
            'total_booking' => 'required|numeric',
            'accomodation_details'=>'required|string',
            'start_date'=>'required|string',
            'end_date'=>'required|string',
            'budget'=>'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $user=Auth::user();
            if($user->role=="super_admin"){
                $organization_id=$request->organization_id;
            }else if($user->role=="organization_admin"){
                $admin=OrganizationAdmin::where('user_id',$user->id)->first();
                $organization_id=$admin->organization_id;
            }
            else if($user->role=="staff"){
                $staff=Staff::where('user_id',$user->id)->first();
                $school=School::where('id',$staff->school_id)->first();                
                $organization_id=$school->organization_id;
            }
            $trip =Trip::find($id);
            $trip->organization_id=$organization_id;
            $trip->attributes =$request["attributes"];
            $trip->title=$request->title;
            $trip->description=$request->description;
            $trip->accomodation_details=$request->accomodation_details;
            $trip->start_date=$request->start_date;
            $trip->end_date=$request->end_date;
            $trip->budget=$request->budget;
            $trip->save();
            DB::commit();
            $response = ['Successfully Updated Trip'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                return response()->json($exception, 500);
            } else {
                return response()->json($exception, 500);
            }
        }
        
    }
    //--------------DELETE TRIP--------------
    public function deleteTrip($id){
        try{
        $trip =Trip::findOrFail($id);
        $trip->delete();
        $response = ['Successfully Deleted Trip'];
        return response()->json($response, 200);
        } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['Trip not found'];
                return response()->json($response, 404);

            }
        }
    }
}
