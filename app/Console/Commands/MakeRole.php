<?php
namespace App\Console\Commands;

use App\Role;
use App\Permission;

use Exception;
use Illuminate\Console\Command;

Class MakeRole extends Command{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = "make:role {install?}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates role with permission attached to it';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

     /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //Check initial setup
        $this->checkInitialInstall();

    }

    protected function checkInitialInstall(){
        $installArg = $fileContents = false;
        if($this->argument('install')) $installArg = true;

        //Check if any record exists in role / permission file or not 
        $roleCount = Role::all()->keyBy('slug');
        $permissionCount = Permission::all()->keyBy('slug');

        if(empty($roleCount->count()) || empty($permissionCount->count())){
            if ($this->confirm('Initial setup not done. Do you want to do it right now ?')) {
                $this->bootstrapRoles();
            }else{
                $this->info('Please run the initial setup by running ==> make:role install ');
            }
        }
    }

    protected function bootstrapRoles(){
        $roleCount = Role::all()->keyBy('slug');
        $permissionCount = Permission::all()->keyBy('slug');

        //Load configuration file
        //if(empty($roleCount->count()) || empty($permissionCount->count())){
            $configFile = str_replace('\\', '/', base_path()).'/config/rolePermissions.json';
            if(file_exists($configFile)) $fileContents = json_decode(file_get_contents($configFile), true);

            if($fileContents){
                //Setting up permissions
                if($permissionCount->count() < count($fileContents['Permissions'])){
                    $this->info('Default permissions not available. Setting them up..');

                    foreach($fileContents['Permissions'] as $role){
                        //Check if current role exists in Db 
                        $dbRole = $permissionCount->get($role['slug']);

                        if(!$dbRole){
                            $newRole = null;
                            $newRole = Permission::create([
                                'slug' => $role['slug'],
                                'name' => $role['name']
                            ]);

                            if($newRole){
                                $this->info('Permission added: '.$newRole->slug);
                            }
                        }
                    }
                }

                //Setting up roles
                if($roleCount->count() < count($fileContents['Roles'])){
                    $this->info('Default roles not available. Setting them up..');

                    foreach($fileContents['Roles'] as $role){
                        //Check if current role exists in Db 
                        $dbRole = $roleCount->get($role['slug']);

                        if(!$dbRole){
                            $newRole = null;
                            $newRole = Role::create([
                                'slug' => $role['slug'],
                                'name' => $role['name']
                            ]);

                            if($newRole){
                               $this->info('Role added: '.$newRole->slug);
                               $dbRole = $newRole;
                            }
                        }

                        if($dbRole){
                            if($role['permissions']){
                                foreach($role['permissions'] as $rolePermission){
                                    $availablePermission = $dbRole->permissions();
                                    if($availablePermission){
                                        foreach($availablePermission as $checkPermission){
                                            if($checkPermission && $checkPermission->name == $rolePermission){
                                                continue;
                                            }else{
                                                $currentPermission = Permission::where('slug', '=', $rolePermission)->first();
                                                if($currentPermission){
                                                    $dbRole->permissions()->attach($currentPermission);
                                                    $this->info('Added "'.$rolePermission.'" permission for '.$dbRole->name);
                                                }
                                            }
                                        }
                                    }else{
                                        $currentPermission = Permission::where('slug', '=', $rolePermission)->first();
                                        if($currentPermission){
                                            $dbRole->permissions()->attach($currentPermission);
                                            $this->info('Added role "'.$rolePermission.'" permission for '.$dbRole->name);
                                        }
                                    }
                                }
                            }
                        }

                        $this->info('-------------'.PHP_EOL);
                    }
                }
            }

            $this->info('Initial bootstrap done.');
        //}
    }
}



?>