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
use Stripe\Refund as StripeRefund;
use Stripe\PaymentIntent;
use App\Models\UserCard;
use App\Models\School;
use App\Models\User;
use App\Models\Staff;
use App\Models\Student;
use Carbon\Carbon;

class MyPurchaseController extends Controller
{
    //---------------GET USER PURCHASES-------------
    public function getMyPurchases(Request $request)
    {  
        $user=Auth::user();
        if($user->role!=='student' && $user->role!=='staff'){
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
        }else if($request->type=='Date Range'){
            $fromDate = Carbon::createFromFormat('d/m/Y', $request->value['fromDate'])->startOfDay()->format('Y-m-d 00:00:0');
            $toDate = Carbon::createFromFormat('d/m/Y', $request->value['toDate'])->endOfDay()->format('Y-m-d 23:59:59');
            $myPurchases = MyPurchasesResource::collection(
                MyPurchase::whereBetween('created_at', [$fromDate, $toDate])->get()
            );
        }
        return response()->json($myPurchases, 200);
    }

    //---------------FILTER REFUND REQUESTS-------------
    public function filterRefunds(Request $request){
        if($request->type=='Buyer'){
            $refunds = RefundResource::collection(
                Refund::with('purchase')
                ->whereHas('purchase.user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%' . $request->value . '%')
                        ->orWhere('last_name', 'like', '%' . $request->value . '%')
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
                })
                ->orderBy('created_at', 'desc')
                ->get()
            );
        }else if($request->type=='Date Range'){
            $fromDate = Carbon::createFromFormat('d/m/Y', $request->value['fromDate'])->startOfDay()->format('Y-m-d 00:00:0');
            $toDate = Carbon::createFromFormat('d/m/Y', $request->value['toDate'])->endOfDay()->format('Y-m-d 23:59:59');
            $refunds = RefundResource::collection(
                Refund::with('purchase.user')->whereBetween('created_at', [$fromDate, $toDate])->get()
            );
        }
        return response()->json($refunds, 200);
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
    public function refundStatus(Request $request)
    {
        $purchase=MyPurchase::find($request->purchase_id);
        $purchase->refund_status='refunded';

        $refund_recipient_id=$purchase->user_id;
        if($purchase->payment_card==null){
        $refund_recipient_wallet=Wallet::where('user_id',$refund_recipient_id)->first();
        $refund_recipient_wallet->ballance = $refund_recipient_wallet->ballance + $purchase->amount_paid;
        $refund_recipient_wallet->save();
        }else if($purchase->payment_card!=null){
            $user_id=$purchase->user_id;
            $amount=$purchase->amount_paid;
            $payment_card=$purchase->payment_card;
            $res=$this->makeRefund($purchase->latest_charge,$amount);
            if($res==false){
                $response = ['Something Went Wrong!'];
                return response()->json($response, 500);
            }
        }

        $user=Auth::user();
        $refund=new Refund();
        $refund->user_id=$purchase->user_id;
        $refund->purchase_id=$purchase->id;
        $refund->refund_status='refunded';
        $refund->save();
        
        $purchase->save();

        //-----------Refund Transaction History-----------
        $history=new TransactionHistory();
        $history->user_id=$refund_recipient_id;
        $history->amount=$purchase->amount_paid;
        $history->type='school_shop_refund';
        $history->save();
        $refund->refund_status='refunded';
        $refund->save();
        $response = ['Refund Successfull'];
        return response()->json($response, 200);
    }

    
    public function makeRefund($charge_id,$amount){
        StripeGateway::setApiKey(env('STRIPE_SECRET'));
        try {
            // Create a refund for a specific charge
            $refund = StripeRefund::create([
                'charge' => $charge_id, // ID of the charge to refund
                'amount' => $amount * 100, // Optional: refund specific amount in cents
            ]);
            return response()->json([
                'message' => 'Refund successful',
                'refund' => $refund,
            ],200);
        } catch (\Exception $e) {
            return false;
            return response()->json([
                'message' => 'Refund failed',
                'error' => $e->getMessage(),
            ], 400);
        }
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
                    // 'destination' => 'acct_1NlWiGGYrt7SylQr',
                    'destination' => "acct_1NlWiGGYrt7SylQr",
                ],
                'return_url' => 'https://your-website.com/thank-you',
            ]);
            // ['stripe_account' => 'acct_1Q8m46PPnkrk4pSx']

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
