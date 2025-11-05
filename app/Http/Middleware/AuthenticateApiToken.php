<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiToken;
use Carbon\Carbon;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = substr($header, 7);
        // tokens are stored hashed (sha256)
        $hashed = hash('sha256', $token);
        $record = ApiToken::where('token', $hashed)->first();
        if (!$record) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        if ($record->expires_at && Carbon::now()->greaterThan($record->expires_at)) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        // attach user info to request for controllers
        $request->merge(['auth_usuario_id' => $record->id_usuario]);
        return $next($request);
    }
}
