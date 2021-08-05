<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
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
        //SAP odataClient
        $odataClient = new SapService();
        $lazada2 = new Lazada2APIController();
        
        $item = $odataClient->getOdataClient()->from('Items')
                                                ->whereKey('181MKT20011')
                                                ->first();
        $fields = [
            'itemName' => $item['ItemName'],
            'sellerSku' => $item['ItemCode'],
            'quantity' => $item['QuantityOnStock'],
            'price' => $item['ItemPrices']['8']['Price'],
            'lazItemCode' => $item['U_LAZ2_ITEM_CODE']
        ];

        if($fields['lazItemCode'] ==  null){
            $createProductPayload = "
                        <Request>
                            <Product>
                                <PrimaryCategory>10000531</PrimaryCategory>
                                <Attributes>
                                    <name>".$fields['itemName']."</name>
                                    <brand>Makita</brand>
                                    <delivery_option_sof>No</delivery_option_sof>
                                    <warranty_type>No Warranty</warranty_type>
                                </Attributes>
                                <Skus>
                                    <Sku>
                                        <SellerSku>".$fields['sellerSku']."</SellerSku>
                                        <quantity>".$fields['quantity']."</quantity>
                                        <price>".$fields['price']."</price>
                                        <package_length>35</package_length>
                                        <package_height>25</package_height>
                                        <package_weight>2</package_weight>
                                        <package_width>25</package_width>
                                    </Sku>
                                </Skus>
                            </Product>
                        </Request>";
            
        }else if($fields['lazItemCode'] !=  null){
            $data = [
                'itemId' => $fields['lazItemCode'],
                'sellerSku' => $fields['sellerSku']
            ];
            $getProduct = $lazada2->getProduct($data);
            //print_r($getProduct);
            $assocSku = $getProduct['data']['skus']['0']['SellerSku'];
            $createProductPayload = "
                        <Request>
                            <Product>
                                <AssociatedSku>".$assocSku."</AssociatedSku>
                                <Skus>
                                    <Sku>
                                        <SellerSku>".$fields['sellerSku']."</SellerSku>
                                        <quantity>".$fields['quantity']."</quantity>
                                        <price>".$fields['price']."</price>
                                        <package_length>35</package_length>
                                        <package_height>25</package_height>
                                        <package_weight>2</package_weight>
                                        <package_width>25</package_width>
                                    </Sku>
                                </Skus>
                            </Product>
                        </Request>";
        }
        //Create Product        
        $response = $lazada2->createProduct($createProductPayload);
        print_r($response);
        
        /**$updateItemCode = $odataClient->getOdataClient()->from('Items')
                    ->whereKey($item['ItemCode'])
                    ->patch([
                        'U_LAZ2_ITEM_CODE' => $response['data']['item_id'],
        ]);

        if($updateItemCode){
            print_r('Item Code UDF updated successfully');
        } **/

        
        /**$skuPayload = "<Request>
                            <Product>
                                <Skus>
                                    <Sku>
                                        <ItemId>".$item['U_LAZ2_ITEM_CODE']."</ItemId>
                                        <SellerSku>".$item['ItemCode']."</SellerSku>
                                        <Quantity>".$item['QuantityOnStock']."</Quantity>
                                        <Price>".$item['ItemPrices']['8']['Price']."</Price>
                                    </Sku>
                                </Skus>
                            </Product>
                        </Request>";
        print_r($lazada2->updatePriceQuantity($skuPayload));**/
    }
}
