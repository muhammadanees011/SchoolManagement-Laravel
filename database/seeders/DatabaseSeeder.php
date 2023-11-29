<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        \App\Models\User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Male',
            'email' => 'admin@example.com',
            'password' =>  Hash::make('password'),
            'role'=>'super_admin'
        ]);

        \App\Models\User::factory()->create([
            'first_name' => 'Stockton',
            'last_name' => 'User',
            'email' => 'stockton@example.com',
            'password' =>  Hash::make('password'),
            'role'=>'school_user'
        ]);
        
        \App\Models\User::factory()->create([
            'first_name' => 'student',
            'last_name' => 'User',
            'gender' => 'Male',
            'email' => 'student@example.com',
            'password' =>  Hash::make('password'),
            'role'=>'student'
        ]);
    }
}
