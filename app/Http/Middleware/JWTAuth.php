<?php

namespace App\Http\Middleware;

use Closure;

use Exception;
use App\User;
Use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

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
            $credentials = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
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
        $request->auth = $user;

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
