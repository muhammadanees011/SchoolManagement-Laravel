<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserCart;
use App\Models\ShopItem;
use App\Models\MyPurchase;
use App\Models\MyInstallments;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\TransactionHistory;
use App\Http\Resources\MyInstallmentsRescource;
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
            $cartItems=UserCart::with('ShopItem.payment','Trip')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
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
        $validator = Validator::make($request->all(), [
            'payment_method' =>'nullable',
            'type' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }

        try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem.payment','Trip')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
            if($cartItems->isEmpty()){
                $response = ['Cart is empty'];
                return response()->json($response, 422);
            }
            $totalAmount=0;

            foreach ($cartItems as $cartItem) {
                if ($cartItem->ShopItem) {
                    if($cartItem->ShopItem->payment_plan=='installments'){
                      $totalAmount += $cartItem->ShopItem->payment->amount_per_installment;
                    }else if($cartItem->ShopItem->payment_plan=='installments_and_deposit'){
                        $totalAmount += $cartItem->ShopItem->payment->initial_deposit_installments;
                    }else if($cartItem->ShopItem->payment_plan=='full_payment'){
                        $totalAmount += $cartItem->ShopItem->price;
                    }                      

                    // $totalAmount += $cartItem->ShopItem->price;
                    $item=ShopItem::find($cartItem->ShopItem->id);
                    $item->quantity= $item->quantity > 0 ? $item->quantity -1 : 0;
                    if($item->quantity == 0){
                        $item->status= "not_available";
                    }
                    $item->save();
                }
            }

            //--------ITEMS PAYMENT-----------
            foreach ($cartItems as $cartItem) {
                if ($cartItem->ShopItem) {
                    $type='school_shop_funds';
                    if($cartItem->ShopItem->payment_plan=='installments'){
                        $ItemAmount = $cartItem->ShopItem->payment->amount_per_installment;
                    }else if($cartItem->ShopItem->payment_plan=='installments_and_deposit'){
                        $ItemAmount = $cartItem->ShopItem->payment->initial_deposit_installments;
                    }else if($cartItem->ShopItem->payment_plan=='full_payment'){
                        $ItemAmount = $cartItem->ShopItem->price;
                    }
                    // $ItemAmount = $cartItem->ShopItem->price;
                    if($request->type=='card'){
                        $this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type);
                    }else if($request->type=='wallet'){
                        $user_wallet=Wallet::where('user_id',$user->id)->first();
                        if($user_wallet->ballance >  $ItemAmount){
                            $user_wallet->ballance = $user_wallet->ballance - $ItemAmount;
                            $user_wallet->save();

                            $history=new TransactionHistory();
                            $history->user_id=$user->id;
                            $history->amount=$ItemAmount;
                            $history->type=$type;
                            $history->save();
                        }else{
                            $response = ['Not enough balance'];
                            return response()->json($response, 500);
                        }
                    }
                    // else if($request->type=='wallet_and_card'){
                    //     $user_wallet=Wallet::where('user_id',$user->id)->first();
                    //     if($user_wallet->ballance >  $ItemAmount){
                    //         $user_wallet->ballance = $user_wallet->ballance - $ItemAmount;
                    //         $user_wallet->save();

                    //         $history=new TransactionHistory();
                    //         $history->user_id=$user->id;
                    //         $history->amount=$ItemAmount;
                    //         $history->type=$type;
                    //         $history->save();
                    //     }else{
                    //         $this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type); 
                    //     }
                    // }
                    $purchase=$this->saveMyPurchases($cartItem->ShopItem,$ItemAmount,$request->payment_method);
                    $this->saveMyInstallments($cartItem->ShopItem,$purchase->id);
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

    //--------------ADD SEBSEQUENT INSTALLMENTS------------
    public function saveMyInstallments($shopItem,$purchase_id)
    {
        $user=Auth::user();
        if($shopItem->payment_plan=='installments'){
            $total_installments=$shopItem->payment->total_installments;
            $amount_per_installment=$shopItem->payment->amount_per_installment;
            for ($i = 1; $i < $total_installments; $i++) {
                $myInstallment=new MyInstallments();
                $myInstallment->shop_item_id=$shopItem->id;
                $myInstallment->user_id=$user->id;
                $myInstallment->installment_no=$i+1;
                $myInstallment->installment_amount=$amount_per_installment;
                $myInstallment->purchases_id=$purchase_id;
                $myInstallment->save();
            }
        }else if($shopItem->payment_plan=='installments_and_deposit'){
            $total_installments=$shopItem->payment->total_installments;
            $amount_per_installment=$shopItem->payment->amount_per_installment;
            for ($i = 0; $i < $total_installments; $i++) {
                $myInstallment=new MyInstallments();
                $myInstallment->shop_item_id=$shopItem->id;
                $myInstallment->user_id=$user->id;
                $myInstallment->installment_no=$i+1;
                $myInstallment->installment_amount=$amount_per_installment;
                $myInstallment->purchases_id=$purchase_id;
                $myInstallment->save();
            }
        }
    }

    //--------------ADD TO MY PURCHASES------------
    public function saveMyPurchases($shopItem,$amountPaid,$payment_method)
    {
        $user=Auth::user();
        $myPurchase = new MyPurchase();
        $myPurchase->user_id = $user->id;
        $myPurchase->shop_item_id = $shopItem->id;
        $myPurchase->total_price = $shopItem->price;
        $myPurchase->amount_paid = $amountPaid;
        $myPurchase->payment_card = $payment_method;
        if($shopItem->price > $amountPaid){
            $myPurchase->payment_status = "partially_paid";
        }else if($shopItem->price == $amountPaid){
            $myPurchase->payment_status = "fully_paid";
        }
        $myPurchase->save();
        return $myPurchase;
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

    //--------------GET MY INSTALLMENTS-------------
    public function getMyInstallments(Request $request)
    {
        $user=Auth::user();
        if($user->role=='super_admin'){
            $myInstallments=MyInstallments::where('payment_status','pending')->orderBy('created_at','desc')->paginate($request->entries_per_page);  
        }else if($user->role=='organization_admin'){
            $myInstallments=MyInstallments::where('payment_status','pending')->orderBy('created_at','desc')->paginate($request->entries_per_page);  
            // $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            // $shop=OrganizationShop::where('organization_id',$admin->organization_id)->first();
            // $myInstallments=MyInstallments::where('user_id',$user->id)->orderBy('created_at','desc')->get();
        }else{
            $myInstallments=MyInstallments::where('payment_status','pending')
            ->where('user_id',$user->id)->orderBy('created_at','desc')->paginate($request->entries_per_page);
        }
        $myInstallments = MyInstallmentsRescource::collection($myInstallments);

        $pagination = [
        'current_page' => $myInstallments->currentPage(),
        'last_page' => $myInstallments->lastPage(),
        'per_page' => $myInstallments->perPage(),
        'total' => $myInstallments->total(),
        ];
        $response['data']=$myInstallments;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }

    public function filterInstallments(Request $request){
        if($request->type=='Item Name'){
            $purchases=MyInstallments::with('shopItems')
            ->whereHas('shopItems', function($query) use ($request) {
                $query->where('name', 'like', '%' . $request->value . '%');
            })->get();
        }else if($request->type=='Buyer'){
            $purchases=MyInstallments::with('user')
            ->whereHas('user', function($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->value . '%')
                ->orWhere('last_name', 'like', '%' . $request->value . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
            })->get();
        }
        return response()->json($purchases, 200);
    }

    //-------------PAY INSTALLMENT--------------
    public function payInstallment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' =>'required',
            'type' =>'required',
            'installment_id' =>'required',
        ]);
        $user=Auth::user();
        $installment=MyInstallments::find($request->installment_id);

        $type='school_shop_funds';
        $ItemAmount = $installment->installment_amount;

        if($request->type=='card'){
            $this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type);
        }else if($request->type=='wallet_and_card'){
            $user_wallet=Wallet::where('user_id',$user->id)->first();
            if($user_wallet->ballance >  $ItemAmount){
                $user_wallet->ballance = $user_wallet->ballance - $ItemAmount;
                $user_wallet->save();

                $history=new TransactionHistory();
                $history->user_id=$user->id;
                $history->amount=$ItemAmount;
                $history->type=$type;
                $history->save();
            }else{
                $this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type); 
            }
        }

        $installment->payment_status='paid';
        $installment->save();

        $purchase=MyPurchase::find($installment->purchases_id);
        $netpayment=$purchase->amount_paid + $ItemAmount;
        if($netpayment == $purchase->total_price){
            $purchase->payment_status = "fully_paid"; 
        }
        $purchase->amount_paid = $purchase->amount_paid + $ItemAmount;
        $purchase->save();
    }
}
