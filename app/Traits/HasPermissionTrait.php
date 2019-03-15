<?php
    namespace App\Traits;

    use App\Permission;
    use App\Role;

    /**
     * 
     */
    trait HasPermissionTrait
    {
        //Return all the roles assigned to a user
        public function roles() {
            return $this->belongsToMany(Role::class,'users_roles');
        }
    
        //Return all the permissions assigned to a user
        public function permissions() {
            return $this->belongsToMany(Permission::class,'users_permissions');
    
        }

        //Checks whether the provided role [$roles] is assigned to the user or not
        public function hasRole( ... $roles ) {
            foreach ($roles as $role) {
               if ($this->roles->contains('slug', $role)) {
                  return true;
               }
            }
            return false;
        }

        //Checks whether the provided permission [$permission] is assigned to the user or user role
        protected function hasPermissionTo($permission) {
            return $this->hasPermissionThroughRole($permission) || $this->hasPermission($permission);
        }
        
        //Check whether user has the provided permission or not
        protected function hasPermission($permission) {
            return (bool) $this->permissions->where('slug', $permission->slug)->count();
        }

        //Chek whether user role has the provided permission or not
        public function hasPermissionThroughRole($permission) {
            foreach ($permission->roles as $role){
                if($this->roles->contains($role)) {
                    return true;
                }
            }
            return false;
        }

        //Returns all the permissions assigned to user
        protected function getAllPermissions(array $permissions){
            return Permission::whereIn('slug', $permissions)->get();
        }

        //Assign permissions to the user
        public function givePermissionsTo(... $permissions) {
            $permissions = $this->getAllPermissions($permissions);
            if($permissions === null) {
                return $this;
            }
            $this->permissions()->saveMany($permissions);
            return $this;
        }

        //Detach assigned [$permission]
        public function deletePermissions( ... $permissions ) {
            $permissions = $this->getAllPermissions($permissions);
            $this->permissions()->detach($permissions);
            return $this;
        }
    }
    
?>