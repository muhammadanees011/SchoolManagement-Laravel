<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
        ['name' => 'create'],
        ['name' => 'delete'],
        ['name' => 'view'],
        ['name' => 'edit'],
        ];
        DB::table('permissions')->insert($data);
    }
}
