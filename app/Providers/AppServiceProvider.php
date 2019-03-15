<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Permission;
use Illuminate\Support\Facades\Gate;

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

        //Adding lumen can() mapping to check for whether user can do an action( have permission ) or not
        // Example - $user->can('edit-settings') 
        Permission::get()->map(function($permission){
            Gate::define($permission->slug, function($user) use ($permission){
                return $user->hasPermissionTo($permission);
            });
        });
    }
}
