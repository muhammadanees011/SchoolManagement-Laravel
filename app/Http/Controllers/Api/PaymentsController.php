<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\UserCard;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Illuminate\Support\Str;
use Stripe\Stripe as StripeGateway;

class PaymentsController extends Controller
{
    protected $stripe;

    public function __construct(){
        $this->stripe = Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function getExternalAccounts(){
        $account = \Stripe\Account::retrieve(Auth::user()->stripe_id,['expand' => 'person']);
        return $account;
    }

    public function setupPaymentInformation(Request $request){
        $user=Auth::user();
        if($user->stripe_id!=null){
            return response()->json('Stripe Already Setup', 201);
        }
        try {
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $row =  [
                'type' => 'custom',
                'country' => @$user->country ?? "GB",
                'email' => $user->email,
                'business_type' => 'individual',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],

                'tos_acceptance' => [
                    "date" => now()->timestamp,
                    "ip" => $request->ip(),
                    "user_agent" => $request->userAgent(),
                    "service_agreement" => 'full'
                ],
            ];
            $row['individual'] = [
                'first_name' => $user->first_name ?? "",
                'last_name' => $user->last_name ?? "",
                'email' => $user->email,
                'phone' => $user->phone,
            ];
            $stripeAccount = $stripe->accounts->create($row);
            $user = Auth::user();
            $user->stripe_id = $stripeAccount->id;
            $user->save();
            $response = [
                "data" => $stripeAccount
            ];
            return response()->json($response, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addExternalAccount(Request $request){
        $validator = Validator::make($request->all(), [
            'accountHolderName' => "required",
            'accountHolderType' => "required",
            'accountNumber' => "required",
            'sortCode' => "required|min:6|max:6"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'errors' => $validator->getMessageBag()
            ], 422);
        }

        $input = $validator->validated();

        try {
            $connectedAccountId = Auth::user()->stripe_id;
            // Create a Token for the bank account
            $token = \Stripe\Token::create([
                'bank_account' => [
                    'country' => "GB",
                    'currency' => "GBP",
                    'account_holder_name' => $input['accountHolderName'],
                    'account_holder_type' => $input['accountHolderType'],
                    'account_number' => $input['accountNumber'],
                    'routing_number' => $input['sortCode']
                ],
            ]);

            $account = \Stripe\Account::createExternalAccount($connectedAccountId, [
                'external_account' => $token->id
            ]);

            $row = [
                'id' => $account['id'],
                'accountHolderName' => $account['account_holder_name'],
                'accountHolderType' => $account['account_holder_type'],
                'country' => $account['country'],
                'currency' => $account['currency'],
                'last4' => $account['last4'],
                'status' => $account['status'],
            ];

            return response()->json([
                'success' => true,
                'message' => 'External Account Created Successfully',
                'account' => $row,
                'requirements' => $this->getRequirements($account)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getRequirements($account){

        $accountRequirements  = @$account['requirements']['past_due'] ?? [];
        $requirementsList = [];

        if(in_array('external_account', $accountRequirements)){
            $requirementsList[] = 'External Account data is missing. Please Enter your external account detail!';
            $accountRequirements = array_filter($accountRequirements, function($item){
                return ($item != "external_account");
            });
        }

        if(count($accountRequirements) > 0){
            $requirementsList[] = 'Additional Account Information is missing. Please Enter the Additional Account Detail!';
        }

        return $requirementsList;
    }

    public function initiatePayment(Request $request)
    {
        StripeGateway::setApiKey('sk_test_51OJgsAImkTfQfjIkXRigEFa5X5TBjVHBBPipT8r0gJlGpRFEjpBcwqSy0verJRGpuoIMGkaeikJuexvp5xsrgJF2002CxaXt3s');
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100,
                'currency' => $request->currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            // Save the $paymentIntent->id to identify this payment later
        } catch (Exception $e) {
            // throw error
        }
        return [
            'token' => (string) Str::uuid(),
            'client_secret' => $paymentIntent->client_secret
        ];
    }

    public function completePayment(Request $request)
    {
        $stripe = new StripeClient('sk_test_51OJgsAImkTfQfjIkXRigEFa5X5TBjVHBBPipT8r0gJlGpRFEjpBcwqSy0verJRGpuoIMGkaeikJuexvp5xsrgJF2002CxaXt3s');
        // Use the payment intent ID stored when initiating payment
        $paymentDetail = $stripe->paymentIntents->retrieve('PAYMENT_INTENT_ID');

        if ($paymentDetail->status != 'succeeded') {
            // throw error
        }
        // Complete the payment
    }

    public function failPayment(Request $request)
    {
        // Log the failed payment if you wish
    }

    public function testPayment(Request $request){
        Stripe::setApiKey('sk_test_51OJgsAImkTfQfjIkXRigEFa5X5TBjVHBBPipT8r0gJlGpRFEjpBcwqSy0verJRGpuoIMGkaeikJuexvp5xsrgJF2002CxaXt3s');

        $intent = PaymentIntent::create([
            'amount' => 100,
            'currency' => 'usd',
            // Add more parameters as needed
        ]);

        return response()->json(['client_secret' => $intent->client_secret]);
    }
    //-----------ADD PAYMENT CARD------------
    public function addPaymentCard(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'card_number' => 'required|numeric',
            'cardholder_name' => 'required|string',
            'card_expiry_month'=>'required|numeric',
            'card_expiry_year' => 'required|numeric|digits:4',
            'card_ccv' => 'required|numeric'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $userCard = new UserCard();
            $userCard->user_id=$request->user_id;
            $userCard->card_number=$request->card_number;
            $userCard->cardholder_name=$request->cardholder_name;
            $userCard->card_expiry_month=$request->card_expiry_month;
            $userCard->card_expiry_year=$request->card_expiry_year;
            $userCard->card_ccv=$request->card_ccv;
            $userCard->save();
            DB::commit();
            $response = ['Successfully Added Card'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return $this->sendError($exception->getMessage(), null);
            }
        }
    }
    public function setupPaymentAccount(Request $request){

    }
    //-----------GET USER CARDS--------------
    public function getUserCards($id){
        $userCards=UserCard::where('user_id',$id)->get();
        return response()->json($userCards, 200);
    }
    //-----------DELETE CARD BY ID--------------
    public function removeCardById($id){
        $userCards=UserCard::find($id);
        $userCards->delete();
        $response = ['Successfully Removed The Card'];
        return response()->json($response, 200);
    }
}
