<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\OrganizationAdmin;
use App\Models\Staff;
use App\Models\School;
use App\Models\Student;
use App\Models\PaymentPlan;
use App\Models\TripParticipant;
use App\Http\Resources\TripParticipantResource;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TripsController extends Controller
{
    public function getAllTrips(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $trips=Trip::with('Organization')->get();
        }else if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            $school=School::where('id',$student->school_id)->first();
            $attributeValues = $student->attributes;
            $trips = Trip::with('cart')->where('organization_id', $school->organization_id)
            ->where(function ($query) use ($attributeValues) {
                foreach ($attributeValues as $value) {
                    $query->orWhereJsonContains('attributes', $value);
                }
                // Include items with empty attributes as well
                $query->orWhereJsonLength('attributes', 0);
            })
            ->get();
        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $school=School::where('id',$staff->school_id)->first();
            $trips=Trip::where('organization_id',$school->organization_id)->get();
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $trips=Trip::where('organization_id',$admin->organization_id)->get();
        }
        return response()->json($trips, 200);
    }
    //------------CREATE TRIP--------------
    public function createTrip(Request $request){
        $validator = Validator::make($request->all(), [
            'organization_id' =>['nullable',Rule::exists('organizations', 'id')],
            'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'title' => 'required|string',
            'description' => 'nullable|string',
            'total_seats' => 'required|numeric',
            'accomodation_details'=>'required|string',
            'start_date'=>'required|string',
            'end_date'=>'required|string',
            'total_funds'=>'required|numeric', 
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
            $trip->attributes=$request["attributes"];
            $trip->title=$request->title;
            $trip->description=$request->description;
            $trip->accomodation_details=$request->accomodation_details;
            $trip->start_date=$request->start_date;
            $trip->end_date=$request->end_date;
            $trip->budget=$request->total_funds;
            $trip->total_booking=$request->total_seats;
            $trip->save();
            if($this->paymentPlan($request,$trip->id)){
                DB::commit();
            }else{
                DB::rollback();
                $response = ['Error Creating Payment Plan'];
                return response()->json($response, 500);
            }
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
    public function paymentPlan($request,$trip_id){
        $validator = Validator::make($request->all(), [
            'payment_plan' => 'required|in:deposit,manual,installments',
            //------------Installments-----------
            'total_installments' => 'required_if:payment_plan,installments|integer',
            'amount_per_installment' => 'required_if:payment_plan,installments|numeric',
            'initial_deposit_installments' => 'required_if:payment_plan,installments|numeric',
            'initial_deposit_deadline_installments' => 'required_if:payment_plan,installments|date',
            'other_installments_deadline_installments' => 'required_if:payment_plan,installments|array', 
            //-----------Deposit-----------------
            'total_deposit'=>'required_if:payment_plan,deposit|numeric',
            'initial_deposit'=>'required_if:payment_plan,deposit|numeric', 
            'final_deposit'=>'required_if:payment_plan,deposit|numeric', 
            'initial_deposit_deadline'=>'required_if:payment_plan,deposit|date', 
            'final_deposit_deadline'=>'required_if:payment_plan,deposit|date',
            //-----------Manual------------------
            'total_amount'=>'required_if:payment_plan,manual|numeric',
            'initial_amount'=>'required_if:payment_plan,manual|numeric',
            'final_amount'=>'required_if:payment_plan,manual|numeric',
            'initial_amount_deadline'=>'required_if:payment_plan,manual|date',
            'final_amount_deadline'=>'required_if:payment_plan,manual|date',
            'comments'=>'required_if:payment_plan,manual|string',
            ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            if($request->payment_plan=='installments'){
                $plan=new PaymentPlan();
                $plan->trip_id=$trip_id;
                $plan->total_installments=$request->total_installments;
                $plan->amount_per_installment=$request->amount_per_installment;
                $plan->initial_deposit_installments=$request->initial_deposit_installments;
                $plan->initial_deposit_deadline_installments=$request->initial_deposit_deadline_installments;
                $plan->other_installments_deadline_installments=$request["other_installments_deadline_installments"];
                $plan->save();
                DB::commit();
                return true;
            }
            if($request->payment_plan=='manual'){
                $plan=new PaymentPlan();
                $plan->trip_id=$trip_id;
                $plan->total_amount=$request->total_amount;
                $plan->initial_amount=$request->initial_amount;
                $plan->final_amount=$request->final_amount;
                $plan->initial_amount_deadline=$request->initial_amount_deadline;
                $plan->final_amount_deadline=$request->final_amount_deadline;
                $plan->comments=$request->comments;
                $plan->save();
                DB::commit();
                return true;
            }
            if($request->payment_plan=='deposit'){
                $plan=new PaymentPlan();
                $plan->trip_id=$trip_id;
                $plan->total_deposit=$request->total_deposit;
                $plan->initial_deposit=$request->initial_deposit;
                $plan->final_deposit=$request->final_deposit;
                $plan->initial_deposit_deadline=$request->initial_deposit_deadline;
                $plan->final_deposit_deadline=$request->final_deposit_deadline;
                $plan->save();
                DB::commit();
                return true;
            }
            return false;
        } catch (\Exception $exception) {
            DB::rollback();
            return false;
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
            'total_seats' => 'required|numeric',
            'accomodation_details'=>'required|string',
            'start_date'=>'required|string',
            'end_date'=>'required|string',
            'total_funds'=>'required|numeric',
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
            $trip->budget=$request->total_funds;
            $trip->total_booking=$request->total_seats;
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
    //--------------TRIP PARTICIPANTS--------------
    public function getTripParticipants($id){
        try{
        $trip = TripParticipantResource::collection(TripParticipant::where('trip_id', $id)->get());
        return response()->json($trip, 200);
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
