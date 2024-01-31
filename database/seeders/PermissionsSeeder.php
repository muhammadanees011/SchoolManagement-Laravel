<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //-----------------------ORGANIZATION ADMIN---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_admin']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_admin']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_admin']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_admin']);
        //-----------------------SCHOOLS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_school']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_school']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_school']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_school']);
        //-----------------------STUDENTS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_student']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_student']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_student']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_student']);
        //-----------------------STAFF---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_staff']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_staff']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_staff']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_staff']);
        //-----------------------TRIPS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_trip']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_trip']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_trip']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_trip']);
        //-----------------------ATTRIBUTES---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_attribute']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_attribute']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_attribute']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_attribute']);
        //-----------------------SHOPS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_shop']);
        //-----------------------SUPPORT---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'support']);
        //-----------------------TRANSACTION HISTORY---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'transaction_history']);
        //-----------------------ROLES---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'roles']);
        //-----------------------TOPUP---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'topup']);
        //-----------------------WALLET---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'wallet']);

    }
}
