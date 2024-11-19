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
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_site']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_site']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_site']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_site']);
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
        //-----------------------SHOPS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_shop']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_products']);
        //----------------------COURSES---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'create_course']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'delete_course']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'view_course']);
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'edit_course']);
        //----------------------PURCHASE HISTORY---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'purchase_history']);
        //----------------------PENDING INSTALLMENTS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'pending_installments']);
        //----------------------REFUNDS---------------------
        Permission::firstOrCreate(['guard_name' => 'api','name' => 'refunds']);
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
