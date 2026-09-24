<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Support\DemoMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Continue as demo": signs into the demo account without its password and
 * goes straight to the assessment. Anyone who can reach the server can use it
 * while demo mode is on, which is why it 404s the rest of the time.
 */
class DemoLoginController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $account = DemoMode::account();
        abort_if($account === null, 404);

        Auth::login($account);
        $request->session()->regenerate();

        return redirect('/assessment/written');
    }
}
