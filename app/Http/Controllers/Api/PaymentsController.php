<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\UserCard;
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
            $user->save();
            return response()->json($customer, 200);
             } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

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

    public function getCustomerPaymentMethods(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            $user=User::find($request->user_id);
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
            'student_id' => 'required',
            'amount' => 'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $wallet=Wallet::where('user_id',$request->student_id)->first();
        if($wallet->ballance >= $request->amount){
            return response()->json($wallet, 200);
        }else{
            $response['message']=["not enoung amount"];
            return response()->json($response, 422);
        }
    }
    //---------------CHARGE THE AMOUNT----------------
    public function redeemBalance(Request $request){
        $validator = Validator::make($request->all(), [
            'student_id' => 'required',
            'amount' => 'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $wallet=Wallet::where('user_id',$request->student_id)->first();
        if($wallet->ballance >= $request->amount){
            $wallet->ballance = $wallet->ballance - $request->amount;
            $wallet->save();
            $response['message']=["Payment Successfull"];
            return response()->json($response, 200);
        }else{
            $response['message']=["not enoung amount"];
            return response()->json($response, 422);
        }
    }

}
