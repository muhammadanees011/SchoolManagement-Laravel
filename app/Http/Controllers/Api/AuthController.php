<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

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
            return response()->json($data);
        } 
        else{ 
            return response()->json('Unauthorised.',401);
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
        // try {
        //     Mail::to($request->email)->send(new ForgotPassword($user->name, $otp));
        // } catch (\Exception $e) {
        //     return $this->sendError($e->getMessage(), null);
        // }
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
    //--------------SET PASSWORD---------------
    public function set_new_password(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
            'new_password' => 'required|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->messages()->first(), 403);
        }
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->new_password);
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

}
