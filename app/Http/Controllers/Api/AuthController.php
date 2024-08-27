<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Student;
use App\Models\School;
use App\Models\Parents;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use App\Mail\ForgotPasswordEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Socialite;
use GuzzleHttp\Client;


class AuthController extends Controller
{
    //------------REGISTER USER--------------
    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $request['password']=Hash::make($request['password']);
        $request['remember_token'] = Str::random(10);
        $user = User::create($request->toArray());
        $response = ['user' => $user];
        return response()->json($response, 200);
    }

    public function handleProviderCallback(Request $request)
    {
        $code = $request->input('code'); // Get the code from the front-end

        if (!$code) {
            return response()->json(['error' => 'Authorization code is missing'], 400);
        }

        try {
            // Step 1: Set up Guzzle HTTP client to make the POST request to Azure AD
            $client = new Client();
            $tenant_id=env('MICROSOFT_TENANT_ID');
            $response = $client->post('https://login.microsoftonline.com/'.$tenant_id.'/oauth2/v2.0/token', [
                'form_params' => [
                    'client_id' => env('MICROSOFT_CLIENT_ID'),
                    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => env('MICROSOFT_REDIRECT_URI'), // This must match the redirect URI in your Azure app
                ],
            ]);

            // Step 2: Parse the token response from Azure AD
            $tokenData = json_decode($response->getBody(), true);

            // Check if token data exists
            if (!isset($tokenData['access_token'])) {
                return response()->json(['error' => 'Failed to retrieve access token'], 400);
            }

            // Step 4: Use the access token to fetch user data from Microsoft Graph API
            $accessToken = $tokenData['access_token'];

            // Step 5: Make a GET request to Microsoft Graph to fetch the user profile
            $userResponse = $client->get('https://graph.microsoft.com/v1.0/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            // Step 6: Parse and return user data
            $userData = json_decode($userResponse->getBody(), true);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to exchange code for token', 'details' => $e->getMessage()], 500);
        }

        $user = $this->findOrCreateUser($userData);
        if(!$user){
            session()->forget('access_token');
            session()->flush();
            $tenant_id = env('MICROSOFT_TENANT_ID');
            // Microsoft logout URL
            $logoutUrl = 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/logout';
            return redirect($logoutUrl);
            return response()->json(['error' => 'The Provided User was not found in the studentpay portal'], 500);
        }

        $tokenResult = $user->createToken('Personal Access Token')->accessToken;
        $token = $tokenResult;
        $data['access_token'] = $token;
        $data['user'] =  $user;
        $school=null;
        
        if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            $school=School::where('id',$student->school_id)->first();
        }elseif($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $school=School::where('id',$staff->school_id)->first();
        }elseif($user->role=='parent'){
            $parent=Parents::where('parent_id',$user->id)->first();
            $school=School::where('id',$parent->school_id)->first();
        }

        $data["primary_color"]=$school!=null ? $school->primary_color : '#424246';
        $data["secondary_color"]=$school!=null ? $school->secondary_color : '#424246';
        $data["logo"]=$school ? $school->logo : null;

        return response()->json($data);
    }

    public function findOrCreateUser($microsoftUser)
    {
        $user = User::where('email', $microsoftUser['userPrincipalName'])->where('status','active')->first();
        // if (!$user) {
        //     $user = User::create([
        //         'name' => $microsoftUser->name,
        //         'email' => $microsoftUser->email,
        //         'password' => bcrypt(str_random(16)),
        //     ]);
        // }
        return $user;
    }

    //------------LOGIN USER--------------
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){ 
            $user = Auth::user(); 
            $tokenResult = $user->createToken('Personal Access Token')->accessToken;
            $token = $tokenResult;
            $data['access_token'] = $token;
            $data['user'] =  $user;
            $school=null;

            if($user->role=='student'){
                $student=Student::where('user_id',$user->id)->first();
                $school=School::where('id',$student->school_id)->first();
            }elseif($user->role=='staff'){
                $staff=Staff::where('user_id',$user->id)->first();
                $school=School::where('id',$staff->school_id)->first();
            }elseif($user->role=='parent'){
                $parent=Parents::where('parent_id',$user->id)->first();
                $school=School::where('id',$parent->school_id)->first();
            }

            $data["primary_color"]=$school!=null ? $school->primary_color : '#424246';
            $data["secondary_color"]=$school!=null ? $school->secondary_color : '#424246';
            $data["logo"]=$school ? $school->logo : null;

            return response()->json($data);
        } 
        else{ 
            return response()->json('Unauthorized.',401);
        } 
    }
    //------------LOGOUT USER--------------
    public function logout(Request $request){
        $user = Auth::user();
        if ($user) {
            if ($user->token()->revoke()) {
                return response()->json('Logout successfully!');
            } else {
                return response()->json('Failed To Logout');
            }
        }
        return response()->json('User not found.', 201);
    }
    //--------------SEND OTP---------------
    public function send_forgot_password_otp(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        $otp = mt_rand(1000, 9999);
        $user = User::where('email', $request->email)->first();
        $user->otp = $otp;
        $user->save();
        //Send OTP to Email
        try {
            $mailData = [
            'title' => 'Reset Your Password',
            'body' => $otp,
            'user_name'=> $user->first_name,
            ];
            Mail::to($request->email)->send(new ForgotPasswordEmail($mailData));
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }
        return response()->json('Forgot Password OTP sent successfully', 200);
    }
    //--------------VERIFY OTP---------------
    public function forgot_password_verify_otp(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
            'otp' => 'required|exists:users,otp',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }

        $user = User::where('email', $request->email)->where('otp', $request->otp)->first();
        if ($user) {
            return response()->json('Code verified successfully.', 200);
        } else {
            return response()->json('Code verified successfully.', 201);
        }
    }
    //--------------CHANGE PASSWORD---------------
    public function changePassword(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        $user = User::where('id', $request->user_id)->first();
        if($request->new_password==$request->old_password){ 
            return response()->json('New password can not be same as old password.',422);
        }else if(Hash::check($request->old_password, $user->password)){ 
            $user->password = Hash::make($request->new_password);
            $user->save();
            return response()->json('Password changed successfully.', 200);     
        }else{ 
            return response()->json('Inccorect Old Password.',422);
        } 
    }
    //--------------SET PASSWORD---------------
    public function set_new_password(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
            'otp' => 'required|exists:users,otp',            
            'new_password' => 'required|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->new_password);
        $user->otp=null;
        $user->save();
        return response()->json('Password changed successfully.', 200);
    }
    //-------------VERIFY EMAIL---------------
    public function verify_email(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        $user = User::where('email', $request->email)->where('otp', $request->otp)->first();
        if ($user) {
            $user->email_verified_at = Carbon::now();
            $user->otp = NULL;
            $user->save();
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->save();
            $data['access_token'] = $tokenResult->accessToken;
            $data['token_type'] = 'Bearer';
            $data['expires_at'] = Carbon::parse($tokenResult->token->expires_at)->toDateTimeString();
            $data['user'] = $user;
            return response()->json('Email Verified Successfully', 200);
        } else {
            return response()->json('Invalid code. Check your email and try again', 201);
        }
    }
    //-------------RESEND EMAIL VERIFICATION OTP---------------
    public function resend_email_verification_otp(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        $otp = mt_rand(1000, 9999);
        $user = User::where('email', $request->email)->first();
        $user->otp = $otp;
        $user->save();
        $user_name=$user->name;
        // try {
        //     Mail::to($request->email)->send(new EmailVerification($user->name, $otp));
        // } catch (\Exception $e) {
        //     return $this->sendError($e->getMessage(), null);
        // }
        return response()->json('Email Verification OTP sent Successfully.',200);
    }
    //-------------PROFILE SETTINGS-----------
    public function profileSettings(Request $request){
        $user_id=Auth::user();
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user_id->id,
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        try {
        DB::beginTransaction();
        $user=User::where('id',$user_id->id)->first();
        $created_at=$user->created_at;
        $updated_at=$user->updated_at;
        $user->first_name=$request->first_name;
        $user->last_name=$request->last_name;
        $user->email=$request->email;
        $user->created_at=$created_at;
        $user->updated_at=$updated_at;
        $user->save();
        DB::commit();
        return response()->json('Successfully updated user settings',200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
            return response()->json($exception, 500);
            }
        }
    }
}
