<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogLastUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Ak chceš aktualizovať iba každých X minút, pridaj podmienku
            if ($user->last_activity === null || now()->diffInMinutes($user->last_activity) >= 1) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['last_activity' => Carbon::now()]);
            }
        }

        return $next($request);
    }
}
