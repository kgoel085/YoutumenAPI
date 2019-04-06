<?php

namespace App\Http\Middleware;

use Closure;

use Exception;
Use Firebase\JWT\JWT;

class GoogleOAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //Check if google auth is enabled on the server or not
        if(!env('GOOGLE_AUTH_ENABLED') || !class_exists('Google_Client') || !array_key_exists('jwt', get_object_vars($request))){
            return response()->json([
                'error' => 'Service currently unavailable. Please try again later'
            ], 503);
        }

        return $next($request);
    }
}
