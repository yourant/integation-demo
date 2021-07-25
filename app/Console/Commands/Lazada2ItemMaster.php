<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\Lazada2APIController;

class Lazada2ItemMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:item-master';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Item Master for 2nd Lazada Account';

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
        try {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                ->where('U_LAZ2_INTEGRATION','Yes')
                                                ->get();
            if(!empty($getItems['0'])){
                //lazada API
                $lazadaAPI = new Lazada2APIController();
                //Loop Results
                foreach($getItems as $item){
                    //Price and Stocks
                    $sapPrice = $item['ItemPrices']['8']['Price'];
                    $sapStock = round($item['ItemWarehouseInfoCollection']['0']['InStock']);
                    //Old and New SKU
                    $oldSku = $item['U_OLD_SKU']; //Old sku from SAP
                    $newSku = $item['ItemCode']; //Sku in lazada
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);

                    if(!empty($getByNewSku['data'])){
                        $lazadaItemId = $getByNewSku['data']['item_id'];
                        $finalSku = $newSku;
                        $odataClient->getOdataClient()->from('Items')
                                ->whereKey($newSku)
                                ->patch([
                                    'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                ]);
                    }else if($oldSku != null){
                        $getByOldSku = $lazadaAPI->getProductItem($oldSku);
                        if(!empty($getByOldSku['data'])){
                            $lazadaItemId = $getByOldSku['data']['item_id'];
                            $finalSku = $oldSku;
                            $oldSkuItemCode = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_OLD_SKU',$oldSku)
                                                    ->first();
                            $odataClient->getOdataClient()->from('Items')
                                    ->whereKey($oldSkuItemCode->ItemCode)
                                    ->patch([
                                        'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                    ]);
                        }
                    }

                    if(!empty($lazadaItemId) && !empty($finalSku)){
                            //Create SKU Payload
                        $skuPayload[] = "<Sku>
                                            <ItemId>".$lazadaItemId."</ItemId>
                                            <SellerSku>".$finalSku."</SellerSku>
                                            <Quantity>".$sapStock."</Quantity>
                                            <Price>".$sapPrice."</Price>
                                        </Sku>";
                    }
                }

                if(!empty($skuPayload)){
                    $finalPayload = "<Request>
                                        <Product>
                                            <Skus>
                                                ".implode('',$skuPayload)."
                                            </Skus>
                                        </Product>
                                    </Request>";
                    //Run 
                    $lazadaAPI->updatePriceQuantity($finalPayload);
                    
                    Log::channel('lazada2.update_price_qty')->info('Items stock and price updated.');

                }else{
                    Log::channel('lazada2.update_price_qty')->info('No Items stock and price to be updated.');
                }
            }else{
                Log::channel('lazada2.update_price_qty')->warning('No Lazada items available.');
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());
        }
    }
}
