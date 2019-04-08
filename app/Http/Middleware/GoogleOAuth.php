<?php

namespace App\Http\Middleware;

use Closure;

use Exception;
Use Firebase\JWT\JWT;

use App\UserGoogleToken;

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

        //Check if user authorized token exists or not
        $userTokenObj = UserGoogleToken::where([['token', '=', $request->jwt->authToken], ['user_id', '=', $request->jwt->sub]])->first();
        if(!$userTokenObj){
            return response()->json([
                'error' => 'Invalid / Unauthorization token provided'
            ], 400);
        }

        if(!array_key_exists('google_token', $request->jwt)){
            $gAuthArr = json_decode($userTokenObj->g_auth_token, true);
            if(!array_key_exists('access_token', $gAuthArr)){
                return response()->json([
                    'error' => 'Invalid / Unauthorization token provided'
                ], 400);
            }
    
            $accessToken = $gAuthArr['access_token'];
            if($accessToken) $request->jwt->google_token = $accessToken;
        }
        

        return $next($request);
    }
}
