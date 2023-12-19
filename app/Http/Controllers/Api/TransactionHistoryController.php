<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class TransactionHistoryController extends Controller
{
    //----------GET TRANSACTION LIST-------------
    public function getTransactionHistory($id=null){
        if($id){
            $history=TransactionHistory::where('user_id',$id)->get();
        }else{
            $history=TransactionHistory::get();
        }
        return response()->json($history, 200);
    }
    //-----------FILTER TRANSACTION HISTORY-----------
    public function filterTransactionHistory(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'required',
            'value' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $history='';
        if($request->type=='Amount'){
            $history=TransactionHistory::where('user_id',$request->user_id)->where('amount',$request->value)->get();
        }
        if($request->type=='Type'){
            $history=TransactionHistory::where('user_id',$request->user_id)->where('type',$request->value)->get();
        }
        if($request->type=='Date'){
            $history=TransactionHistory::where('user_id',$request->user_id)->whereDate('created_at','=', Carbon::parse($request->value)->toDateString())->get();
        }
        if($request->type=='Clear'){
            $history=TransactionHistory::where('user_id',$request->user_id)->get();
        }
        return response()->json($history, 200);
    }
    //----------DELETE TRANSACTION----------------
    public function deleteTransactionHistory($id){
        $history=TransactionHistory::find($id)->first();
        $history->delete();
        $response=['Successfully deleted'];
        return response()->json($response, 200);
    }
}
