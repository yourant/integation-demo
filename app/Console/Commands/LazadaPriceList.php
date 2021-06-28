<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaPriceList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        //Get item price from Lazada price list
        $lazadaPrice = $item['ItemPrices']['8']['Price'];
        //itemCode
        $itemCode = $item['ItemIntrastatExtension']['ItemCode'];
        //Lazada Item Code
        $lazItemCode =$item['U_LAZ_ITEM_CODE'];
        //payload request
        $payload = "<Request>
                        <Product>
                        <Skus>
                            <Sku>
                                <ItemId>".$lazItemCode."</ItemId>
                                <SellerSku>".$itemCode."</SellerSku>
                                <Price>".$lazadaPrice."</Price>
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
