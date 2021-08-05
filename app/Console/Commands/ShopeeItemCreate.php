<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ShopeeItemCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:item-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Shopee Product if it does not exist';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();   
        
        $productList = [];
        $moreProducts = true;
        $offset = 0;
        $pageSize = 50;

        // retrieve products with base
        while ($moreProducts) {
            $productSegmentList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'NORMAL',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = json_decode($shopeeProductsResponse->body(), true);

            foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                array_push($productSegmentList, $item['item_id']);
            }

            $productStr = implode(",", $productSegmentList);
            // for testing
            $productStr = '5392771665,8070898047,1199243276';
            
            $shopeeProductBase = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
            $shopeeProductBaseResponse = Http::get($shopeeProductBase->getFullPath(), array_merge([
                'item_id_list' => $productStr
            ], $shopeeProductBase->getShopCommonParameter()));
            
            $shopeeProductBaseResponseArr = json_decode($shopeeProductBaseResponse->body(), true);

            $productList = array_merge($productList, $shopeeProductBaseResponseArr['response']['item_list']);
            // for testing
            $productList = $shopeeProductBaseResponseArr['response']['item_list'];

            if ($shopeeProductsResponseArr['response']['has_next_page']) {
                $offset += $pageSize;
            } else {
                $moreProducts = false;
            }   
        }
        
        dd($productList);

    }
}
