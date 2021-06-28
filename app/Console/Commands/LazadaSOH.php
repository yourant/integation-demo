<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaSOH extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:soh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada stock on hand';

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
        //1844845298 - Use this Lazada product id for testing purposes. Look this on inactive products tab in Lazada
        $odataClient = (new LazadaLoginController)->login();
        //ItemCode - 12345678a(Sku from Lazada)
        $item = $odataClient->from('Items')->find('12345678a'); // ItemPrices[8]['Price']
        //In stock value from General Warehouse (first)
        $inStock = round($item['ItemWarehouseInfoCollection']['0']['InStock']);
        //itemCode
        $itemCode = $item['ItemIntrastatExtension']['ItemCode'];
        //Lazada Item Code
        $lazItemCode =$item['U_LAZ_ITEM_CODE'];
        //Payload request
        $payload = "<Request>
                        <Product>
                            <Skus>
                                <Sku>
                                    <ItemId>".$lazItemCode."</ItemId>
                                    <SellerSku>".$itemCode."</SellerSku>
                                    <Quantity>".$inStock."</Quantity>
                                </Sku>
                            </Skus>
                        </Product>
                </Request>";
        //Lazada
        $lazada = new LazadaController();
        //Transfer payload
        $lazada->updatePriceQuantity($payload);

    }
}
