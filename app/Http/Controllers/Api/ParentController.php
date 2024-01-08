<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Parents;
use App\Models\Wallet;
use App\Models\School;
use App\Models\Student;
use App\Models\OrganizationAdmin;
use Illuminate\Support\Facades\Validator;


class ParentController extends Controller
{

    //-------------GET ALL Parents-------------
    public function getAllParents($admin_id=null){
        // $parents=Parents::with('user')->get();
        if($admin_id==null){
            $parents=Parents::with('user')->get();
        }else{
            $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $studentIds=Student::where('school_id',$schoolIds)->pluck('id')->toArray();
            $parents=Parents::whereIn('student_id', $studentIds)->with('user')->get();
        }
        return response()->json($parents, 200);
    }
 
    public function createParent(Request $request){
        $validator = Validator::make($request->all(), [
            'student_id' => ['required',Rule::exists('users', 'id')],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $user = new User();
            $user->first_name=$request->first_name;
            $user->last_name=$request->last_name;
            $user->email=$request->email;
            $user->phone=$request->phone;
            $user->password=Hash::make($request['password']);
            $user->role='parent';
            $user->address=$request->address;
            $user->country=$request->country;
            $user->city=$request->city;
            $user->zip=$request->zip;
            $user->status = $request->status;
            $user->save();

            $parents=new Parents();
            $parents->parent_id = $user->id;
            $parents->student_id = $request->student_id;
            $parents->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->ballance=0;
            $userWallet->save();

            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $name=$request->first_name . ' ' . $request->last_name;
            $customer=$stripe->customers->create([
            'name' => $name,
            'email' => $request->email,
            ]);
            $parent=User::where('id',$user->id)->first();
            $parent->stripe_id=$customer->id;
            $now = Carbon::now();
            $parent->created_at = $now;
            $parent->updated_at = $now;
            $parent->save();

            DB::commit();
            //------------SEND WELCOME MAIL------------
            $studentName = $request->first_name . ' ' . $request->last_name;
            $mailData = [
            'title' => 'Congratulations you have successfully created your StudentPay account!',
            'body' => $request['password'],
            'user_name'=> $studentName,
            ];
            Mail::to($request->email)->send(new WelcomeEmail($mailData));
            $response['message'] = ['Successfully created the Parent Account'];
            $response['user']=$user;
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json($exception, 500);
            }
        }
    }

    public function editParent($id){
        $parent=Parents::with('user')->find($id);
        return response()->json($parent, 200);
    }

    public function updateParent(Request $request,$id){
        $staff=Parents::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'student_id' => ['required',Rule::exists('students', 'id')],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$staff->user->id,
            'phone' => 'nullable|string|unique:users,phone,'.$staff->user->id,
            'address'=>'required|string|max:255',
            'country'=>'required|string|max:255',
            'city'=>'required|string|max:255',
            'zip'=>'required|string|max:255',
            'status'=>'required|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $staff->student_id = $request->student_id;
            $updateData = [
                'phone' => $request->phone,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'address' => $request->address,
                'country' => $request->country,
                'city' => $request->city,
                'zip' => $request->zip,
                'status' => $request->status,
            ];
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }
            $staff->user->update($updateData);
            $staff->save();
            DB::commit();
            $response = ['Successfully Updated the Parent'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json($exception, 500);
            }
        }
    }

    public function deleteParent($id){
        try {
            DB::beginTransaction();
            $staff =User::findOrFail($id);
            $staff->delete();
            DB::commit();
            $response = ['Successfully deleted Parent'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['parent not found'];
                return response()->json($response, 404);

            }
        }
    }
}
