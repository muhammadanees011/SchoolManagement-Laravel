<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Organization;
use App\Models\Student;
use App\Models\School;
use App\Models\Parents;
use App\Models\Staff;
use App\Models\OrganizationShop;
use App\Models\OrganizationAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Resources\Organization\OrganizationListResource;


class OrganizationController extends Controller
{
    public function getOrganizationName(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id'=>'required|numeric',
            'role'=>'required|string|max:255',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->role=='student'){
            $student=Student::where('user_id',$request->user_id)->first();
            $school=School::where('id',$student->school_id)->first();
            $organization=Organization::where('id',$school->organization_id)->first();
        }elseif($request->role=='staff'){
            $staff=Staff::where('user_id',$request->user_id)->first();
            $school=School::where('id',$staff->school_id)->first();
            $organization=Organization::where('id',$school->organization_id)->first();
        }elseif($request->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$request->user_id)->first();
            $organization=Organization::where('id',$admin->organization_id)->first();
        }elseif($request->role=='parent'){
            $parent=Parents::where('parent_id',$request->user_id)->first();
            $student=Student::where('id',$parent->student_id)->first();
            $school=School::where('id',$student->school_id)->first();
            $organization=Organization::where('id',$school->organization_id)->first();
        }else{
            $response["message"]="invalid user role";
            return response()->json($response, 200);
        }
        $response["organization_name"]=$organization->name;
        return response()->json($response, 200);
    }
    //-------------GET ALL ORGANIZATIONS------------
    public function index(){
        // return OrganizationListResource::collection(Organization::all());
        $user=Auth::user();
        if($user->role=='organization_admin'){
            $organizationAdmin=OrganizationAdmin::where('user_id',$user->id)->first();
            $organizations=Organization::with('User')->where('id',$organizationAdmin->id)->get();
        }else if($user->role=='super_admin'){
            $organizations=Organization::with('User')->get();
        }
        return response()->json($organizations, 200);
    }
    //-------------CREATE ORGANIZATIONS------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'name'=>'required|string|max:255',
            'email'=>'required|email|max:255|unique:organizations',
            'phone'=>'nullable|string|max:255|unique:organizations',
            'address' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'zip' => 'required|string|max:255',
            'website'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $organization=new Organization();
            $organization->name=$request->name;
            $organization->email=$request->email;
            $organization->phone=$request->phone;
            $organization->address=$request->address;
            $organization->country=$request->country;
            $organization->city=$request->city;
            $organization->zip=$request->zip;
            $organization->website=$request->website;
            $organization->save();
            $shop=new OrganizationShop();
            $shop->organization_id=$organization->id;
            $shop->shop_name=$request->name.' '.'Shop';
            $shop->save();
            DB::commit();
            $response = ['Successfully created organization'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json(['errors'=>$exception->getMessage()], 422);
            }
        }
    }
    //-------------EDIT ORGANIZATIONS------------
    public function edit($id){
        $organization=Organization::with('user')->find($id);
        return response()->json($organization, 200);
    }
    //-------------UPDATE ORGANIZATIONS------------
    public function update(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'name'=>'required|string|max:255',
            'website'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'email' => 'required|email|max:255|unique:organizations,email,' . $id,
            'phone' => 'nullable|string|unique:organizations,phone,' . $id,
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $organization =Organization::find($id);
            $organization->name=$request->name;
            $organization->email=$request->email;
            $organization->phone=$request->phone;
            $organization->website=$request->website;
            $organization->country=$request->country;
            $organization->city=$request->city;
            $organization->address=$request->address;
            $organization->founded_date=$request->founded_date;
            $organization->save();
            DB::commit();
            $response = ['Successfully updated organization',$organization];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return $this->sendError($exception->getMessage(), null);
            }
        }
    }

    //-------------DELETE ORGANIZATION------------
    public function delete($id){
        try {
            DB::beginTransaction();
            $organization =Organization::findOrFail($id);
            $organization->delete();
            DB::commit();
            $response = ['Successfully deleted organization'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['organization not found'];
                return response()->json($response, 404);

            }
        }
    }
}
