<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('mailer', function ($app) { 
            $app->configure('services'); 
            return $app->loadComponent('mail', 'Illuminate\Mail\MailServiceProvider', 'mailer'); 
        });
    }

    public function boot(){
        //To add custom roues -- Example
        // $this->app['router']->group(['prefix' => 'my-module'], function ($router) {
        //     $router->get('my-route', 'MyVendor\MyPackage\MyController@action');
        //     $router->get('my-second-route', 'MyVendor\MyPackage\MyController@otherAction');
        // });
    }
}
