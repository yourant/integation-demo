<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:order';

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
        
        $odataClient = (new LazadaLoginController)->login();
        
        $lazada = new LazadaController();
        
        $order = $lazada->getOrder('54789621886245'); // Different SKU - Will use for demo
        
        $orderItems = $lazada->getOrderItem($order['data']['order_id']);
        
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
        $products = $lazada->getProducts($skus);
        
        foreach($products['data']['products'] as $item){
            $itemName = $item['attributes']['name'];
            foreach($item['skus'] as $sku){
                if(in_array($sku['SellerSku'],$skuArray)){
                    $tempItems[] = [
                        'ItemCode' => $sku['SellerSku'],
                        'ItemName' => $itemName,
                        'ItemType' => 'itItems'
                    ];
                }
            }
        }
        //Check if Item exists
        foreach($tempItems as $key => $value){
            $itemData = array_slice($tempItems[$key],0);
            try {
                $odataClient->from('Items')->find(''.$itemData['ItemCode'].'');
            }catch (\Exception $e) {
                if($e->getCode() == '404'){
                    $odataClient->post('Items',$itemData);
                }else{
                    dd($e->getMessage());
                }
            }
        }
        //Create GRPO
        $odataClient->post('PurchaseDeliveryNotes',[
            'CardCode' => 'TV00001',
            'DocumentLines' => $goodsReceipt
        ]);
        //Create Sales Order
        $odataClient->post('Orders', [
            'CardCode' => 'Lazada_C',
            'DocDate' => $order['data']['created_at'],
            'DocDueDate' => '2021-06-20',
            'U_Order_ID' => $order['data']['order_id'],
            'U_Customer_Name' => $order['data']['customer_first_name'].' '.$order['data']['customer_last_name'],
            'DocumentLines' => $items
        ]);
        
    }
}
