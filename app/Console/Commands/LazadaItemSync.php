<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LazadaAPIController;

class LazadaItemSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:item-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize the item master to Lazada products';

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
            $odataClient = new SapService();
            
            $count = 0;

            $itemCount = 0;

            $moreItems = true;
            
            $items = [];
            
            while($moreItems){
            
                $getItems = $odataClient->getOdataClient()
                                ->from('Items')
                                ->where('U_LAZ_INTEGRATION','Yes')
                                ->where('U_LAZ_ITEM_CODE',null) // ->where('U_LAZ_SELLER_SKU',null)
                                ->skip($count)
                                ->get();
                
                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){
                        
                        $items[] = [
                            'itemCode' => $item['ItemCode'],
                            'oldSku' => $item['U_OLD_SKU'] // Live - U_MPS_OLDSKU
                        ];
                        
                    }
    
                    $count += count($getItems);
    
                }else{
                    $moreItems = false;
                }
    
            }
            
            if(!empty($items)){
                
                $lazadaAPI = new LazadaAPIController();
                $batch = array_chunk($items,50);
                $oldSkus = [];
                $itemCodes = [];
                
                foreach($batch as $b){
                 
                    foreach($b as $key){

                        array_push($itemCodes,$key['itemCode']);
    
                        if($key['oldSku'] != null){
                            array_push($oldSkus,$key['oldSku']);
                        }
                    }
    
                    if(!empty($itemCodes)){
                        $skus =  '['.'"'.implode('","',$itemCodes).'"'.']';
                        $response = $lazadaAPI->getProducts($skus);
                        $resultArray = [];
                        
                        if(!empty($response['data']['products'])){

                            foreach($response['data']['products'] as $product){
                            
                                foreach($product['skus'] as $sku){
                                    
                                    $key = array_search($sku['SellerSku'],$itemCodes);
    
                                    if($key === false){
                                        //Not Found
                                    }else{
    
                                        $update = $odataClient->getOdataClient()->from('Items')
                                                                            ->whereKey($itemCodes[$key])
                                                                            ->patch([
                                                                                'U_LAZ_ITEM_CODE' => $product['item_id'],
                                                                            ]);
                                                                            //live - 'U_LAZ_SELLER_SKU' => $sku['SellerSku']
                                        ($update ? $itemCount ++ : '');
    
                                    }
                                    
                                }
                            
                            }
                        }
                        
                    }

                    if(!empty($oldSkus)){
                        $skus =  '['.'"'.implode('","',$oldSkus).'"'.']';
                        $response = $lazadaAPI->getProducts($skus);
                        $resultArray = [];

                        if(!empty($response['data']['products'])){

                            foreach($response['data']['products'] as $product){
        
                                foreach($product['skus'] as $sku){
                                    
                                    $key = array_search($sku['SellerSku'],$oldSkus);

                                    if($key === false){
                                        //Not Found
                                    }else{

                                        $get = $odataClient->getOdataClient()->from('Items')
                                                        ->where('U_OLD_SKU',$oldSkus[$key])//Live - U_MPS_OLDSKU
                                                        ->first();

                                        $update = $odataClient->getOdataClient()->from('Items')
                                                                            ->whereKey($get->ItemCode)
                                                                            ->patch([
                                                                                'U_LAZ_ITEM_CODE' => $product['item_id'],
                                                                            ]);
                                                                            //live - 'U_LAZ_SELLER_SKU' => $sku['SellerSku']
                                        ($update ? $itemCount ++ : '');

                                    }
                                    
                                }
                            
                            }
                        }
                    }
    
                }
            }

            if($itemCount > 0){
                Log::channel('lazada.item_master')->info($itemCount.' Item Id UDFs updated.');
            
            }else{
                Log::channel('lazada.item_master')->info('No new Lazada items to be sync.');
            
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());
        }
    }
}
