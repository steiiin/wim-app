<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class LoginController extends Controller
{

    public function index()
    {
        return Inertia::render('Auth/Login', [
        ]);
    }

    public function authenticate(Request $request)
    {

        $request->validate([
            'passphrase' => 'required|string',
        ],
        [
            'passphrase.required' => 'Du musst eine Passphrase eingeben.',
        ]);

        $passphrase = $request->input('passphrase');
        $env_passphrase = env('ADMIN_PASSPHRASE');
        Log::debug('Login-Attempt: got:'.$passphrase.', env:'.$env_passphrase);

        if ($passphrase === $env_passphrase) {
            $request->session()->put('authenticated', true);
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'passphrase' => 'Die Passphrase ist falsch.',
        ]);

    }

    public function logout(Request $request)
    {
        $request->session()->forget('authenticated');
        return redirect('/login');
    }

}
