<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

use Symfony\Component\HttpFoundation\Cookie;

class JWTController extends Controller
{

    private $reqVars;
    private $authUser;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        if($request) $this->reqVars = $request;
        
        //Validate current user
        $this->validateUser();
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(Request $data)
    {
        $this->validate($data, [
            'email' => ['required', 'string', 'email', 'exists:users'],
            'password' => ['required', 'string'],
            'account' => ['required', 'string', 'exists:users']
        ]);
    }

    public function validateUser(){
       $authUSer = $this->reqVars->auth;
       if($authUSer) $this->authUser = $authUSer;

        if(!$this->authUser){

            //Validate the params
            $this->validator($this->reqVars);

            //Validate the user
            $user = User::where([['email', '=', $this->reqVars->input('email')], ['account', '=', $this->reqVars->input('account')]])->first();
            if(!$user){
                return response()->json(['error' => 'Invalid login provided.'], 401);
            }

            //validate the password
            if (!Hash::check($this->reqVars->input('password'), $user->password)) {
                return response()->json(['error' => 'Invalid password provided'], 401);
            }

            $this->authUser = $user;
        }
    }

    /**
     * Creates the JWT token based on received user
     * 
     * @param App\User
     * @return string \Firebase\JWT\JWT
     */
    public function jwtToken($arr = array()){
        $payload = [
            'iss' => Hash::make(env('APP_NAME')),
            'sub' => $this->authUser->id,
            'iat' => time(),
            'exp' => time() + env('JWT_EXPIRY', 10)*60
        ];

        if(count($arr) > 0) $payload = array_merge($arr, $payload);

        return JWT::encode($payload, env('JWT_SECRET'));
    }

    /**
     * Validate received credentials and return JWT token
     */
    public function generateToken($tokenExtras = array()){
        $newToken = $this->jwtToken($tokenExtras);
        $response = response()->json(['success' => ['token' => $newToken]], 200);

        if(env('JWT_COOKIE') == true && env('JWT_COOKIE_NAME', null)){
            $cookieName = env('JWT_COOKIE_NAME');
            $response = $response->withCookie(new Cookie($cookieName, $newToken, env('JWT_EXPIRY')));
        }

        return $response;
    }

    
}
