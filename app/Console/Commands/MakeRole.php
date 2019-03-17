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
    protected $signature = "make:role {rolename?}";

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

        //Check if user has provided any role to create or not
        $roleName = $this->argument('rolename');
        
        //Ask user to proivde one
        if(!$roleName){
            if($this->confirm('No role name provided. Do you want to continue ?')){
                $roleName = $this->ask('Please provide role name to create');
            }
        }

        //If user provided us something only then continue
        if($roleName) $this->createRole($roleName);
    }

    protected function checkInitialInstall(){
        $fileContents = false;

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

        $this->info('Initial check complete.');
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

    protected function createRole($userRole = null){
        $chooseOpt = $roleSlug = null;
        $roleExists = false;

        //Check whether same role exists in DB or not
        $userExist = Role::where('slug', $userRole)->orWhere('name', $userRole)->first();
        if($userExist){
            $this->info('Provided role already exist in the DB with slug/name : '.$userExist->slug.' / '.$userExist->name);
            $roleExists = true;
        }

        if($roleExists) return false;

        //Ask user for role slug
        $roleSlug = $this->ask('Please enter a slug for '.$userRole.'. ');

        //if($this->confirm('You have selected to create '.$userRole.' role. Continue ? ')){
            $newRole = Role::create([
                'slug' => $roleSlug,
                'name' => $userRole
            ]);

            if($newRole){
                $this->info('Role created: '.$newRole->name);

                //Ask user whether they need to assign any permission to current role
                if($this->confirm('Do you want to assign any permissions for role: '.$newRole->name)){
                    $this->createPermission($newRole);
                }
            }
        //}
    }

    public function createPermission($roleObj){
        //Get all the permissions available in the DB
        $permissionArr = Permission::all();
        if($permissionArr->count() > 0){
            $optArr = $tableArr = array();

            foreach($permissionArr as $permissionOpt){
                if($permissionOpt && $permissionOpt->id){
                  $optArr[$permissionOpt->name] = $permissionOpt->id; 

                  $tableArr[] = [$permissionOpt->id, $permissionOpt->name];
                }
            }

            if(count($optArr) > 0){
                $this->info('Following permissions are available.');
                $this->table(['ID', 'Name'], $tableArr);

                $chooseOpt = $this->ask('Please input the ID\'s with "|" seperator.');
                if(empty($chooseOpt) == false){
                    $permissionIds = array_unique(explode('|', $chooseOpt));
                    
                    if(count($permissionIds) > 0){
                        foreach($permissionIds as $permissionId){
                            $arrayKey = array_search($permissionId, $optArr);
                            $this->info('Adding permission "'.$arrayKey.'": ');

                            $permissionObj = null;
                            $permissionObj = Permission::where('id', $permissionId)->first();

                            if($permissionObj){
                                $roleObj->permissions()->attach($permissionObj);
                            }
                        }
                    }
                }

                $this->info('Permissions added successfully.');
            }
        }
    }
}



?>