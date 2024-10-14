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
use Illuminate\Support\Facades\Storage;

class SchoolsController extends Controller
{
    //-------------GET TOTAL SCHOOLS------------
    public function totalSchools(){
        $user=Auth::user();
        if($user->role!=='staff' && $user->role!=='student'){
            $schools=School::count();
        }
        // else if($user->role=='organization_admin'){
        //     $admin=OrganizationAdmin::where('user_id',$user->id)->first();
        //     $schools=School::where('organization_id',$admin->organization_id)->count();
        // }
        else if($user->role=='staff'){
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
            $schools=School::where('status','!=','deleted')->with('user','organization')->get();
        }else{
            $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
            $schools=School::where('status','!=','deleted')->where('organization_id',$admin->organization_id)->with('user','organization')->get();
        }
        return response()->json($schools, 200);
    }
    //-------------CREATE SCHOOL--------------
    public function create(Request $request,$admin_id=null){
        $validator = Validator::make($request->all(), [
            'organization_id' => 'nullable',
            'title' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:schools',
            'phone' => 'nullable|string|unique:schools',
            'website'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'teachers_count'=>'nullable|numeric',
            'students_count'=>'nullable|numeric',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        // try {
        //     DB::beginTransaction();
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
            $school->address = $request->address;
            $school->country = $request->country;
            $school->website = $request->website;
            $school->city = $request->city;
            $school->zip = $request->zip;
            $school->status = $request->status;
            $school->teachers_count = 0;
            $school->students_count = 0;
            $school->save();
            DB::commit();
            $data['school_id']=$school->id;
            $data['name']=$school->title.' Site';
            $data['email']=$school->email;
            $res=$this->createCustomer($data);
            $response = ['Successfully created the School'];
            return response()->json($res, 200);
        // } catch (\Exception $exception) {
        //     DB::rollback();
        //     if (('APP_ENV') == 'local') {
        //         dd($exception);
        //     } else {
        //     return response()->json('Something went wrong', 200);
        //     }
        // }
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
            'email' => 'required|email|max:255|unique:schools,email,' . $id,
            'phone' => 'nullable|string|unique:schools,phone,' . $id,
            'website'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'country'=>'required|string|max:255',
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
            $school->website = $request->website;
            $school->address = $request->address;
            $school->country = $request->country;
            $school->city = $request->city;
            $school->zip = $request->zip;
            $school->status = $request->status;
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
            $school =School::find($id);
            $school->status='deleted';
            $school->save();
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

    public function storeBrandingSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id'=>'required',
            'primary_color' => 'nullable',
            'secondary_color' => 'nullable',
            'logo' => 'nullable',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $school=School::find($request->school_id);
        $school->primary_color=$request->primary_color;
        $school->secondary_color=$request->secondary_color;
        if($request->file('logo')){
        $path = $request->file('logo')->store('uploads', 'public');
        $school->logo=Storage::url($path);
        }
        $school->save();   
        $response = ['Successfully updated School branding'];
        return response()->json($response, 200);
    }

    public function getSettings(Request $request)
    {
        $school=School::find($request->school_id);
        return response()->json($school, 200);
    }

    public function updateFinanceCoordiantorEmail(Request $request)
    {
        $school=School::find($request->school_id);
        $school->finance_coordinator_email=$request->finance_coordinator_email;
        $school->save();
        return response()->json(['message' => 'Saved successfully'], 200);
    }

    //-------------GET ARCHIVED SCHOOLS------------
    public function archivedSchools($admin_id=null){
        if($admin_id==null){
            $schools=School::where('status','deleted')->with('user','organization')->get();
        }else{
            $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
            $schools=School::where('organization_id',$admin->organization_id)->where('status','deleted')->with('user','organization')->get();
        }
        return response()->json($schools, 200);
    }

    public function bulkRestoreSchools(Request $request)
    {
        $ids = $request->all();
        foreach ($ids as $record) {
            $school=School::where('id',$record)->first();
            $school->status='active';
            $school->save();
        }
        return response()->json(['message' => 'Schools restored successfully'], 200);
    }

    //-------------CREATE STRIPE CUSTOMER--------
    public function createCustomer($data)
    {
        try{
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $customer=$stripe->customers->create([
            'name' => $data['name'],
            'email' => $data['email'],
            ]);
            $school=School::where('id',$data['school_id'])->first();
            $school->stripe_id=$customer->id;
            $school->save();
            } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
