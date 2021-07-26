<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\LazadaAPIController;

class LazadaCreateProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:create-product';

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
        $odataClient = new SapService();
        //Get items with lazada integration set as yes
        $getItems = $odataClient->getOdataClient()->from('Items')
                                                ->where('U_LAZ_INTEGRATION','Yes')
                                                ->get();
        if(!empty($getItems['0'])){

            foreach ($getItems as $item) {
                //lazada API
                $lazadaAPI = new LazadaAPIController();
                //Price and Stocks
                $sapPrice = $item['ItemPrices']['8']['Price'];
                $sapStock = round($item['ItemWarehouseInfoCollection']['0']['InStock']);
                $itemCode = $item['ItemCode']; //Sku in lazada
                $itemName = $item['ItemName'];
                $sku = $lazadaAPI->getProductItem($itemCode);

                if(empty($sku['data'])){
                    /**$createProductPayload = "
                            <Request>
                                <Product>
                                    <PrimaryCategory>6614</PrimaryCategory>
                                    <SPUId />
                                    <AssociatedSku />
                                    <Attributes>
                                        <name>".$itemName."</name>
                                        <brand>Bosch</brand>
                                    </Attributes>
                                    <Skus>
                                        <Sku>
                                            <SellerSku>".$itemCode."</SellerSku>
                                            <color_family>blue</color_family>
                                            <size>40</size>
                                            <quantity>".$sapStock."</quantity>
                                            <price>".$sapPrice."</price>
                                            <package_length>20</package_length>
                                            <package_height>15</package_height>
                                            <package_weight>2.5</package_weight>
                                            <package_width>15</package_width>
                                        </Sku>
                                    </Skus>
                                </Product>
                            </Request>";
                    
                    $lazadaAPI->createProduct($createProductPayload);    
                    $itemId = $lazadaAPI->getProductItem($itemCode);                    
                    $deactivateProductPayload = "
                    <Request>
                       <Product>
                          <ItemId>".$itemId."</ItemId>
                       </Product>
                    </Request>";
                    $lazadaAPI->deactivateProduct($deactivateProductPayload);
                    **/
                }
            }
             

        }else{
            print_r('No lazada products available');
        }

        
    }
}
