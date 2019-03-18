<?php

namespace App\Http\Controllers;

use App\User;
use App\UserVerify;

use App\Role;
use App\Permission;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Notifications\VerifyUserEmailNotification;
use App\Notifications\NewUserAccountNotification;

class VerifyUserController extends Controller
{
    protected $newToken;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->newToken = sha1(time().time()+60*60);
    }

    protected function validator(Request $data)
    {
        $this->validate($data, [
        //return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:user_verify'],
            'password' => ['required', 'string', 'min:8'],
        ]);
    }

    /**
     * Create a new user verification instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\UserVerify
     */
    protected function create(array $data)
    {
        $verifyUser = UserVerify::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'token' => $this->newToken,
            'password' => Hash::make($data['password']),
            
        ]);
        return $verifyUser;
    }

    public function register(Request $request)
    {
        $this->validator($request);
        event($user = $this->create($request->all()));
        //$this->guard()->login($user);

        if(!$user){
            return response()->json([
                'error' => 'Unable to process the request'
            ], 400);
        }

        $user->notify(new VerifyUserEmailNotification($this->newToken));
        
        $user->email_sent = 1;
        $user->save();

        return response()->json([
            'success' => 'Registration successfull. Verification email sent at '.$request->input('email')
        ], 200);
    }

    public function verifyToken(String $token = null){
        $newUserAccount = sha1(time());

        if(!$token){
            return response()->json([
                'error' => 'Token not provided'
            ], 400);
        }

        //Check for user assigned to token
        $userVerify = null;
        $userVerify = UserVerify::where([['token', '=', $token], ['del_status', '=', 0], ['email_sent', '=', 1], ['email_verified' , '=', 0]])->first();
    
        if(!$userVerify){
            return response()->json([
                'error' => 'Invalid token provided.'
            ], 400);
        }

        //Check whether token is valid or not
        $createTime = $endTime = null;
        if($userVerify->created_at) $createTime = strtotime($userVerify->created_at);
        if($createTime) $endTime = strtotime("+15 minutes", $createTime);

        //If expired, Set the current token user as invalid
        if(time() > $endTime){
            $userVerify->del_status = 1;
            $userVerify->save();

            return response()->json([
                'error' => 'Token Expired. Please try again'
            ], 400);
        }

        $newUser = null;
        $newUser = User::create([
            'name' => $userVerify->name,
            'email' => $userVerify->email,
            'password' => $userVerify->password,
            'account' => $newUserAccount,
        ]);

        if(!$newUser){
            return response()->json([
                'error' => 'Unable to verify your request. Please try again'
            ], 400);
        }

        //Set role / permission
        $role = Role::where('slug', 'user')->first();
        $permission = permission::where('slug', 'read')->first();

        $newUser->roles()->attach($role);
        $newUser->permissions()->attach($permission);

        $newUser->notify(new NewUserAccountNotification($newUserAccount));
        $userVerify->email_verified = 1;
        $userVerify->save();

        

        return response()->json([
            'success' => 'User verified successfully'
        ], 200);
    }
}
