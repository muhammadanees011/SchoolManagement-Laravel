<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\UserCard;
use App\Models\Student;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Models\TransactionHistory;
use App\Models\Wallet;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Illuminate\Support\Str;
use Stripe\Stripe as StripeGateway;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Exception\CardException;
use Illuminate\Support\Carbon;


class PaymentsController extends Controller
{
    protected $stripe;

    public function __construct(){
        $this->stripe = Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function createCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'name' => 'required|string',
            'email' => 'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $customer=$stripe->customers->create([
            'name' => $request->name,
            'email' => $request->email,
            ]);
            $user=User::where('id',$request->user_id)->first();
            $user->stripe_id=$customer->id;
            $now = Carbon::now();
            $user->created_at = $now;
            $user->updated_at = $now;
            $user->save();
            return response()->json($customer, 200);
             } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //--------------CREATE STUDENT/STAFF PAYMENT CARD---------------
    public function createCard(Request $request)
    {    
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|string',
            'card_token' => 'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            $customer_id=$request->customer_id;
            $card_token=$request->card_token;
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $card=$stripe->customers->createSource(
            $customer_id,
            ['source' => $card_token]
            );
            $user=User::where('stripe_id',$customer_id)->first();
            $userCard=new UserCard();
            $userCard->user_id=$user->id;
            $userCard->card_id=$card->id;
            $userCard->customer_id=$card->customer;
            $userCard->brand=$card->brand;
            $userCard->last_4=$card->last4;
            $userCard->card_expiry_month=$card->exp_month;
            $userCard->card_expiry_year=$card->exp_year;
            $userCard->save();
            return response()->json($card, 200);
             } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //--------------CREATE SCHOOL PAYMENT CARD---------------
    public function createSchoolCard(Request $request)
    {    
        $validator = Validator::make($request->all(), [
            'school_id' => 'required',
            'card_token' => 'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            $school=School::find($request->school_id);
            if($school){
                $card=UserCard::where('school_id',$school->id)->first();
                if($card){
                    return response()->json('Already have the card', 422);  
                }
            }
            if($school && $school->stripe_id){
                $customer_id=$school->stripe_id;
            }else{
                return response()->json('customer id not found', 422);
            }
            $card_token=$request->card_token;
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $card=$stripe->customers->createSource(
            $customer_id,
            ['source' => $card_token]
            );
            $userCard=new UserCard();
            $userCard->school_id=$school->id;
            $userCard->card_id=$card->id;
            $userCard->customer_id=$card->customer;
            $userCard->brand=$card->brand;
            $userCard->last_4=$card->last4;
            $userCard->card_expiry_month=$card->exp_month;
            $userCard->card_expiry_year=$card->exp_year;
            $userCard->save();
            return response()->json($card, 200);
                } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCustomerPaymentMethods(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'nullable'

        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            if($request->type=='school'){
                $user=School::find($request->user_id);
            }else{
                $user=User::find($request->user_id);
            }
            Stripe::setApiKey(env('STRIPE_SECRET'));
            // Replace 'cus_12345678901234567890' with the actual customer ID
            $customerId = $user->stripe_id;
            // Get the customer
            $customer = Customer::retrieve($customerId);
            // List all payment methods for the customer
            $paymentMethods = PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card',
            ]);
            return response()->json($paymentMethods, 200);
             } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    //-------------REMOVEPAYMENT METHOD---------
    public function removePaymentMethod(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'payment_method' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            $user=User::where('id',$request->user_id)->first();
            Stripe::setApiKey(env('STRIPE_SECRET'));
            $customerId = $user->stripe_id;
            $cardId = $request->payment_method;
            $paymentMethod = PaymentMethod::retrieve($cardId);
            $paymentMethod->detach(); 
            
            $card=UserCard::where('card_id',$cardId)->first();
            $card->delete();

            $response=["Payment method removed successfully"];
            return response()->json($response, 200);
             } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        } 
    }
    //--------------GET WALLET------------------
    public function getWallet($id){
        $wallet=Wallet::with('user')->where('user_id',$id)->first();
        return response()->json($wallet, 200);
    }
    //--------------ADMIN TOPUP-------------
    public function adminTopUp(Request $request)
    {
        $user=Auth::user();
         try {
            if($user->role=='super_admin' || $user->role=='organization_admin'|| $user->role=='staff')
            {
             $wallet=Wallet::where('user_id',$request->user_id)->first();
             $wallet->ballance=$wallet->ballance + $request->amount;
             $wallet->save();
 
             $history=new TransactionHistory();
             $history->user_id=$request->user_id;
             $history->amount=$request->amount;
             $history->type=$request->type;
             $history->save();
             // TOPUP was successful
            $response['message']="TopUp Successfull";
            return response()->json($response, 200);
            }else{
                $response['message']="TopUp Failed";
                return response()->json($response, 422);
            }
         }catch (Exception $e) {
             // Handle other errors
             return response()->json(['error' => $e->getMessage()], 500);
         }
    }

    //--------------BULK TOPUP-------------
    public function bulkTopUp(Request $request)
    {
        $user=Auth::user();
        try {
            if($user->role=='super_admin' || $user->role=='organization_admin'|| $user->role=='staff')
            {
                foreach($request->students as $student){
                    $wallet=Wallet::where('user_id',$student)->first();
                    $wallet->ballance=$wallet->ballance + $request->topup_amount;
                    $wallet->save();

                    $history=new TransactionHistory();
                    $history->user_id=$student;
                    $history->amount=$request->topup_amount;
                    $history->type='top_up';
                    $history->save();
                }

            // TOPUP was successful
            $response['message']="TopUp Successfull";
            return response()->json($response, 200);
            }else{
                $response['message']="TopUp Failed";
                return response()->json($response, 422);
            }
        }catch (Exception $e) {
            // Handle other errors
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    //--------------MAKET A PAYMENT-------------
    public function initiatePayment(Request $request)
    {
        StripeGateway::setApiKey(env('STRIPE_SECRET'));
        try {
            $user=User::find($request->user_id);
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100,
                'currency' => 'gbp',
                'customer' => $user->stripe_id,  //ID of the customer in Stripe
                'payment_method' =>$request->payment_method, //ID of the specific card
                'confirm' => true, // Confirm the payment immediately
                'transfer_data' => [
                    // 'destination' => $request->recipientStripeAccountId,
                    'destination' => "acct_1NlWiGGYrt7SylQr",
                ],
                'return_url' => 'https://your-website.com/thank-you',

            ]);
            $wallet=Wallet::where('user_id',$request->user_id)->first();
            $wallet->ballance=$wallet->ballance + $request->amount;
            $wallet->save();

            $history=new TransactionHistory();
            $history->user_id=$request->user_id;
            $history->amount=$request->amount;
            $history->acct_id='acct_1NlWiGGYrt7SylQr';
            $history->type=$request->type;
            $history->save();

        } catch (CardException $e) {
            // Handle card errors, such as insufficient funds
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            // Handle other errors
            return response()->json(['error' => $e->getMessage()], 500);
        }
        // Payment was successful
        return [
            'token' => (string) Str::uuid(),
            'client_secret' => $paymentIntent->client_secret,
        ];
    }
    //-------------CHECK BALANCE BEFORE MAKING PAYMENT---------
    public function checkBalance(Request $request){
        $validator = Validator::make($request->all(), [
            'mifare_id' => 'required',
            'user_type' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->user_type=='student'){
            $user=Student::where('mifare_id',$request->mifare_id)->first();
        }else if($request->user_type=='staff'){
            $user=Staff::where('mifare_id',$request->mifare_id)->first();
        }
        if(!$user){
            $response['message']=["user not found"];
            return response()->json($response, 422);
        }
        $wallet=Wallet::where('user_id',$user->user_id)->first();
        if(!$wallet){
            $response['message']=["user wallet not found"];
            return response()->json($response, 422);
        }
        if($wallet){
            // if($student->fsm_activated){
                // $response['ballance']=$wallet->ballance + ($student->fsm_amount ? (float)$student->fsm_amount : 0 );
            // }else if(!$student->fsm_activated){
                // $response['ballance']=$wallet->ballance;
            // }
            // $response['fsm_activated']=$student->fsm_activated==0 ? false:true ;
            $response['ballance']=$wallet->ballance;
            $response['fsm_amount']=$user->fsm_amount ? (float)number_format($user->fsm_amount, 2) :(float)number_format(0, 2);
            return response()->json($response, 200);
        }else{
            $response['message']=["not enough amount"];
            return response()->json($response, 422);
        }
    }
    //---------------CHARGE THE AMOUNT----------------
    public function redeemBalance(Request $request){
        $validator = Validator::make($request->all(), [
            'mifare_id' => 'required',
            'amount' => 'required|numeric',
            'user_type' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->user_type=='student'){
            $user=Student::where('mifare_id',$request->mifare_id)->first();
        }else if($request->user_type=='staff'){
            $user=Staff::where('mifare_id',$request->mifare_id)->first();
        }
        if(!$user){
            $response['message']=["user not found"];
            return response()->json($response, 422);
        }
        $wallet=Wallet::where('user_id',$user->user_id)->first();
        if(!$wallet){
            $response['message']=["user wallet not found"];
            return response()->json($response, 422);
        }
        $fsmAmount=$user->fsm_amount ? (float)$user->fsm_amount : 0 ;
        $netBalance=$wallet->ballance + ($fsmAmount ? $fsmAmount :0);
        if($netBalance > 0){
            if($fsmAmount>=$request->amount){
                $user->fsm_amount=$fsmAmount-$request->amount;
                $user->save();
            }else if($fsmAmount<$request->amount){
                $user->fsm_amount=0;
                $user->save();
                $chargeBallance=$request->amount - $fsmAmount;
                if($wallet->ballance >= $chargeBallance){
                    $wallet->ballance = $wallet->ballance - $chargeBallance;
                    $wallet->save();
                }else if($wallet->ballance < $chargeBallance){
                    $wallet->ballance = 0;
                    $wallet->save();
                }
            }
            //--------Save Transaction History-----------
            $history=new TransactionHistory();
            $history->user_id=$user->user_id;
            $history->type='pos_transaction';
            $history->amount=$request->amount;
            $history->save();
            $response['message']=["Payment Successfull"];
            return response()->json($response, 200);
        }else{
            $response['message']=["not enough amount"];
            return response()->json($response, 422);
        }
    }
    //---------------REFUND THE AMOUNT----------------
    public function refundAmount(Request $request){
        $validator = Validator::make($request->all(), [
            'mifare_id' => 'required',
            'amount' => 'required|numeric|gt:0',
            'user_type' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->user_type=='student'){
            $user=Student::where('mifare_id',$request->mifare_id)->first();
        }else if($request->user_type=='staff'){
            $user=Staff::where('mifare_id',$request->mifare_id)->first();
        }
        if(!$user){
            $response['message']=["user not found"];
            return response()->json($response, 422);
        }
        $wallet=Wallet::where('user_id',$user->user_id)->first();
        if(!$wallet){
            $response['message']=["user wallet not found"];
            return response()->json($response, 422);
        }
        $wallet->ballance = $wallet->ballance + $request->amount;
        $wallet->save();
        //--------Save Transaction History-----------
        $history=new TransactionHistory();
        $history->user_id=$user->user_id;
        $history->type='pos_refund';
        $history->amount=$request->amount;
        $history->save();
        $response['message']=["Refund Successfull"];
        return response()->json($response, 200);
    }
}
