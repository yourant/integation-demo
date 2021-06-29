<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\SAPLoginController;
use App\Http\Controllers\LazadaAPIController;

class LazadaItemMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:item-master';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada Item Master';

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
        $odataClient = new SapService();
        //lazada endpoints
        $lazada = new LazadaAPIController();
        //Get items with lazada integration set as yes
        $getItems = $odataClient->getOdataClient()->from('Items')
                                                ->where('U_LAZ_INTEGRATION','Yes')
                                                ->get();

        print_r($getItems);
       //Loop results
        /**foreach($getItems as $item){
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
       $lazada->updatePriceQuantity($finalPayload); **/
       
        
    }
}
