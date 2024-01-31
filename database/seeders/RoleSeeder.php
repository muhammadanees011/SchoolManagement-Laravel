<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['guard_name' => 'api','name' => 'Admin']);
        $associateAdminRole = Role::firstOrCreate(['guard_name' => 'api','name' => 'Associate Admin']);
        $staffRole = Role::firstOrCreate(['guard_name' => 'api','name' => 'Staff']);

        $adminPermissions = ['create_admin', 'view_admin', 'edit_admin', 'delete_admin','create_school'
        ,'delete_school','view_school','edit_school','create_student','delete_student','view_student',
        'edit_student','create_staff','delete_staff','view_staff','edit_staff',
        'create_trip','delete_trip','view_trip','edit_trip','create_attribute','delete_attribute',
        'view_attribute','edit_attribute','create_shop','delete_shop','view_shop','edit_shop',
        'support','transaction_history','topup','wallet', 'roles'];

        $associateAdminPermissions = ['create_school'
        ,'delete_school','view_school','edit_school','create_student','delete_student','view_student',
        'edit_student','create_staff','delete_staff','view_staff','edit_staff',
        'create_trip','delete_trip','view_trip','edit_trip','create_attribute','delete_attribute',
        'view_attribute','edit_attribute','create_shop','delete_shop','view_shop','edit_shop',
        'support','transaction_history','topup','wallet'];

        $staffPermissions = ['create_school'
        ,'delete_school','view_school','edit_school','create_student','delete_student','view_student',
        'edit_student','create_trip','delete_trip','view_trip','edit_trip','create_attribute',
        'delete_attribute','view_attribute','edit_attribute','create_shop','delete_shop','view_shop',
        'edit_shop','support','transaction_history','topup','wallet'];

        foreach ($adminPermissions as $permissionName) {
            $adminRole->givePermissionTo($permissionName);
        }

        foreach ($associateAdminPermissions as $permissionName) {
            // $permission = Permission::firstOrCreate(['guard_name' => 'api', 'name' => $permissionName]);
            $associateAdminRole->givePermissionTo($permissionName);
        }

        foreach ($staffPermissions as $permissionName) {
            // $permission = Permission::firstOrCreate(['guard_name' => 'api', 'name' => $permissionName]);
            $staffRole->givePermissionTo($permissionName);
        }

    }
}
