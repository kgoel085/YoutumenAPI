<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class JWTController extends Controller
{

    private $reqVars;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        if($request) $this->reqVars = $request;
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

    /**
     * Creates the JWT token based on received user
     * 
     * @param App\User
     * @return string \Firebase\JWT\JWT
     */
    protected function jwtToken(User $user){
        $payload = [
            'iss' => env('APP_NAME'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + 10*60
        ];
        return JWT::encode($payload, env('JWT_SECRET'));
    }

    /**
     * Validate received credentials and return JWT token
     */
    public function generateToken(){
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
        //Return JWT with user details
        return response()->json(['success' => ['token' => $this->jwtToken($user)]], 200);
    }
}
