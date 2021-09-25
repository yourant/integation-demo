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
                                ->where('U_LAZ_INTEGRATION','Y')
                                ->where('U_LAZ_ITEM_CODE',null)
                                ->skip($count)
                                ->get();
                
                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){
                        
                        $items[] = [
                            'newSku' => $item['ItemCode'],
                            'oldSku' => $item['U_MPS_OLDSKU']
                        ];
                        
                    }
    
                    $count += count($getItems);
    
                }else{
                    $moreItems = false;
                }
    
            }

            $lazadaAPI = new LazadaAPIController();
            $batch = array_chunk($items,50);
            
            foreach($batch as $b){

                foreach($b as $key){
                    
                    $newSku = $key['newSku'];
                    $oldSku = $key['oldSku'];
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);

                    if(!empty($getByNewSku['data'])){
                        $lazadaItemId = $getByNewSku['data']['item_id'];

                        $update = $odataClient->getOdataClient()->from('Items')
                                ->whereKey($newSku)
                                ->patch([
                                    'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                ]);
                        
                        ($update ? $itemCount ++ : '');

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
                            
                            ($update ? $itemCount ++ : '');
                        }
                    }

                }
                
            }

            if($itemCount > 0){
                Log::channel('lazada.item_master')->info($itemCount.' Item Id UDFs updated.');
            
            }else{
                Log::channel('lazada.item_master')->warning('No new Lazada items to be sync.');
            
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());
        }
    }
}
