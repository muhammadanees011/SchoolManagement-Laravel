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
use App\Models\Student;
use App\Models\UserCard;
use Illuminate\Support\Str;
use App\Models\TransactionHistory;
use App\Http\Resources\MyInstallmentsRescource;
use App\Http\Resources\AdminInstallmentsResource;
use App\Models\TripParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Stripe\Stripe as StripeGateway;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Exception\CardException;
use Illuminate\Support\Facades\Validator;
use PDF;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;
use Carbon\Carbon;
use App\Services\MicrosoftGraphService;

class UserCartController extends Controller
{
    protected $graphService;

    public function __construct(MicrosoftGraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    //-------------ADD ITEM TO CART------------------
    public function addItemToCart(Request $request)
    {
        try {
            DB::beginTransaction();
            $user=Auth::user();

            if($request->installment_id){
                $cartItem=UserCart::where('user_id',$user->id)->where('installment_id',$request->installment_id)->first();
                if($cartItem){
                    $response = ['Installment Is Already In The Cart'];
                    return response()->json($response, 409);
                }else{
                    $cartItem=new UserCart();
                    $cartItem->user_id=$user->id;
                    $cartItem->installment_id=$request->installment_id;
                }

            }else{
                $cartItem=new UserCart();
                $cartItem->user_id=$user->id;
                $cartItem->shop_item_id=$request->shop_item_id;
            }
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

    //-------------GET CART ITEMS------------------
    public function getUserCartItems(){
        try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem.payment','Installment.shopItems')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
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

    //-------------COUNT CART ITEMS------------------
    public function countUserCartItems(){
        try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem.payment','Trip')->where('user_id',$user->id)->count();
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
            'latest_charge' =>'nullable',
            'last_4' =>'nullable',
            'brand' =>'nullable',
            'cardholder_name' =>'nullable',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        // try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem.payment','Installment')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
            if($cartItems->isEmpty()){
                $response = ['Cart is empty'];
                return response()->json($response, 422);
            }
            $totalAmount=0;
            $items=[];
            $latest_charge=null;
            $product_owners=[];

            foreach ($cartItems as $cartItem) {
                if ($cartItem->ShopItem) {
                    if($cartItem->ShopItem->payment_plan=='installments'){
                      $totalAmount += $cartItem->ShopItem->payment->amount_per_installment;
                      $price = $cartItem->ShopItem->payment->amount_per_installment;
                    }else if($cartItem->ShopItem->payment_plan=='installments_and_deposit'){
                        $totalAmount += $cartItem->ShopItem->payment->initial_deposit;
                        $price = $cartItem->ShopItem->payment->initial_deposit;
                    }else if($cartItem->ShopItem->payment_plan=='full_payment'){
                        $totalAmount += $cartItem->ShopItem->price;
                        $price = $cartItem->ShopItem->price;
                    }                      

                    // $totalAmount += $cartItem->ShopItem->price;
                    $item=ShopItem::find($cartItem->shop_item_id);
                    $item->quantity= $item->quantity !== null ? $item->quantity -1 : null;
                    $item->quantity_sold= $item->quantity_sold >= 0 ? $item->quantity_sold +1 : 1;
                    if($item->quantity !==null && $item->quantity == 0){
                        $item->status= "not_available";
                    }
                    $item->save();

                    $items[] = [
                        'quantity' => 1,
                        'description' => $cartItem->ShopItem->name,
                        'price' =>  number_format($price, 2, '.', ''), 
                    ];
                    if($cartItem->ShopItem->product_owner_email){
                        $product_owners[]= $cartItem->ShopItem->product_owner_email;
                    }
            
                }
            }

            //--------ITEMS PAYMENT-----------
            foreach ($cartItems as $cartItem) {
                if ($cartItem->ShopItem || $cartItem->Installment) {
                    $type='school_shop_funds';

                    if($cartItem->Installment){ //for installments
                        $ItemAmount = $cartItem->Installment->installment_amount;
                    }else //for shop items
                    {
                        if($cartItem->ShopItem->payment_plan=='installments'){
                            $ItemAmount = $cartItem->ShopItem->payment->amount_per_installment;
                        }else if($cartItem->ShopItem->payment_plan=='installments_and_deposit'){
                            $ItemAmount = $cartItem->ShopItem->payment->initial_deposit;
                        }else if($cartItem->ShopItem->payment_plan=='full_payment'){
                            $ItemAmount = $cartItem->ShopItem->price;
                        }
                    }

                    if($request->type=='card'){
                        $res=$this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type);
                        $latest_charge=$res->latest_charge;
                    }else if($request->type=='google_apple_pay'){
                        $latest_charge=$request->latest_charge;
                        $history=new TransactionHistory();
                        $history->user_id=$user->id;
                        $history->amount=$ItemAmount;
                        $history->type=$type;
                        $history->charge_id=$request->latest_charge;
                        $history->last_4=$request->last_4;
                        $history->card_brand=$request->brand;
                        $history->card_holder_name=$request->cardholder_name;
                        $history->save();
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
                            $response ='Not enough balance';
                            return response()->json($response, 500);
                        }
                    }
                    if($cartItem->ShopItem){
                        $purchase=$this->saveMyPurchases($cartItem->ShopItem,$ItemAmount,$request->payment_method,$latest_charge);
                        $this->saveMyInstallments($cartItem->ShopItem,$purchase->id);
                    }else{
                        $installment=MyInstallments::find($cartItem->Installment->id);
                        $installment->payment_status='paid';
                        $installment->save();

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
            }
            
            //--------REMOVE ITEMS FROM CART-----------
            $deletedRows = UserCart::where('user_id', $user->id)->delete();
            $customer=Auth::user();
            if($customer->role=='student'){
                $student=Student::where('user_id',$customer->id)->first();
                $student_id=$student->student_id;
            }else{
                $student_id=null;  
            }  

            $data['student_id']=$student_id;
            $data['customer_email']=$customer->email;
            $data['items']=$items;
            $data['customer_name']=$customer->first_name.' '.$customer->last_name;
            $data['customer_mifare']=null;
            $data['total']= number_format($totalAmount, 2, '.', '');
            $data['invoice_id'] = mt_rand(100000000, 999999999);

            $status=$this->sendReceiptForProductOwner($data,$product_owners);
            $this->sendReceipt($data);
            $response=['Checkout Successfull'];
            $status=200;
            return response()->json($response, $status);
        // } catch (\Exception $exception) {
        //     DB::rollback();
        //     if (('APP_ENV') == 'local') {
        //         dd($exception);
        //     } else {
        //         return response()->json('Something went wrong', 500);
        //     }
        // }
    }

    public function sendReceipt($data){
        try{
            $pdf = PDF::loadView('receipts.PurchaseReceipt', compact('data'));
            $to = [$data['customer_email']];
            $subject = 'Your Receipt From Education Training Collective (ETC)';
            $bodyView = 'emails.message';
            $attachmentContent = $pdf->output();
            $attachmentName = 'Receipt.pdf';
            $status = $this->graphService->sendEmail($to, $subject, $bodyView, $attachmentContent, $attachmentName, $data);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json('Something went wrong', 500);
            }
        }
    }

    public function sendReceiptForProductOwner($data, $product_owners){
        try{
            $pdf = PDF::loadView('receipts.PurchaseReceipt', compact('data'));
            $to = $product_owners;
            $subject = 'Receipt for Product Purchase Education Training Collective (ETC)';
            $bodyView = 'emails.ProductOwnerMessage';
            $attachmentContent = $pdf->output();
            $attachmentName = 'Receipt.pdf';
            $status = $this->graphService->sendEmail($to, $subject, $bodyView, $attachmentContent, $attachmentName,$data);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json('Something went wrong', 500);
            }
        }
    }

    //--------------ADD SEBSEQUENT INSTALLMENTS------------
    public function saveMyInstallments($shopItem,$purchase_id)
    {
        try {
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
        } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json($exception, 500);
            }
        }
    }

