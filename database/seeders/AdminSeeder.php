<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user= \App\Models\User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Male',
            'email' => 'admin@example.com',
            'password' =>  Hash::make('password'),
            'role'=>'super_admin'
        ]);

        $roleName = 'Admin';
        $guardName = ['api'];

        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->where('guard_name', $guardName)->first();
        $user->assignRole($role);
    }
}
