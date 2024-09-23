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
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Illuminate\Support\Facades\Validator;


class ParentController extends Controller
{

    //-------------GET ALL Parents-------------
    public function getAllParents(Request $request,$admin_id=null){
        // $parents=Parents::with('user')->get();
        if($admin_id==null){
            $parents=User::where('role','parent')->paginate($request->entries_per_page);
            // $parents=Parents::with('user')->get();
        }else{
            $admin=OrganizationAdmin::where('user_id',$admin_id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $studentIds=Student::where('school_id',$schoolIds)->pluck('id')->toArray();
            $parents=Parents::whereIn('student_id', $studentIds)->with('user')->paginate($request->entries_per_page);
        }

        $pagination = [
            'current_page' => $parents->currentPage(),
            'last_page' => $parents->lastPage(),
            'per_page' => $parents->perPage(),
            'total' => $parents->total(),
        ];
        $response['data']=$parents;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }
 
    public function createParent(Request $request){
        $validator = Validator::make($request->all(), [
            // 'student_id' => ['required',Rule::exists('users', 'id')],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'school_id' => 'required',
            'password' => 'required|string|min:6|confirmed',
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
            $user->password=Hash::make($request['password']);
            $user->role='parent';
            $user->status = $request->status;
            $user->save();

            $parents=new Parents();
            $parents->parent_id = $user->id;
            $parents->school_id = $request->school_id;
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
        $parent=User::find($id);
        return response()->json($parent, 200);
    }

    public function updateParent(Request $request,$id){
        $parent=User::find($id);
        $validator = Validator::make($request->all(), [
            // 'student_id' => ['required',Rule::exists('students', 'id')],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$parent->id,
            'status'=>'required|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            // $parent->student_id = $request->student_id;
            $updateData = [
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'status' => $request->status,
            ];
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }
            $parent->update($updateData);
            $parent->save();
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

    public function addChildrenToParent(Request $request){
        $validator = Validator::make($request->all(), [
            'student_email' => 'required',
            'parent_id'=>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $user=User::where('email',$request->student_email)->first();
            $student=Student::where('user_id',$user->id)->first();
            $parent=Parents::where('parent_id', $request->parent_id)
            ->orderBy('created_at', 'desc')->first();
            if($parent->student_id==null){
                $parent->student_id=$user->id;
                $parent->save();
            }else{
                $parent=new Parents();
                $parent->parent_id = $request->parent_id;
                $parent->school_id = $student->school_id;
                $parent->student_id=$user->id;
                $parent->save();
            }
            DB::commit();
            $response = ['Successfully Added the Children'];
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

    public function getChildrensOfParent($id)
    {
        $parent=Parents::where('parent_id',$id)->with('student')->get();
        return response()->json($parent, 200);
    }

    public function deleteChildren($id)
    {
        $parent=Parents::where('student_id',$id)->first();
        $parent->delete();
        $response = ['Successfully deleted the Children'];
        return response()->json($response, 200);
    }

    public function selectChildren(Request $request)
    {
        $student_id=$request->student_id;
        $parent=Parents::where('student_id',$student_id)->first();
        $parent->status='Active';
        $parent->save();

        $user = Auth::user();
        $user->token()->revoke();

        $newUser = User::find($student_id);
        if ($newUser) {
            Auth::setUser($newUser);
            $user = Auth::user(); 
            $tokenResult = $user->createToken('Personal Access Token')->accessToken;
            $token = $tokenResult;
            $data['access_token'] = $token;
            $data['user'] =  $user;
            $school=null;

            if($user->role=='student'){
                $student=Student::where('user_id',$user->id)->first();
                $school=School::where('id',$student->school_id)->first();
            }

            $data["primary_color"]=$school!=null ? $school->primary_color : '#424246';
            $data["secondary_color"]=$school!=null ? $school->secondary_color : '#424246';
            $data["logo"]=$school ? $school->logo : null;

            return response()->json($data);
        }

        // if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){ 
        //     $user = Auth::user(); 
        //     $tokenResult = $user->createToken('Personal Access Token')->accessToken;
        //     $token = $tokenResult;
        //     $data['access_token'] = $token;
        //     $data['user'] =  $user;
        //     $school=null;

        //     if($user->role=='student'){
        //         $student=Student::where('user_id',$user->id)->first();
        //         $school=School::where('id',$student->school_id)->first();
        //     }

        //     $data["primary_color"]=$school!=null ? $school->primary_color : '#424246';
        //     $data["secondary_color"]=$school!=null ? $school->secondary_color : '#424246';
        //     $data["logo"]=$school ? $school->logo : null;

        //     return response()->json($data);
        // } 
    }
}
