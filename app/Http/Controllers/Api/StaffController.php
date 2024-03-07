<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;
use App\Models\User;
use App\Models\Wallet;
use App\Models\OrganizationAdmin;
use App\Models\TransactionHistory;
use App\Models\School;
use App\Mail\WelcomeEmail;
use App\Http\Resources\StaffResource;
use Illuminate\Support\Facades\Auth;


class StaffController extends Controller
{
    //-------------GET ALL STAFF-------------
    public function getAllStaff(Request $request){
        if($request->user_id==null){
        $staff = Staff::with('user', 'school')->paginate(20);
        $staff=StaffResource::collection($staff);
        }else{
            $admin=OrganizationAdmin::where('user_id',$request->user_id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $staff= StaffResource::collection(Staff::with('user', 'school')->whereIn('school_id', $schoolIds)->paginate(20));
        }
        $pagination = [
        'current_page' => $staff->currentPage(),
        'last_page' => $staff->lastPage(),
        'per_page' => $staff->perPage(),
        'total' => $staff->total(),
        ];
        $response['data']=$staff;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }
    //-------------CREATE STAFF--------------
    public function createStaff(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'staff_id' =>'required',
            'mifare_id' =>'required|numeric',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'nullable|string|min:6|confirmed',
            'address'=>'nullable|string|max:255',
            'country'=>'nullable|string|max:255',
            'city'=>'nullable|string|max:255',
            'zip'=>'nullable|string|max:255',
            'status'=>'nullable|string|max:255',
            'role'=>'required|string|max:255',
            'balance'=>'required|numeric'
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
            $user->role='staff';
            $user->address=$request->address;
            $user->country=$request->country;
            $user->city=$request->city;
            $user->zip=$request->zip;
            $user->status = $request->status;
            $user->save();

            $role = \Spatie\Permission\Models\Role::where('name', $request->role)->where('guard_name', 'api')->first();
            $user->assignRole($role);

            $staff=new Staff();
            $staff->user_id = $user->id;
            $staff->staff_id = $request->staff_id;
            $staff->mifare_id = $request->mifare_id;
            $staff->school_id = $request->school_id;
            $staff->save();

            $school=School::where('id',$request->school_id)->first();
            $school->teachers_count=$school->teachers_count + 1;
            $school->save();

            $userWallet=new Wallet();
            $userWallet->user_id=$user->id;
            $userWallet->ballance=$request->balance ? $request->balance: 0;
            $userWallet->save();
            DB::commit();
            //------------SEND WELCOME MAIL------------
            $studentName = $request->first_name . ' ' . $request->last_name;
            $mailData = [
            'title' => 'Congratulations you have successfully created your StudentPay account!',
            'body' => $request['password'],
            'user_name'=> $studentName,
            ];
            Mail::to($request->email)->send(new WelcomeEmail($mailData));
            $response['message'] = ['Successfully created the Staff'];
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

    //-------------EDIT STAFF------------------
    public function editStaff($id){
        $school=Staff::with('user.UserRole.Role','balance')->find($id);
        return response()->json($school, 200);
    }

    //-------------UPDATE STAFF--------------
    public function updateStaff(Request $request,$id){
        $staff=Staff::with('user')->find($id);
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'staff_id' =>'required',
            'mifare_id' =>'required|numeric',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$staff->user->id,
            'phone' => 'nullable|string|unique:users,phone,'.$staff->user->id,
            'address'=>'nullable|string|max:255',
            'country'=>'nullable|string|max:255',
            'city'=>'nullable|string|max:255',
            'zip'=>'nullable|string|max:255',
            'status'=>'nullable|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'role'=>'nullable|string|max:255',
            'balance' => 'nullable|numeric',
            'add_amount' => 'nullable|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
                $staff->school_id = $request->school_id;
                $staff->staff_id = $request->staff_id;
                $staff->mifare_id = $request->mifare_id;
                $updateData = [
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'address' => $request->address,
                    'country' => $request->country,
                    'city' => $request->city,
                    'zip' => $request->zip,
                    // 'status' => $request->status,
                ];
                if ($request->password) {
                    $updateData['password'] = Hash::make($request->password);
                }
                $wallet=Wallet::where('user_id',$staff->user_id)->first();
                $wallet->ballance=$request->balance + ($request->add_amount ? $request->add_amount:0 );
                $wallet->save();
                $staff->user->update($updateData);
                $staff->save();

                $user=User::where('id',$staff->user_id)->update(['status'=>$request->status]);

                //--------Save Transaction History-----------
                if($request->add_amount){
                    $history=new TransactionHistory();
                    $history->user_id=$staff->user_id;
                    $history->type='top_up';
                    $history->amount=$request->add_amount;
                    $history->save();
                }
                // $user->syncRoles([]);
                // $role = \Spatie\Permission\Models\Role::where('name', $request->role)->where('guard_name', 'api')->first();
                // $user->assignRole($role);

            DB::commit();
            $response = ['Successfully Updated the Staff'];
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

    //-------------DELETE STAFF----------------
    public function deleteStaff($id){
        try {
            DB::beginTransaction();
            $staff =User::findOrFail($id);
            $staff->delete();
            DB::commit();
            $response = ['Successfully deleted Staff'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['staff not found'];
                return response()->json($response, 404);

            }
        }
    }

    //-------------SEARCH STAFF----------------

    public function searchStaff(Request $request){
        $validator = Validator::make($request->all(), [
            'searchString' => 'nullable',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        
        $user=Auth::user();
        if($user->role=='super_admin'){
            $staff = Staff::with(['user' => function ($query) {
                $query->with('balance');
            }, 'school'])
            ->where(function ($query) use ($request) {
                $query->whereHas('user', function ($subquery) use ($request) {
                    $subquery->where('first_name', 'like', '%' . $request->searchString . '%')
                    ->orWhere('last_name', 'like', '%' . $request->searchString . '%')
                    ->orWhere('mifare_id', 'like', '%' . $request->searchString . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->searchString . '%']);
                });
            })->get();
            $response= StaffResource::collection($staff);
        }else{
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();

            $staff = Staff::with(['user' => function ($query) {
                $query->with('balance');
            }, 'school'])
            ->whereIn('school_id', $schoolIds) // Filter based on school_id
            ->where(function ($query) use ($request) {
                $query->whereHas('user', function ($subquery) use ($request) {
                    $subquery->where('first_name', 'like', '%' . $request->searchString . '%')
                    ->orWhere('last_name', 'like', '%' . $request->searchString . '%')
                    ->orWhere('staff_id', 'like', '%' . $request->searchString . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->searchString . '%']);
                });
            })->get();
            $response= StaffResource::collection($staff);
        }

        return response()->json($response, 200);
    }

}
