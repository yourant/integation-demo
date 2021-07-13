<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        try 
        {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ_INTEGRATION','Yes')
                                                    ->get();
            if(!empty($getItems['0'])){
                //lazada API
                $lazadaAPI = new LazadaAPIController();
                //Loop results
                foreach($getItems as $item){
                    //Initializations
                    $itemCode = $item['ItemIntrastatExtension']['ItemCode']; //Sku in lazada
                    $sapPrice = $item['ItemPrices']['8']['Price'];
                    $sapStock = round($item['ItemWarehouseInfoCollection']['0']['InStock']);
                    //Update Item Id UDF to SAP B1
                    $getProduct = $lazadaAPI->getProductItem($itemCode);
                    $lazadaItemId = $getProduct['data']['item_id'];
                    $odataClient->getOdataClient()->patch("Items("."'".$itemCode."'".")", [
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
                $lazadaAPI->updatePriceQuantity($finalPayload);
                
                Log::channel('lazada.update_price_qty')->info('Items stocks and price updated.');
            
            }else{
                Log::channel('lazada.update_price_qty')->info('No Lazada items available.');
            }

        } catch (\Exception $e) {
            Log::channel('lazada.update_price_qty')->emergency($e->getMessage());
        }
        
    }
}
