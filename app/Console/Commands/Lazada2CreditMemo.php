<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Lazada2APIController;

class Lazada2CreditMemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:credit-memo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada 2 Credit Memo';

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
            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA2_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_ECM')->where('Code','PERCENTAGE')->first();
            
            $lazadaAPI = new Lazada2APIController();

            $moreOrders= true;
            
            $offset = 0;

            $orderIdArray = [];

            while($moreOrders){
                
                $orders = $lazadaAPI->getReturnedOrders($offset);

                if(!empty($orders['data']['orders'])){

                    foreach($orders['data']['orders'] as $order){
                        $orderId = $order['order_id'];
                        array_push($orderIdArray,$orderId);
                        
                        $tempCM[$orderId] = [
                            'CardCode' => $lazadaCustomer->Name,
                            'DocDate' => substr($order['created_at'],0,10),
                            'DocDueDate' => substr($order['created_at'],0,10),
                            'TaxDate' => substr($order['created_at'],0,10),
                            'NumAtCard' => $orderId,
                            'U_Ecommerce_Type' => 'Lazada_2',
                            'U_Order_ID' => $orderId,
                            'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
                        ];
                    
                    }

                    if($orders['data']['count'] == $orders['data']['countTotal']){
                        $moreOrders = false;
                    }else{  
                        $offset += $orders['data']['count'];
                    }
                    
                }else{
                    $moreOrders = false;
                }

            }

            if(!empty($orderIdArray)){
        
                $orderIds = '['.implode(',',$orderIdArray).']';
                $orderItems = $lazadaAPI->getMultipleOrderItems($orderIds);

                foreach ($orderItems['data'] as $item) {
                    $orderId = $item['order_id'];
                    
                    foreach($item['order_items'] as $orderItem){
                        if($orderItem['status'] == 'returned'){
                            $shippingAmount = $orderItem['shipping_amount'];
                            $paidPrice = $orderItem['paid_price'];
                            
                            if($shippingAmount != 0){
                                $finalPrice = $paidPrice + $shippingAmount;
                            }else{
                                $finalPrice = $paidPrice;
                            }
                            
                            $items[$orderId][] = [
                                'ItemCode' => $orderItem['sku'],
                                'Quantity' => 1,
                                'VatGroup' => $taxCode->Name,
                                'UnitPrice' => $finalPrice / $percentage->Name
                            ];

                            $refund[$orderId][] = $finalPrice;
                        }
                        
                    }
                    
                    $tempCM[$orderId]['DocTotal'] = array_sum($refund[$orderId]);
                    $tempCM[$orderId]['DocumentLines'] = $items[$orderId];
                    
                }
        
                foreach($tempCM as $key => $value){
                    $finalCM = array_slice($tempCM[$key],0);
                    $getCM = $odataClient->getOdataClient()->from('CreditNotes')
                                    ->where('U_Order_ID',(string)$finalCM['U_Order_ID'])
                                    ->where('U_Ecommerce_Type','Lazada_2')
                                    ->where(function($query){
                                        $query->where('DocumentStatus','bost_Open');
                                        $query->orWhere('DocumentStatus','bost_Close');
                                    })
                                    ->where('Cancelled','tNO')
                                    ->first();
                    if(!$getCM){
                        $odataClient->getOdataClient()->post('CreditNotes',$finalCM);
                        
                        Log::channel('lazada2.credit_memo')->info('Credit memo for Lazada order:'.$finalCM['U_Order_ID'].' created successfully.');
                    }else{
                        unset($finalCM);
                    }
                    
                }

            }else{
                Log::channel('lazada2.credit_memo')->info('No returned orders for now.');
            }
            
        } catch (\Exception $e) {
            Log::channel('lazada2.credit_memo')->emergency($e->getMessage());
        }

    }
}
