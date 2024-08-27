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
