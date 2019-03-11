<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserVerify extends Model
{

    protected $table = "user_verify";
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','token','account'
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
