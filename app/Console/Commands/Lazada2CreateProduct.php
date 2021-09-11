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
        try {
            $odataClient = new SapService();
            
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
                            'lazItemCode' => $item['U_LAZ2_ITEM_CODE']
                        ];

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
                        
                        $response = $lazadaAPI->createProduct($createProductPayload);

                        if(!empty($response['data'])){
                            $itemId = $response['data']['item_id'];
                            $update = $odataClient->getOdataClient()->from('Items')
                                        ->whereKey($fields['sellerSku'])
                                        ->patch([
                                            'U_LAZ2_ITEM_CODE' => $itemId,
                                        ]);
                            
                            ($update ? $itemCount++ : '');
                        }else if($response['code'] == 500){
                            print_r('product already exists');
                        }
                    
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }
            }

            if($itemCount > 0){
                print_r($itemCount.' SKUs added');
            }

        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
                
        
        

        
                    
        
        
       

    }
}
