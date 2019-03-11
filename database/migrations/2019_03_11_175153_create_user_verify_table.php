<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserVerifyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_verify', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->tinyInteger('email_sent')->default(0);
            $table->tinyInteger('email_verified')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_verify');
    }
}
