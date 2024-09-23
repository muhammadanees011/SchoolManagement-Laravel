<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrganizationAdmin;
use App\Models\Student;
use App\Models\School;
use App\Models\Staff;
use App\Models\Wallet;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TransactionHistoryController extends Controller
{
    //-------------GET TOTAL Transactions--------------
    public function getTotalTransactions(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $transactions = TransactionHistory::sum('amount');
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $studentIds = Student::where('school_id',$schoolIds)->pluck('user_id')->toArray();
            $transactions = TransactionHistory::whereIn('user_id',$studentIds)->sum('amount');
        }else if($user->role=='staff'){
            $staff=Staff::with('school')->where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$staff->school->organization_id)->pluck('id')->toArray();
            $studentIds = Student::where('school_id',$schoolIds)->pluck('user_id')->toArray();
            $transactions = TransactionHistory::whereIn('user_id',$studentIds)->sum('amount');
        }else if($user->role=='student'){
            $transactions = TransactionHistory::where('user_id',$user->id)->sum('amount');
        }else if($user->role=='parent'){
            $transactions=0;
        }
        return response()->json($transactions, 200);
    }
    //----------GET TRANSACTION LIST-------------
    public function getTransactionHistory(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable',
            'admin_id' => 'nullable',
            'role' => 'nullable',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->user_id){
            $history=TransactionHistory::where('user_id',$request->user_id)->orderBy('created_at', 'desc')->paginate($request->entries_per_page);
        }else{
            if($request->admin_id==null){
                $history=TransactionHistory::with('user')->orderBy('created_at', 'desc')->paginate($request->entries_per_page);
            }else if($request->role=='organization_admin' && $request->admin_id!=null){
                $admin=OrganizationAdmin::where('user_id',$request->admin_id)->first();
                $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
                $studentIds = Student::whereIn('school_id', $schoolIds)->pluck('user_id')->toArray();
                $history=TransactionHistory::whereIn('user_id',$studentIds)->with('user')->orderBy('created_at', 'desc')->paginate($request->entries_per_page);  
            }else if($request->role=='staff' && $request->admin_id!=null){
                $user=Staff::where('user_id',$request->admin_id)->first();
                $studentIds = Student::where('school_id', $user->school_id)->pluck('user_id')->toArray();
                $history=TransactionHistory::whereIn('user_id',$studentIds)
                ->orWhere('user_id', $request->admin_id)
                ->with('user')->orderBy('created_at', 'desc')->paginate($request->entries_per_page); 
            }else if($request->role=='parent' && $request->admin_id!=null){
                $history=TransactionHistory::where('user_id',$request->admin_id)->with('user')->orderBy('created_at', 'desc')->paginate($request->entries_per_page); 
            }
        }
        $pagination = [
            'current_page' => $history->currentPage(),
            'last_page' => $history->lastPage(),
            'per_page' => $history->perPage(),
            'total' => $history->total(),
        ];
        $response['data']=$history;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
        // return response()->json($history, 200);
    }
    //-----------FILTER TRANSACTION HISTORY-----------
    public function filterTransactionHistory(Request $request){
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'value' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }

        $user=Auth::user();
        if($user->role=='student' || $user->role=='staff' || $user->role=='parent'){
            $user_id=$user->id;
        }else{
            $user_id=null;
        }

        $history='';
        if($request->type=='User'){
            $history=TransactionHistory::with('user')->whereHas('user', function($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->value . '%')
                ->orWhere('last_name', 'like', '%' . $request->value . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
            })
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id);
            })
            ->get();
        }
        if($request->type=='Amount'){
            $history=TransactionHistory::with('user')->where('amount',$request->value)
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id);
            })->get();
        }
        if($request->type=='Type'){
            $history=TransactionHistory::with('user')->where('type',$request->value)
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id);
            })->get();
        }
        if($request->type=='Date'){
            $history=TransactionHistory::with('user')->whereDate('created_at','=', Carbon::parse($request->value)->toDateString())
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id);
            })->get();
        }
        return response()->json($history, 200);
    }
    //-----------STUDENT DASHBOARD TRANSACTION-----------
    public function studentDashboard(Request $request){
        $user=Auth::user();

        $startOfWeek = Carbon::now()->startOfWeek()->subWeek();
        $endOfWeek = Carbon::now()->endOfWeek()->subWeek();
        $totalAmountLastWeek = TransactionHistory::where('user_id', $user->id)
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->sum('amount');

        $startOfMonth = Carbon::now()->startOfMonth()->subMonth();
        $endOfMonth = Carbon::now()->endOfMonth()->subMonth();
        $totalAmountLastMonth = TransactionHistory::where('user_id', $user->id)
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->sum('amount');

        $currenctBalance=Wallet::where('user_id',$user->id)->first();

        $response['last_week']=$totalAmountLastWeek;
        $response['last_month']=$totalAmountLastMonth;
        $response['current_balance']=$currenctBalance->ballance;

        return response()->json($response, 200);
    }
    //----------DELETE TRANSACTION----------------
    public function deleteTransactionHistory($id){
        $history=TransactionHistory::find($id)->first();
        $history->delete();
        $response=['Successfully deleted'];
        return response()->json($response, 200);
    }
}
