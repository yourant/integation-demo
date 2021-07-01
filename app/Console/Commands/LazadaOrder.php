<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\LazadaAPIController;

class LazadaOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:sales-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada Order';

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
        
        $odataClient = new SapService();
        
        $lazadaAPI = new LazadaAPIController();
        
        $order = $lazadaAPI->getOrder('55949912514307'); // Different SKU - Will use for demo
        
        $orderItems = $lazadaAPI->getOrderItem($order['data']['order_id']);
        
        $mergedItem = [];
        foreach ($orderItems['data'] as $item) {
            // Order Items
            $items[] = [
                'ItemCode' => $item['sku'],
                'Quantity' => 1,
                "TaxCode" => 'T1',
                'UnitPrice' => $item['item_price']
            ];
            // GRPO Items
            $goodsReceipt[] = [
                'ItemCode' => $item['sku'],
                'Quantity' => 50,
                "TaxCode" => 'T1',
                'UnitPrice' => $item['item_price']
            ];
        }

        $skuArray = array_column($orderItems['data'],'sku');
        $skus = '["'.implode('","', $skuArray).'"]';
        $products = $lazadaAPI->getProducts($skus);
        
        foreach($products['data']['products'] as $item){
            $itemName = $item['attributes']['name'];
            foreach($item['skus'] as $sku){
                if(in_array($sku['SellerSku'],$skuArray)){
                    $tempItems[] = [ // - Will remove when go live
                        'ItemCode' => $sku['SellerSku'],
                        'ItemName' => $itemName,
                        'ItemType' => 'itItems',
                        'U_LAZ_INTEGRATION' => 'No'
                    ];
                }
            }
        }
        //Check if Item exists - Will remove when go live
        foreach($tempItems as $key => $value){
            $itemData = array_slice($tempItems[$key],0);
            try {
                $odataClient->getOdataClient()->from('Items')->find(''.$itemData['ItemCode'].'');
            }catch (\Exception $e) {
                if($e->getCode() == '404'){
                    $odataClient->getOdataClient()->post('Items',$itemData);
                }else{
                    dd($e->getMessage());
                }
            }
        }
        //Create GRPO - Will remove when go live
        $odataClient->getOdataClient()->post('PurchaseDeliveryNotes',[
            'CardCode' => 'TV00001',
            'DocumentLines' => $goodsReceipt
        ]);
        //Create Sales Order
        $odataClient->getOdataClient()->post('Orders', [
            'CardCode' => 'Lazada_C',
            'DocDate' => $order['data']['created_at'],
            'DocDueDate' => $order['data']['created_at'],
            'PostingDate' => $order['data']['created_at'],
            'NumAtCard' => $order['data']['order_id'],
            'U_Ecommerce_Type' => 'Lazada',
            'U_Order_ID' => $order['data']['order_id'],
            'U_Customer_Name' => $order['data']['customer_first_name'].' '.$order['data']['customer_last_name'],
            'DocumentLines' => $items
        
        ]);
        
    }
}
