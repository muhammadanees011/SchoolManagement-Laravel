<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MicrosoftController extends Controller
{

    public function redirectToProvider()
    {
        return Socialite::driver('microsoft')->stateless()->redirect();
    }

    public function handleProviderCallback(Request $request)
    {
        dd('123 anees');
        // $microsoftUser = Socialite::driver('microsoft')->stateless()->user();
        // $user = $this->findOrCreateUser($microsoftUser);
        // $tokenResult = $user->createToken('Personal Access Token')->accessToken;
        // $token = $tokenResult;
        // $data['access_token'] = $token;
        // $data['user'] =  $user;
        // $school=null;
        
        // if($user->role=='student'){
        //     $student=Student::where('user_id',$user->id)->first();
        //     $school=School::where('id',$student->school_id)->first();
        // }elseif($user->role=='staff'){
        //     $staff=Staff::where('user_id',$user->id)->first();
        //     $school=School::where('id',$staff->school_id)->first();
        // }elseif($user->role=='parent'){
        //     $parent=Parents::where('parent_id',$user->id)->first();
        //     $school=School::where('id',$parent->school_id)->first();
        // }

        // $data["primary_color"]=$school!=null ? $school->primary_color : '#424246';
        // $data["secondary_color"]=$school!=null ? $school->secondary_color : '#424246';
        // $data["logo"]=$school ? $school->logo : null;

        // return response()->json($data);
        // return response()->json(['token' => $token]);
    }

    public function findOrCreateUser($microsoftUser)
    {
        $user = User::where('email', $microsoftUser->email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $microsoftUser->name,
                'email' => $microsoftUser->email,
                'password' => bcrypt(str_random(16)),
            ]);
        }
        return $user;
    }
}
