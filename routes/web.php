<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

/**
 * Below routes handles the user verification and reistration
 */
$router->post('/register', [
    'as' => 'register.user', 'uses' => 'VerifyUserController@register'
]);
$router->get('/verify/{token}', [
    'as' => 'verify.user', 'uses' => 'VerifyUserController@verifyToken'
]);

//Below route will be used to generate a JWT token
$router->post('/generateToken', [
    'as' => 'generate.token', 'uses' => 'JWTController@generateToken'
]);

/**
 * Below routes require JWT token in order to pass
 */
$router->group(['middleware' => 'jwt.auth', 'prefix' => 'api_v1'], function () use ($router) {
    $router->get('/{action}', 'EndPointController@getAction');
});

// $router->get('/', function () use ($router) {
//     return $router->app->version();
// });