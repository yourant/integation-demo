<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Lazada2APIController;

class Lazada2CreateProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:create-product';

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
        try {
            $odataClient = new SapService();
            //LIVE - U_MPS_ECOMMERCE
            $brand = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_BRAND')->first();
            $primaryCategory = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_CATEGORY')->first();
            $deliveryOption = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_DELIVERY_OPTION')->first();
            $packageHeight = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_PACKAGE_HEIGHT')->first();
            $packageLength = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_PACKAGE_LENGTH')->first();
            $packageWeight = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_PACKAGE_WEIGHT')->first();
            $packageWidth = $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_PACKAGE_WIDTH')->first();
            $warrantyType= $odataClient->getOdataClient()->from('U_L2DD')->where('Code','L2_DFLT_WARRANTY_TYPE')->first();
            
            $count = 0;

            $itemCount = 0;

            $moreItems = true;

            while($moreItems){
                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ2_INTEGRATION','Yes')->skip($count)->get();

                if($getItems->isNotEmpty()){

                    $lazadaAPI = new Lazada2APIController();

                    foreach($getItems as $item){

                        $fields = [
                            'itemName' => $item['ItemName'],
                            'sellerSku' => $item['ItemCode'],
                            'quantity' => $item['QuantityOnStock'],
                            'price' => $item['ItemPrices']['8']['Price'],
                        ];

                        $createProductPayload = "
                            <Request>
                                <Product>
                                    <PrimaryCategory>".$primaryCategory->U_VALUE."</PrimaryCategory>
                                    <Attributes>
                                        <name>".$fields['itemName']."</name>
                                        <brand>".$brand->U_VALUE."</brand>
                                        <delivery_option_sof>".$deliveryOption->U_VALUE."</delivery_option_sof>
                                        <warranty_type>".$warrantyType->U_VALUE."</warranty_type>
                                    </Attributes>
                                    <Skus>
                                        <Sku>
                                            <SellerSku>".$fields['sellerSku']."</SellerSku>
                                            <quantity>".$fields['quantity']."</quantity>
                                            <price>".$fields['price']."</price>
                                            <package_length>".$packageLength->U_VALUE."</package_length>
                                            <package_height>".$packageHeight->U_VALUE."</package_height>
                                            <package_weight>".$packageWeight->U_VALUE."</package_weight>
                                            <package_width>".$packageWidth->U_VALUE."</package_width>
                                        </Sku>
                                    </Skus>
                                </Product>
                            </Request>";
                        
                        $response = $lazadaAPI->createProduct($createProductPayload);

                        if(!empty($response['data'])){
                            $itemId = $response['data']['item_id'];
                            $update = $odataClient->getOdataClient()->from('Items')
                                        ->whereKey($fields['sellerSku'])
                                        ->patch([
                                            'U_LAZ2_ITEM_CODE' => $itemId,
                                        ]);
                            
                            ($update ? $itemCount++ : '');
                        }
                    
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }
            }

            if($itemCount > 0){
                Log::channel('lazada2.item_master')->info($itemCount.' new product/s added');
            }else{
                Log::channel('lazada2.item_master')->info('No new Lazada products to be added.');
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());
        }
                

    }
}
