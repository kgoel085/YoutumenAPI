<?php

namespace App\Http\Middleware;

use Closure;

use Exception;
use App\User;
Use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;
use App\UserGoogleToken;

class JWTAuth
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
        $token = null;

        /**
         * Get the token from Bearer as it is the only valid way to send the token
         */
        if($request->header('Authorization')){
            $tmpArr = explode(' ',$request->header('Authorization'));
            if(end($tmpArr)) $token = end($tmpArr);
        }

        if(!$token) {
            // Unauthorized response if token not there
            return response()->json([
                'error' => 'Token not provided.'
            ], 401);
        }

        try {
            $credentials = JWT::decode($token, env('JWT_SECRET'), [env('JWT_ALGO')]);

            //Check the issuer is valid or not
            if(!Hash::check(env('APP_NAME'), $credentials->iss)){
                throw new Exception('Issuer is invalid');
            }

            $credentials->iss = env('APP_NAME');
            
        } catch(ExpiredException $e) {
            return response()->json([
                'error' => 'Provided token is expired.'
            ], 400);
        } catch(Exception $e) {
            return response()->json([
                'error' => 'An error while decoding token. '.$e->getMessage()
            ], 400);
        }

        $user = User::find($credentials->sub);

        //Check for user permission
        if(!$user->can('read')){
            return response()->json([
                'error' => 'Unauthorized access'
            ], 400);
        }
        
        // Now let's put the user in the request class so that you can grab it from there
        if($user){
            $request->auth = $user;

            //Add decoded token details in current request
            $request->jwt = $credentials;
        }


        //Check if google auth is enabled on the server or not
        if(!env('GOOGLE_AUTH_ENABLED') || !class_exists('Google_Client') || !array_key_exists('jwt', get_object_vars($request))){
            return response()->json([
                'error' => 'Service currently unavailable. Please try again later'
            ], 503);
        }

        //Check if google user authorized token exists or not
        if($request->jwt->authToken){
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
        }

        //Check if configuration file exists or not 
        $configFile = str_replace('\\', '/', base_path()).'/config/endpoints.json';
        if(!file_exists($configFile)){
            return response()->json([
                'error' => 'Endpoint configuration does not exists'
            ], 503);
        }

        return $next($request);
    }
}