    //--------------ADD TO MY PURCHASES------------
    public function saveMyPurchases($shopItem,$amountPaid,$payment_method, $latest_charge)
    {
        try {
            $user=Auth::user();
            $myPurchase = new MyPurchase();
            $myPurchase->user_id = $user->id;
            $myPurchase->shop_item_id = $shopItem->id;
            $myPurchase->total_price = $shopItem->price;
            $myPurchase->amount_paid = $amountPaid;
            $myPurchase->payment_card = $payment_method ? $payment_method : null ;
            $myPurchase->latest_charge = $latest_charge ? $latest_charge : null ;
            if($shopItem->price > $amountPaid){
                $myPurchase->payment_status = "partially_paid";
            }else if($shopItem->price == $amountPaid){
                $myPurchase->payment_status = "fully_paid";
            }
            $myPurchase->save();
            return $myPurchase;
        } catch (\Exception $exception) {
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
                "on_behalf_of"=> "acct_1Q8m46PPnkrk4pSx",
                'transfer_data' => [
                    'destination' => "acct_1Q8m46PPnkrk4pSx",
                ],
                'return_url' => 'https://your-website.com/thank-you',
                'automatic_payment_methods' => ['enabled' => true],
            ]);
            // acct_1NlWiGGYrt7SylQr
            // ['stripe_account' => 'acct_1Q8m46PPnkrk4pSx']
            $user=Auth::user();
            $user_card=UserCard::where('user_id',$user->id)->first();
            $history=new TransactionHistory();
            $history->user_id=$user_id;
            $history->amount=$amount;
            $history->type=$type;
            $history->charge_id=$paymentIntent->latest_charge;
            $history->last_4=$user_card->last_4;
            $history->card_brand=$user_card->brand;
            $history->card_holder_name=$user_card->cardholder_name;
            $history->save();

