<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

        if ($request->input('passphrase') === env('ADMIN_PASSPHRASE')) {
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
