<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MyPurchase;
use App\Models\TransactionHistory;
use App\Models\Refund;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\MyPurchasesResource;
use App\Http\Resources\RefundResource;

class MyPurchaseController extends Controller
{
    //---------------GET USER PURCHASES-------------
    public function getMyPurchases()
    {  
        $user=Auth::user();
        if($user->role=='super_admin'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->orderBy('created_at', 'desc')
                ->paginate(20)
            );

        }else if($user->role=='organization_admin'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->orderBy('created_at', 'desc')
                ->paginate(20)
            );
        }else if($user->role=='student' || $user->role=='staff'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')->where('user_id',$user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20)
            );
        }
        $pagination = [
            'current_page' => $myPurchases->currentPage(),
            'last_page' => $myPurchases->lastPage(),
            'per_page' => $myPurchases->perPage(),
            'total' => $myPurchases->total(),
        ];
        $response['data']=$myPurchases;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }

    //---------------GET REQUEST FOR REFUNDS-------------
    public function getRefundRequest(Request $request)
    {
        $user=Auth::user();
        $refunds= RefundResource::collection(
        Refund::with('purchase')->orderBy('created_at','desc')->paginate(20)
        );
        $pagination = [
        'current_page' => $refunds->currentPage(),
        'last_page' => $refunds->lastPage(),
        'per_page' => $refunds->perPage(),
        'total' => $refunds->total(),
        ];
        $response['data']=$refunds;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }
    
    //---------------REQUEST FOR REFUNDS-------------
    public function refundRequest(Request $request)
    {
        $myPurchases=MyPurchase::find($request->purchase_id);
        if($myPurchases->refund_status=='refunded' || $myPurchases->refund_status=='refund_requested'){
            $response['message'] ='already refunded/requested';
            return response()->json($response, 200);
        }else{
        $user=Auth::user();
        $refund=new Refund();
        $refund->user_id=$user->id;
        $refund->purchase_id=$request->purchase_id;
        $refund->refund_status='refund_requested';
        $refund->save();
        $myPurchases=MyPurchase::find($request->purchase_id);
        $myPurchases->refund_status='refund_requested';
        $myPurchases->save();
        $response['message'] ='refund requested successfully';
        return response()->json($response, 200);
        }
    }

    //---------------REQUEST FOR REFUNDS-------------
    public function refundStatus(Request $request)
    {
        $refund=Refund::with('purchase')->find($request->refund_id);

        if($refund->refund_status!=='refunded' && $request->refund_status=='refunded'){

        $purchase=MyPurchase::find($refund->purchase->id);
        $purchase->refund_status='refunded';
        $purchase->save();

        $refund_recipient_id=$refund->purchase->user_id;
        $refund_recipient_wallet=Wallet::where('user_id',$refund_recipient_id)->first();
        $refund_recipient_wallet->ballance = $refund_recipient_wallet->ballance + $refund->purchase->amount_paid;
        $refund_recipient_wallet->save();

        //-----------Refund Transaction History-----------
        $history=new TransactionHistory();
        $history->user_id=$refund_recipient_id;
        $history->amount=$refund->purchase->amount_paid;
        $history->type='school_shop_refund';
        $history->save();
        
        $response = ['Refund Successfull'];
        }else{
            $response = ['Something went wrong'];
        }

        $refund->refund_status=$request->refund_status;
        $refund->save();
        return response()->json($response, 200);
    }
}
