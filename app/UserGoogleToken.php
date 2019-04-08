<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserGoogleToken extends Model
{

    protected $table = "google_auth_tokens";
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'token', 'g_auth_token'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];
}