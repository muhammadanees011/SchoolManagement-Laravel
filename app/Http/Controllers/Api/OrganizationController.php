<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Resources\Organization\OrganizationListResource;


class OrganizationController extends Controller
{
    //-------------GET ALL ORGANIZATIONS------------
    public function index(){
        // return OrganizationListResource::collection(Organization::all());
        $organizations=Organization::with('User')->get();
        return response()->json($organizations, 200);
    }
    //-------------CREATE ORGANIZATIONS------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'name'=>'required|string|max:255',
            'address' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:255',
            'description'=>'required|string|max:255',
            'website'=>'required|string|max:255'
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
            $user->role='organization_user';
            $user->save();
            $organization=new Organization();
            $organization->organization_user_id=$user->id;
            $organization->name=$request->name;
            $organization->address=$request->address;
            $organization->country=$request->country;
            $organization->city=$request->city;
            $organization->state=$request->state;
            $organization->zip=$request->zip;
            $organization->description=$request->description;
            $organization->website=$request->website;
            $organization->save();

            DB::commit();
            $response = ['Successfully created organization'];
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
    //-------------EDIT ORGANIZATIONS------------
    public function edit($id){
        $organization=Organization::with('user')->find($id);
        return response()->json($organization, 200);
    }
    //-------------UPDATE ORGANIZATIONS------------
    public function update(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'name'=>'required|string|max:255',
            'description'=>'required|string|max:255',
            'website'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'state'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'address'=>'required|string|max:255',
            'email'=>'required|email|max:255',
            'phone'=>'required|string|max:255',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();

            $organization =Organization::with('user')->find($id);
            $organization->name=$request->name;
            $organization->description=$request->description;
            $organization->website=$request->website;
            $organization->country=$request->country;
            $organization->city=$request->city;
            $organization->state=$request->state;
            $organization->address=$request->address;
            $organization->founded_date=$request->founded_date;
            $organization->user->update([
                'phone' => $request->phone,
                'email' => $request->email,
            ]);
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