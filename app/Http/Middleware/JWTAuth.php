<?php

namespace App\Http\Middleware;

use Closure;

use Exception;
use App\User;
Use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;

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
