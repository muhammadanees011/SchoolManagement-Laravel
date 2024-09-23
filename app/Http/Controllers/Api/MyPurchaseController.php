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
use Stripe\Stripe as StripeGateway;
use Stripe\PaymentIntent;
use App\Models\UserCard;
use App\Models\School;
use App\Models\User;
use App\Models\Staff;
use App\Models\Student;


class MyPurchaseController extends Controller
{
    //---------------GET USER PURCHASES-------------
    public function getMyPurchases(Request $request)
    {  
        $user=Auth::user();
        if($user->role=='super_admin'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->orderBy('created_at', 'desc')
                ->paginate($request->entries_per_page)
            );

        }else if($user->role=='organization_admin'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->orderBy('created_at', 'desc')
                ->paginate($request->entries_per_page)
            );
        }else if($user->role=='student' || $user->role=='staff'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')->where('user_id',$user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->entries_per_page)
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

    //---------------FILTER PURCHASE HISTORY-------------
    public function filterPurchaseHistory(Request $request){
        if($request->type=='Item Name'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::whereHas('shopItems', function($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->value . '%');
                })->get()
            );
        }else if($request->type=='Price'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->where('total_price', 'like', '%' . $request->value . '%')->get()
            );
        }else if($request->type=='Amount Paid'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->where('amount_paid', 'like', '%' . $request->value . '%')->get()
            );
        }else if($request->type=='Payment Status'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::with('shopItems.payment')
                ->where('payment_status', 'like', '%' . $request->value . '%')->get()
            );
        }else if($request->type=='Buyer'){
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::whereHas('user', function($query) use ($request) {
                    $query->where('first_name', 'like', '%' . $request->value . '%')
                    ->orWhere('last_name', 'like', '%' . $request->value . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
                })->get()
            );
        }
        return response()->json($myPurchases, 200);
    }

    //---------------FILTER REFUND REQUESTS-------------
    public function filterRefunds(Request $request){
        if($request->type=='Buyer'){
            $purchases=MyPurchase::with('user')
            ->whereHas('user', function($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->value . '%')
                ->orWhere('last_name', 'like', '%' . $request->value . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
            })->get();
        }
        return response()->json($purchases, 200);
    }

    //---------------GET REQUEST FOR REFUNDS-------------
    public function getRefundRequest(Request $request)
    {
        $user=Auth::user();
        $refunds= RefundResource::collection(
        Refund::with('purchase')->orderBy('created_at','desc')->paginate($request->entries_per_page)
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

        if($refund->refund_status=='refund_requested' && $request->refund_status=='refunded'){

        $purchase=MyPurchase::find($refund->purchase->id);
        $purchase->refund_status='refunded';

        $refund_recipient_id=$refund->purchase->user_id;
        if($purchase->payment_card==null){
        $refund_recipient_wallet=Wallet::where('user_id',$refund_recipient_id)->first();
        $refund_recipient_wallet->ballance = $refund_recipient_wallet->ballance + $refund->purchase->amount_paid;
        $refund_recipient_wallet->save();
        }else if($purchase->payment_card!=null){
            $user_id=$purchase->user_id;
            $amount=$refund->purchase->amount_paid;
            $payment_card=$purchase->payment_card;
            $res=$this->initiatePayment($user_id,$amount,$payment_card);
            if($res==false){
                return response()->json(['error' => ['Something went wrong']], 500);
            }
        }
        
        $purchase->save();

        //-----------Refund Transaction History-----------
        $history=new TransactionHistory();
        $history->user_id=$refund_recipient_id;
        $history->amount=$refund->purchase->amount_paid;
        $history->type='school_shop_refund';
        $history->save();
        $refund->refund_status='refunded';
        $refund->save();
        $response = ['Refund Successfull'];
        }else if($refund->refund_status=='refund_requested' && $request->refund_status=='refund_rejected'){
            $purchase=MyPurchase::find($refund->purchase->id);
            $purchase->refund_status='not_refunded';
            $purchase->save();

            $refund->refund_status='refund_rejected';
            $refund->save();
            $response = ['Refund Request Declined'];
        }
        else{
            $response = ['Something went wrong'];
        }
        return response()->json($response, 200);
    }

    
    //--------------MAKET A PAYMENT-------------
    public function initiatePayment($user_id,$amount,$recipientStripeAccountId)
    {
        StripeGateway::setApiKey(env('STRIPE_SECRET'));
        try {
            $user=User::find($user_id);
            if($user->role=='student'){
                $student=Student::where('user_id',$user->id)->first();
                $payment_method=UserCard::where('school_id',$student->school_id)->first();
                $school=School::where('id',$student->school_id)->first();
            }else if($user->role=='staff'){
                $staff=Staff::where('user_id',$user->id)->first();
                $payment_method=UserCard::where('school_id',$staff->school_id)->first();
                $school=School::where('id',$staff->school_id)->first();
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $amount * 100,
                'currency' => 'gbp',
                'customer' => $payment_method->customer_id,  //ID of the customer in Stripe
                'payment_method' =>$payment_method->card_id, //ID of the specific card
                'confirm' => true, // Confirm the payment immediately
                'transfer_data' => [
                    // 'destination' => $recipientStripeAccountId,
                    'destination' => "acct_1NlWiGGYrt7SylQr",
                ],
                'return_url' => 'https://your-website.com/thank-you',

            ]);
            return true;

        } catch (CardException $e) {
            return false;
            // Handle card errors, such as insufficient funds
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return false;
            // Handle other errors
            return response()->json(['error' => $e->getMessage()], 500);
        }
        // Payment was successful
        // return [
        //     'transaction_id'=>$history->id,
        //     'token' => (string) Str::uuid(),
        //     'client_secret' => $paymentIntent->client_secret,
        // ];
    }
}
