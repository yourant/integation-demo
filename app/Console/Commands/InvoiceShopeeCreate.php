<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Services\SapService;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;

class InvoiceShopeeCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee-create:ar-invoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create AR Invoice in SAP B1';

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
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        $orderList = [];
        $moreReadyOrders = true;
        $offset = 0;
        $pageSize = 50;
        
        while ($moreReadyOrders) {
            $shopeeReadyOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
            $shopeeReadyOrdersResponse = Http::get($shopeeReadyOrders->getFullPath(), array_merge([
                'time_range_field' => 'create_time',
                'time_from' => 1623970808,
                'time_to' => 1624575608,
                'page_size' => $pageSize,
                'cursor' => $offset,
                'order_status' => 'SHIPPED',
                'response_optional_fields' => 'order_status'
            ], $shopeeReadyOrders->getShopCommonParameter()));

            $shopeeReadyOrdersResponseArr = json_decode($shopeeReadyOrdersResponse->body(), true);

            foreach ($shopeeReadyOrdersResponseArr['response']['order_list'] as $order) {
                array_push($orderList, $order['order_sn']);
            }

            if ($shopeeReadyOrdersResponseArr['response']['more']) {
                $offset += $pageSize;
            } else {
                $moreReadyOrders = false;
            }   
        }
        // dd($orderList);
        // $orderStr = implode(",", $orderList);
        // // dd($orderStr);
        
        // $shopeeOrderDetail = new ShopeeService('/order/get_order_detail', 'shop', $shopeeToken->access_token);
        // $shopeeOrderDetailResponse = Http::get($shopeeOrderDetail->getFullPath(), array_merge([
        //     'order_sn_list' => $orderStr,
        //     'response_optional_fields' => 'total_amount,item_list,buyer_user_id,buyer_username,recipient_address,estimated_shipping_fee,actual_shipping_fee,actual_shipping_fee_confirmed'
        // ], $shopeeOrderDetail->getShopCommonParameter()));

        // $shopeeOrderDetailResponseArr = json_decode($shopeeOrderDetailResponse->body(), true);
        // $orderListDetails = $shopeeOrderDetailResponseArr['response']['order_list'];
        // dd($orderListDetails);
        // dd($shopeeOrderDetailResponseArr['response']['order_list']);
            // dd($orderList);
        $salesOrderSapService = new SapService();

        foreach ($orderList as $order) {

            $salesOrder = $salesOrderSapService->getOdataClient()->from('Orders')
                ->where('U_Order_ID', (string)$order)
                ->first();

            $existInv = $salesOrderSapService->getOdataClient()->from('Invoices')
                ->where('U_Order_ID', (string)$order)
                ->first();

            if ($salesOrder && !$existInv) {
                $itemList = [];

                foreach ($salesOrder['DocumentLines'] as $itemLine => $item) {
                    $batchList = [];

                    if ($item['BatchNumbers']) {                  
                        foreach ($item['BatchNumbers'] as $batch) {
                            $batchList[] = [
                                'BatchNumber' => $batch['BatchNumber'],
                                'Quantity' => $batch['Quantity']
                            ];
                        }
                    }

                    $itemList[] = [
                        'BaseType' => 17,
                        'BaseEntry' => $salesOrder['DocEntry'],
                        'BaseLine' => $itemLine,
                        'BatchNumbers' => $batchList
                    ];
                }

                $finalInvoice = [
                    'CardCode' => $salesOrder['CardCode'],
                    'NumAtCard' => $salesOrder['NumAtCard'],
                    'DocDate' => $salesOrder['DocDate'],
                    'DocDueDate' => $salesOrder['DocDueDate'],
                    'TaxDate' => $salesOrder['TaxDate'],
                    'DocTotal' => $salesOrder['DocTotal'],
                    'U_Ecommerce_Type' => $salesOrder['U_Ecommerce_Type'],
                    'U_Order_ID' => $salesOrder['U_Order_ID'],
                    'U_Customer_Name' => $salesOrder['U_Customer_Name'],
                    'U_Customer_Phone' => $salesOrder['U_Customer_Phone'],
                    'U_Customer_Shipping_Address' => $salesOrder['U_Customer_Shipping_Address'],
                    'DocumentLines' => $itemList
                ];
 
                $salesOrder = $salesOrderSapService->getOdataClient()->post('Invoices', $finalInvoice);

                if ($salesOrder) {
                    dd('Successfully created invoices');
                } else {
                    dd('Failed to create invoices');
                }
              
                // $escrowDetail = new ShopeeService('/payment/get_escrow_detail', 'shop', $shopeeToken->access_token);
                // $escrowDetailResponse = Http::get($escrowDetail->getFullPath(), array_merge([
                //     'order_sn' => $order['order_sn']
                // ], $escrowDetail->getShopCommonParameter()));
                // $escrowDetailResponseArr = json_decode($escrowDetailResponse->body(), true);
                // $escrow = $escrowDetailResponseArr['response'];
                // // dd($escrowDetailResponseArr);

                // $ecm = $salesOrderSapService->getOdataClient()->from('U_ECM')->get();
                // foreach ($ecm as $ecmItem) {
                //     if ($ecmItem['properties']['Code'] == 'SHOPEE_CUSTOMER') {
                //         $shopeeCust = $ecmItem['properties']['Name'];
                //     } elseif ($ecmItem['properties']['Code'] == 'SHIPPING_FEE') {
                //         $shippingItem = $ecmItem['properties']['Name'];
                //     } elseif ($ecmItem['properties']['Code'] == 'SELLER_VOUCHER') {
                //         $sellerVoucherItem = $ecmItem['properties']['Name'];
                //     }      
                // }
                    
                // $itemList = [];

                // foreach ($order['item_list'] as $item) {                   
                //     try {
                //         $response = $salesOrderSapService->getOdataClient()->from('Items')->where('U_SH_ITEM_CODE', (string)$item['item_id'])->first();
                //     } catch(ClientException $e) {
                //         dd($e->getResponse()->getBody()->getContents());
                //     }
                //     // dd($response);
                //     $sapItem = $response['properties'];

                //     $itemList[] = [
                //         'ItemCode' => $sapItem['ItemCode'],
                //         'Quantity' => $item['model_quantity_purchased'],
                //         'TaxCode' => 'T1',
                //         'UnitPrice' => $item['model_discounted_price']
                //     ];
                // }

                // if ($escrow['order_income']['buyer_paid_shipping_fee']) {
                //     $itemList[] = [
                //         'ItemCode' => $shippingItem,
                //         'Quantity' => 1,
                //         'TaxCode' => 'T1',
                //         'UnitPrice' => $escrow['order_income']['buyer_paid_shipping_fee']
                //     ];           
                // }

                // if ($escrow['order_income']['voucher_from_seller']) {
                //     $itemList[] = [
                //         'ItemCode' => $sellerVoucherItem,
                //         'Quantity' => -1,
                //         'TaxCode' => 'T1',
                //         'UnitPrice' => $escrow['order_income']['voucher_from_seller']
                //     ];           
                // }
                // dd($itemList);             
            }        
        }
    }
}
