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

        $adminPermissions = ['create_admin', 'view_admin', 'edit_admin', 'delete_admin','create_site'
        ,'delete_site','view_site','edit_site','create_student','delete_student','view_student',
        'edit_student','create_course','view_course','edit_course','delete_course','purchase_history',
        'pending_installments','refunds','create_staff','delete_staff','view_staff','edit_staff',
        'create_shop','delete_shop','view_shop','edit_shop','view_products',
        'transaction_history','topup','wallet', 'roles'];

        $associateAdminPermissions = ['create_site'
        ,'delete_site','view_site','edit_site','create_student','delete_student','view_student',
        'edit_student','create_course','view_course','edit_course','delete_course','purchase_history',
        'pending_installments','refunds','create_staff','delete_staff','view_staff','edit_staff',
        'create_shop','delete_shop','view_shop','edit_shop',
        'transaction_history','topup','wallet'];

        // $staffPermissions = ['create_site'
        // ,'delete_site','view_site','edit_site','create_student','delete_student','view_student',
        // 'edit_student','create_shop','delete_shop','view_shop',
        // 'edit_shop','transaction_history','topup','wallet'];

        $staffPermissions = ['transaction_history','topup','wallet'];

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
