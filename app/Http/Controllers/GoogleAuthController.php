<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\GoogleAuthTrait;

class GoogleAuthController extends Controller
{
    use GoogleAuthTrait;

    public function __construct()
    {
        $this->middleware('google.auth');
    }

    public function getSubscriptions(Request $request){
        dd($request->jwt);
    }
}
