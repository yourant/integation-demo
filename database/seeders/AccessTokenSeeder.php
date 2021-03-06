<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('access_tokens')->insert([
            [
                'platform' => 'shopee',
                'refresh_token' => null,
                'access_token' => null,
                'code' => null,
                'shop_id' => null,
                'created_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
            ], [
                'platform' => 'lazada',
                'refresh_token' => null,
                'access_token' => null,
                'code' => null,
                'shop_id' => null,
                'created_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' =>  Carbon::now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }
}
