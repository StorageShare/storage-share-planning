<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        if (! str($googleUser->getEmail())->endsWith('@storage-share.nl')) {
            return redirect()->route('login')->with('error', 'Inloggen is alleen toegestaan met een @storage-share.nl account.');
        }

        $user = User::firstOrNew(['email' => $googleUser->getEmail()]);

        $user->fill([
            'name' => $user->name ?? $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);

        return redirect()->intended('/');
    }
}
