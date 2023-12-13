<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\DB;
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

    //----------DELETE TRANSACTION----------------
    public function deleteTransactionHistory($id){
        $history=TransactionHistory::find($id)->first();
        $history->delete();
        $response=['Successfully deleted'];
        return response()->json($response, 200);
    }
}
