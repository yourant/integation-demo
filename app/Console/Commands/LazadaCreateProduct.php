<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        try {
            $odataClient = new SapService();

            $brand = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_BRAND')->first();
            $primaryCategory = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_CATEGORY')->first();
            $deliveryOption = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_DELIVERY_OPTION')->first();
            $packageHeight = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_HEIGHT')->first();
            $packageLength = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_LENGTH')->first();
            $packageWeight = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_WEIGHT')->first();
            $packageWidth = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_WIDTH')->first();
            $warrantyType= $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_WARRANTY_TYPE')->first();
            
            $count = 0;

            $moreItems = true;

            $items = [];

            while($moreItems){
                $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ_INTEGRATION','Y')
                                                    ->where('U_LAZ_ITEM_CODE',null)
                                                    ->skip($count)
                                                    ->get();

                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){

                        $items[] = [
                            'itemName' => $item['ItemName'],
                            'sellerSku' => $item['ItemCode'],
                            'quantity' => $item['QuantityOnStock'],
                            'price' => $item['ItemPrices']['6']['Price'],
                            'status' => $item['Valid']
                        ];
                    
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }
            }

            $itemCount = 0;
            $updateCount = 0;

            foreach($items as $item){

                $createProductPayload = "
                    <Request>
                        <Product>
                            <PrimaryCategory>".$primaryCategory->U_VALUE."</PrimaryCategory>
                            <Attributes>
                                <name>".$item['itemName']."</name>
                                <brand>".$brand->U_VALUE."</brand>
                                <delivery_option_sof>".$deliveryOption->U_VALUE."</delivery_option_sof>
                                <warranty_type>".$warrantyType->U_VALUE."</warranty_type>
                            </Attributes>
                            <Skus>
                                <Sku>
                                    <SellerSku>".$item['sellerSku']."</SellerSku>
                                    <quantity>".$item['quantity']."</quantity>
                                    <price>".$item['price']."</price>
                                    <package_length>".$packageLength->U_VALUE."</package_length>
                                    <package_height>".$packageHeight->U_VALUE."</package_height>
                                    <package_weight>".$packageWeight->U_VALUE."</package_weight>
                                    <package_width>".$packageWidth->U_VALUE."</package_width>
                                </Sku>
                            </Skus>
                        </Product>
                    </Request>";
                
                $lazadaAPI = new LazadaAPIController();
                
                $response = $lazadaAPI->createProduct($createProductPayload);

                if(!empty($response['data'])){
                    $itemId = $response['data']['item_id'];
                    $sellerSku = $response['data']['sku_list']['0']['seller_sku'];
                    $update = $odataClient->getOdataClient()->from('Items')
                                ->whereKey($item['sellerSku'])
                                ->patch([
                                    'U_LAZ_ITEM_CODE' => $itemId,
                                    'U_LAZ_SELLER_SKU' => $sellerSku
                                ]);
                    
                    ($update ? $itemCount++ : '');

                }else{

                    if($item['status'] == 'tYES'){
                        $payload = "<Request>
                                            <Product>
                                                <Skus>
                                                    <Sku>
                                                        <SellerSku>".$item['sellerSku']."</SellerSku>
                                                        <Status>active</Status>
                                                    </Sku>
                                                </Skus>
                                            </Product>
                                        </Request>";
                        $activate = $lazadaAPI->activateProduct($payload);

                        if($activate['code'] == '0'){
                            $updateCount++;
                        }

                    }else if($item['status'] == 'tNO'){
                        $getDetail = $lazadaAPI->getProductItem($item['sellerSku']);
                        $itemId = $getDetail['data']['item_id'];
                        $payload = "<Request>
                                        <Product>
                                            <ItemId>".$itemId."</ItemId>
                                            <Skus>
                                                <SellerSku>".$item['sellerSku']."</SellerSku>
                                            </Skus>
                                        </Product>
                                    </Request>";
                        $deactivate = $lazadaAPI->deactivateProduct($payload);
                       
                        if($deactivate['code'] == '0'){
                            $updateCount++;
                        }
                        
                    }
                    
                }

            }

            if($itemCount > 0){
                Log::channel('lazada.item_master')->info($itemCount.' new product/s added');
            }
            if($updateCount > 0){
                Log::channel('lazada2.item_master')->info($updateCount.' SKU/s status updated');
            }else{
                Log::channel('lazada.item_master')->info('No new Lazada products to be added.');
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());
        }
    }
}
