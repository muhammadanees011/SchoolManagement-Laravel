<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserCart;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\TransactionHistory;
use App\Models\TripParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Stripe\Stripe as StripeGateway;
use Stripe\PaymentIntent;
use Stripe\Exception\CardException;
use Illuminate\Support\Facades\Validator;

class UserCartController extends Controller
{
    //-------------ADD ITEM TO CART------------------
    public function addItemToCart(Request $request)
    {
        try {
            DB::beginTransaction();
            $user=Auth::user();
            $cartItem=new UserCart();
            $cartItem->user_id=$user->id;
            $cartItem->shop_item_id=$request->shop_item_id;
            $cartItem->save();
            DB::commit();
            $response = ['Successfully Added Item To The Cart'];
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
    //--------------ADD TRIP TO CART--------------
    public function addTripToCart(Request $request){
        $validator = Validator::make($request->all(), [
            'trip_id' =>['nullable',Rule::exists('trips', 'id')],
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{
            DB::beginTransaction();
            $user=Auth::user();
            $cartItem=new UserCart();
            $cartItem->user_id=$user->id;
            $cartItem->trip_id=$request->trip_id;
            $cartItem->save();
            DB::commit();
        $response = ['Successfully Added Trip To The Cart'];
        return response()->json($response, 200);
        } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['Something went wrong'];
                return response()->json($response, 404);

            }
        }
    }
    //-------------GET CART ITEMS------------------
    public function getUserCartItems(){
        try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem','Trip')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
            return response()->json($cartItems, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json($exception, 500);
            }
        }
    }
    //------------REMOVE ITEM FROM CART--------------
    public function removeItemFromCart(Request $request)
    {
        try {
            $user=Auth::user();
            $cartItem=UserCart::where('id',$request->item_id)->where('user_id',$user->id)->first();
            $cartItem->delete();
            $response = ['Removed Item Successfully'];
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
    //-----------------CHECKOUT----------------
    public function checkout(Request $request)
    {
        try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem','Trip')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
            if($cartItems->isEmpty()){
                $response = ['Cart is empty'];
                return response()->json($response, 422);
            }
            $totalAmount=0;
            foreach ($cartItems as $cartItem) {
                if ($cartItem->ShopItem) {
                    $totalAmount += $cartItem->ShopItem->price;
                }
                if ($cartItem->Trip) {
                    $totalAmount += $cartItem->Trip->budget;
                }
            }
                foreach ($cartItems as $cartItem) {
                if ($cartItem->ShopItem) {
                    $type='school_shop_funds';
                    $ItemAmount = $cartItem->ShopItem->price;
                    $this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type);
                }
                if ($cartItem->Trip) {
                    $type='trip_funds';
                    $ItemAmount = $cartItem->Trip->budget;
                    $result=$this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type);
                    //--------TRIP PARTICIPANT-----------
                    $trip=new TripParticipant();
                    $trip->user_id=$user->id;
                    $trip->trip_id=$cartItem->Trip->id;
                    $trip->participation_status='aproved';
                    $trip->transaction_id=$result['transaction_id'];
                    $trip->payment_status='paid';
                    $trip->save();
                }
                }
                //--------REMOVE ITEMS FROM CART-----------
                $deletedRows = UserCart::where('user_id', $user->id)->delete();
                $status=200;
                $response = ['Checkout Successful'];
            return response()->json($response, $status);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json($exception, 500);
            }
        }
    }

    //--------------MAKET A PAYMENT-------------
    public function initiatePayment($user_id,$amount,$payment_method,$type)
    {
        StripeGateway::setApiKey(env('STRIPE_SECRET'));
        try {
            $user=User::find($user_id);
            $paymentIntent = PaymentIntent::create([
                'amount' => $amount * 100,
                'currency' => 'gbp',
                'customer' => $user->stripe_id,  //ID of the customer in Stripe
                'payment_method' =>$payment_method, //ID of the specific card
                'confirm' => true, // Confirm the payment immediately
                'transfer_data' => [
                    // 'destination' => $request->recipientStripeAccountId,
                    'destination' => "acct_1NlWiGGYrt7SylQr",
                ],
                'return_url' => 'https://your-website.com/thank-you',

            ]);

            $history=new TransactionHistory();
            $history->user_id=$user_id;
            $history->amount=$amount;
            $history->type=$type;
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
            'transaction_id'=>$history->id,
            'token' => (string) Str::uuid(),
            'client_secret' => $paymentIntent->client_secret,
        ];
    }
}
