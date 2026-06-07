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
        // Socialite returns a Symfony RedirectResponse; wrap into Laravel's RedirectResponse
        $targetUrl = Socialite::driver('google')->redirect()->getTargetUrl();

        return redirect()->away($targetUrl);
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $allowedDomains = [
            'storage-share.nl',
            'vinjo.onl',
        ];

        if (! in_array(str($googleUser->getEmail())->after('@'), $allowedDomains)) {
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
