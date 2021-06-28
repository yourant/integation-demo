<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaItemMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:item';

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
         //SAP odataClient
         $odataClient = (new LazadaLoginController)->login();
         //lazada endpoints
         $lazada = new LazadaController();
         //Get items with lazada integration set as yes
         $getItems = $odataClient->from('Items')
                                ->where('U_LAZ_INTEGRATION','Yes')
                                ->get();
        //Loop results
         foreach($getItems as $item){
            //Initializations
            $itemCode = $item['ItemIntrastatExtension']['ItemCode']; //Sku in lazada
            $sapPrice = $item['ItemPrices']['8']['Price'];
            $sapStock = round($item['ItemWarehouseInfoCollection']['0']['InStock']);
            //Update Item Id UDF to SAP B1
            $getProduct = $lazada->getProductItem($itemCode);
            $lazadaItemId = $getProduct['data']['item_id'];
            $odataClient->patch("Items("."'".$itemCode."'".")", [
                'U_LAZ_ITEM_CODE' => $lazadaItemId,
            ]);
            //Create SKU Payload
            $skuPayload[] = "<Sku>
                                <ItemId>".$lazadaItemId."</ItemId>
                                <SellerSku>".$itemCode."</SellerSku>
                                <Quantity>".$sapStock."</Quantity>
                                <Price>".$sapPrice."</Price>
                            </Sku>";
            
         }
         $finalPayload = "<Request>
                            <Product>
                            <Skus>
                                ".implode('',$skuPayload)."
                            </Skus>
                            </Product>
                        </Request>";
        //Run 
        $lazada->updatePriceQuantity($finalPayload);
    }
}
