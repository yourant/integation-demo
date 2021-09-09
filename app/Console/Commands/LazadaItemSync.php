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

            while($moreItems){
            
                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Y')->skip($count)->get();//Live - Y/N
                
                if($getItems->isNotEmpty()){
                    
                    $lazadaAPI = new LazadaAPIController();
                    
                    foreach($getItems as $item){
                        //Old and New SKU
                        $oldSku = $item['U_MPS_OLDSKU']; //Live - U_MPS_OLDSKU
                        $newSku = $item['ItemCode']; //New SKU
                        $getByNewSku = $lazadaAPI->getProductItem($newSku);
                        
                        if(!empty($getByNewSku['data'])){
                            $lazadaItemId = $getByNewSku['data']['item_id'];

                            $update = $odataClient->getOdataClient()->from('Items')
                                    ->whereKey($newSku)
                                    ->patch([
                                        'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                    ]);
                            
                            ($update ? $itemCount++ : '');

                        }else if($oldSku != null){
                            $getByOldSku = $lazadaAPI->getProductItem($oldSku);
                            
                            if(!empty($getByOldSku['data'])){
                                $lazadaItemId = $getByOldSku['data']['item_id'];
                                $oldSkuItemCode = $odataClient->getOdataClient()->from('Items')
                                                        ->where('U_MPS_OLDSKU',$oldSku)
                                                        ->first();

                                $update = $odataClient->getOdataClient()->from('Items')
                                        ->whereKey($oldSkuItemCode->ItemCode)
                                        ->patch([
                                            'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                        ]);
                                
                                ($update ? $itemCount++ : '');
                            }
                        }
                        
                    }
    
                    $count += count($getItems);
    
                }else{
                    $moreItems = false;
                }
    
            }

            if($itemCount > 0){
                Log::channel('lazada.item_master')->info($itemCount.' Item Id UDFs updated.');
            
            }else{
                Log::channel('lazada.item_master')->warning('No Lazada items available.');
            
            }


        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());
        }
    }
}
