<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only renters approved by an admin may use the rental features.
 * Usage: ->middleware('approved').
 *
 * @author Charlene
 *
 * @version 1.0
 */
class EnsureRenterIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A registration can be rejected while the renter is still logged in, so end the session right away.
        if ($user?->isRejected()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => AuthService::REJECTED_MESSAGE]);
        }

        if (! $user?->isApproved()) {
            abort(403, 'Your account is still awaiting admin verification.');
        }

        return $next($request);
    }
}
