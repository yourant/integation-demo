<?php

namespace App\Console\Commands;

use App\Services\SapService;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PriceAndStockShopeeUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee-update:price-and-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the shopee products\' price and stock';

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
        $itemSapService = new SapService();

        //get all the items
        $sapItems = $itemSapService->getOdataClient()->get('Items');
        // dd($sapItems[6]['properties']['ItemPrices'][9]['Price']);
        // dd($sapItems[6]['properties']['QuantityOnStock']);

        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');
        
        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $shopeeAccess->getCode(),
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => $shopeeAccess->getShopId()
        ]);
        
        $accessResponseArr = json_decode($accessResponse->body(), true);

        foreach ($sapItems as $item) {         
            $itemProps = $item['properties'];
            // dd($itemProps);
            if ($itemProps['U_SH_INTEGRATION'] == 'Yes') {
                $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $accessResponseArr['access_token']);
                $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
                    'item_id' => (int) $itemProps['U_SH_ITEM_CODE'],
                    'price_list' => [
                        [
                            'model_id' => 0,
                            'original_price' => (int) $itemProps['ItemPrices'][9]['Price']
                        ]
                    ]
                ]);
                // dd($shopeePriceUpdateResponse->body());

                $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $accessResponseArr['access_token']);
                $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
                    'item_id' => (int) $itemProps['U_SH_ITEM_CODE'],
                    'stock_list' => [
                        [
                            'model_id' => 0,
                            'normal_stock' => (int) $itemProps['QuantityOnStock']
                        ]
                    ]
                ]);
                // dd($shopeeStockUpdateResponse->body());
            }
        }
        // dd($sapItems);
    }
}