            return $paymentIntent;
            
        } catch (\Exception $e) {
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

    //--------------Google Apple PAYMENT-------------
    public function createexpressPaymentIntent(Request $request){
    StripeGateway::setApiKey(env('STRIPE_SECRET'));
    // try {
        $paymentIntent = PaymentIntent::create([
            'amount' => $request->amount, // Amount in cents
            'currency' => $request->currency, // e.g., 'usd'
            'payment_method_types' => ['card'],
        ]);

        return response()->json([
            'clientSecret' => $paymentIntent->client_secret,
        ]);
        // ['stripe_account' => 'acct_1Q8m46PPnkrk4pSx']
            
        // } catch (\Exception $e) {
        //     return response()->json(['error' => $e->getMessage()], 500);
        // }
    }

    //--------------GET MY INSTALLMENTS-------------
    public function getMyInstallments(Request $request)
    {
        $user=Auth::user();
        if($user->role!=='student' && $user->role!=='staff' && $user->role!=='parent'){
            $myInstallments=MyPurchase::has('installments')->with('installments')->orderBy('created_at','desc')->paginate($request->entries_per_page);
            // return $myInstallments;
            $myInstallments = AdminInstallmentsResource::collection($myInstallments);
            // $myInstallments=MyInstallments::where('payment_status','pending')->orderBy('created_at','desc')->paginate($request->entries_per_page);  
        }else{
            $myInstallments=MyInstallments::where('payment_status','pending')
            ->where('user_id',$user->id)->orderBy('created_at','desc')->paginate($request->entries_per_page);
            $myInstallments = MyInstallmentsRescource::collection($myInstallments);
        }

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

    //--------------GET PAID INSTALLMENTS-------------
    public function getPaidInstallments(Request $request)
    {
        $user=Auth::user();
        if($user->role!=='student' && $user->role!=='staff' && $user->role!=='parent'){
            $myInstallments=MyInstallments::where('payment_status','paid')
            ->orderBy('created_at','desc')->paginate($request->entries_per_page);
            $myInstallments = MyInstallmentsRescource::collection($myInstallments);
        }else{
            $myInstallments=MyInstallments::where('payment_status','paid')
            ->where('user_id',$user->id)->orderBy('created_at','desc')->paginate($request->entries_per_page);
            $myInstallments = MyInstallmentsRescource::collection($myInstallments);
        }

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
            $purchases=
                MyInstallmentsRescource::collection(
                    MyInstallments::with('shopItems')->whereHas('shopItems', function($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->value . '%');
                })->where('payment_status',$request->status)->get()
            );
        }else if($request->type=='Buyer'){
            $purchases = MyInstallmentsRescource::collection(
                MyInstallments::with('user')->whereHas('user', function($query) use ($request) {
                $query->where('first_name', 'like', '%' . $request->value . '%')
                ->orWhere('last_name', 'like', '%' . $request->value . '%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $request->value . '%']);
            })->where('payment_status',$request->status)->get()
            );
        }if($request->type=='Date Range'){
            $fromDate = Carbon::createFromFormat('d/m/Y', $request->value['fromDate'])->startOfDay()->format('Y-m-d 00:00:0');
            $toDate = Carbon::createFromFormat('d/m/Y', $request->value['toDate'])->endOfDay()->format('Y-m-d 23:59:59');
            $purchases = MyInstallmentsRescource::collection(
            MyInstallments::whereBetween('created_at', [$fromDate, $toDate])->where('payment_status',$request->status)->get()
            );
        }
        return response()->json($purchases, 200);
    }

    public function getInstallmentDetail(Request $request){
        $installment=MyInstallments::find($request->installment_id);
        return response()->json($installment, 200);
    }
    //-------------PAY INSTALLMENT--------------
    public function payInstallment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' =>'required',
            'type' =>'required',
            'installment_id' =>'required',
            'latest_charge' =>'nullable',
            'last_4' =>'nullable',
            'brand' =>'nullable',
            'cardholder_name' =>'nullable',
            'amount' =>'nullable',

        ]);
        try {
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
                }
                else{
                    return response()->json(['Not Enough balance'], 500);
                    // $this->initiatePayment($user->id,$ItemAmount,$request->payment_method,$type); 
                }
            }else if($request->type=='google_apple_pay'){
                $history=new TransactionHistory();
                $history->user_id=$user->id;
                $history->amount=$request->amount;
                $history->type=$type;
                $history->charge_id=$request->latest_charge;
                $history->last_4=$request->last_4;
                $history->card_brand=$request->brand;
                $history->card_holder_name=$request->cardholder_name;
                $history->save();
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

            $cart=UserCart::where('user_id',$user->id)->where('installment_id',$request->installment_id)->first();
            if($cart){
                $cart->delete();
            }
            return response()->json('Installment Paid Successfully', 200);
        } catch (\Exception $e) {
            // Handle other errors
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getPaymentMethod(Request $request)
    {
        StripeGateway::setApiKey(env('STRIPE_SECRET'));
        try {
            $paymentMethod = PaymentMethod::retrieve($request->paymentMethodId);

            if ($paymentMethod->card) {
                $paymentIntentId = $request->paymentIntentId;
                // Retrieve the payment intent
                $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
                // Retrieve the latest charge from the PaymentIntent
                if (isset($paymentIntent)) {
                    return response()->json([
                        'payment_method' => $paymentMethod,
                        'charge_id' => $paymentIntent->latest_charge,
                    ]);
                } else {
                    return response()->json(['error' => 'No charge found for this payment method'], 404);
                }
            }

            // return response()->json($paymentMethod);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
