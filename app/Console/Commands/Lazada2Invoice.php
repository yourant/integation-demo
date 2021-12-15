<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Services\LazadaLogService;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;
use App\Http\Controllers\Lazada2APIController;

class Lazada2Invoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:ar-invoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada AR Invoice';

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

        $lazadaLog = new LazadaLogService('lazada2.ar_invoice');
    
        $lazadaAPI = new Lazada2APIController();
        
        $offset = 0;
        
        $moreOrders= true;

        $orderArray = [];

        $customerInfo = [];
        
        while($moreOrders){

            $orders = $lazadaAPI->getReadyToShipOrders($offset);

            if(!empty($orders['data']['orders'])){
                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    array_push($orderArray,$orderId);
                    //Basic Information
                    $customerName = $order['customer_first_name'].' '.$order['customer_last_name'];
                    $receiverPhone = $order['address_shipping']['phone'];
                    //Shipping Address
                    $sName = $order['address_shipping']['first_name'] .' '. $order['address_shipping']['last_name'];
                    $sPhone = $order['address_shipping']['phone'];
                    $sAddress = $order['address_shipping']['address1'];
                    $sPostCode = $order['address_shipping']['post_code'];
                    $sCountry = $order['address_shipping']['country'];
                    $shippingAddress = $sName."\n".$sPhone."\n".$sAddress.', '.$sPostCode.', '.$sCountry;
                    //Billing Address
                    $bName = $order['address_billing']['first_name'] .' '. $order['address_billing']['last_name'];
                    $bPhone = $order['address_billing']['phone'];
                    $bAddress = $order['address_billing']['address1'];
                    $bPostCode = $order['address_billing']['post_code'];
                    $bCountry = $order['address_billing']['country'];
                    $billingAddress = $bName."\n".$bPhone."\n".$bAddress.', '.$bPostCode.', '.$bCountry;
                    
                    $customerInfo[$orderId] = [
                        'U_Basic_Information' => 'Customer Name: '.$customerName."\n".'Receiver Phone: '.$receiverPhone,
                        'U_Shipping_Address' => $shippingAddress,
                        'U_Billing_Address' => $billingAddress,
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

        if(!empty($orderArray)){
            foreach($orderArray as $id){
                $orderDocEntry = $odataClient->getOdataClient()->select('DocEntry')->from('Orders')
                                    ->where('U_Order_ID',(string)$id)
                                    ->where('U_Ecommerce_Type','Lazada_2')
                                    ->where('DocumentStatus','bost_Open')
                                    ->where('Cancelled','tNO')
                                    ->first();

                $getInv = $odataClient->getOdataClient()->from('Invoices')
                                    ->where('U_Order_ID',(string)$id)
                                    ->where('U_Ecommerce_Type','Lazada_2')
                                    ->where(function($query){
                                        $query->where('DocumentStatus','bost_Open');
                                        $query->orWhere('DocumentStatus','bost_Close');
                                    })
                                    ->where('Cancelled','tNO')
                                    ->first();

                if($orderDocEntry && !$getInv){
                    $getSO = $odataClient->getOdataClient()->from('Orders')->find($orderDocEntry['DocEntry']);
                    $items = [];

                    foreach ($getSO['DocumentLines'] as $key => $value) {
                        $batchList = [];
                        if($value['BatchNumbers']) {                  
                            foreach ($value['BatchNumbers'] as $batch) {
                                $batchList[] = [
                                    'BatchNumber' => $batch['BatchNumber'],
                                    'Quantity' => $batch['Quantity']
                                ];
                            }
                        }
    
                        $items[] = [
                            'BaseType' => 17,
                            'BaseEntry' => $getSO['DocEntry'],
                            'BaseLine' => $key,
                            'BatchNumbers' => $batchList
                        ];
                    }
                    
                    try {
                        //Copy sales order to invoice
                        $odataClient->getOdataClient()->post('Invoices',[
                            'CardCode' => $getSO['CardCode'],
                            'DocDate' => $getSO['DocDate'],
                            'DocDueDate' => $getSO['DocDueDate'],
                            'PostingDate' => $getSO['TaxDate'],
                            'NumAtCard' => $getSO['NumAtCard'],
                            'U_Ecommerce_Type' => $getSO['U_Ecommerce_Type'],
                            'U_Order_ID' => $getSO['U_Order_ID'],
                            'U_Basic_Information' =>  $customerInfo[$getSO['U_Order_ID']]['U_Basic_Information'],
                            'U_Shipping_Address' => $customerInfo[$getSO['U_Order_ID']]['U_Shipping_Address'],
                            'U_Billing_Address' => $customerInfo[$getSO['U_Order_ID']]['U_Billing_Address'],
                            'DocumentLines' => $items 
                        ]);
                        
                        Log::channel('lazada2.ar_invoice')->info('A/R invoice for Lazada order:'.$getSO['U_Order_ID'].' created successfully.');

                    }catch (ClientException $e) {
                        $msg = "Order ".$finalSO['U_Order_ID']." has problems";
                        $lazadaLog->writeSapLog($e,$msg);
                    }
                }
                
            }
        }
        else{
            Log::channel('lazada2.ar_invoice')->info('No ready to ship orders for now.');
        }
            
        
    }
}
