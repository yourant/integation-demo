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
        /**$odataClient = new SapService();

        $item = $odataClient->getOdataClient()->from('Items')
                                                ->whereKey('181MKT20010')
                                                ->first();
        $createProductPayload = "
                    <Request>
                        <Product>
                            <PrimaryCategory>10000531</PrimaryCategory>
                            <Attributes>
                                <name>".$item['ItemName']."</name>
                                <brand>Makita</brand>
                                <delivery_option_sof>No</delivery_option_sof>
                                <warranty_type>No Warranty</warranty_type>
                            </Attributes>
                            <Skus>
                                <Sku>
                                    <SellerSku>".$item['ItemCode']."</SellerSku>
                                    <quantity>".$item['QuantityOnStock']."</quantity>
                                    <price>".$item['ItemPrices']['8']['Price']."</price>
                                    <package_length>35</package_length>
                                    <package_height>25</package_height>
                                    <package_weight>2</package_weight>
                                    <package_width>25</package_width>
                                </Sku>
                            </Skus>
                        </Product>
                    </Request>";
        
                    //Create Product        
                    print_r($lazada2->createProduct($createProductPayload));**/
                    
        /**$lazada2 = new Lazada2APIController();
        $request = $lazada2->getProductItem('181MKT20010');**/

        $odataClient = new SapService();
        $item = $odataClient->getOdataClient()->from('Items')
                                                ->whereKey('181MKT20010')
                                                ->first();
        $skuPayload = "<Request>
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
        
        $lazada2 = new Lazada2APIController();
        print_r($lazada2->updatePriceQuantity($skuPayload));
    }
}
