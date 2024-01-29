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
        //
        Role::firstOrCreate(['guard_name' => 'api','name' => 'admin']);
        Role::firstOrCreate(['guard_name' => 'api','name' => 'writer']);
        Role::firstOrCreate(['guard_name' => 'api','name' => 'reader']);
    }
}
