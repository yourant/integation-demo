<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'username' => 'shopee-admin',
                'password' => Hash::make('Password'),
                'platform' => 'shopee',
                'created_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
            ], [
                'username' => 'lazada-admin',
                'password' => Hash::make('Password'),
                'platform' => 'lazada',
                'created_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
            ], [
                'username' => 'shopee-test',
                'password' => Hash::make('Password'),
                'platform' => 'shopee',
                'created_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
            ],
            [
                'username' => 'tchub-admin',
                'password' => Hash::make('Password'),
                'platform' => 'tchub',
                'created_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }
}
