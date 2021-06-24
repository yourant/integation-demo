<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LazadaSAPController extends Controller
{
    public function salesOrder(){
        $odataClient = (new LazadaLoginController)->login();
        $lazada = new LazadaController();
        $orders = $lazada->getOrders();
        
        foreach($orders['data']['orders'] as $order){
            $orderIdArray[] = $order['order_id'];
            
            $tempSO[$order['order_id']]['CardCode'] = 'Lazada_C';
            $tempSO[$order['order_id']]['DocDate'] = $order['created_at'];
            $tempSO[$order['order_id']]['DocDueDate'] = '2021-06-25'; // Not sure for this one
            $tempSO[$order['order_id']]['U_Order_ID'] = $order['order_id'];
            $tempSO[$order['order_id']]['U_Customer_Name'] = $order['customer_first_name'].' '.$order['customer_last_name'];
        }
        
        $orderIds = '['.implode(',',$orderIdArray).']';
        $orderItems = $lazada->getMultipleOrderItems($orderIds);
        foreach ($orderItems['data'] as $item) {
            $orderId = $item['order_id'];
            $mergedItem[$orderId] = [];

            foreach($item['order_items'] as $orderItem){
                $sku = $orderItem['sku'];
                $itemPrice = $orderItem['item_price'];
                if(array_key_exists($sku, $mergedItem[$orderId])) {
                    $mergedItem[$orderId][$sku]['Quantity'] += 1;
                } else {
                    $mergedItem[$orderId][$sku]['Quantity'] = 1;
                    $mergedItem[$orderId][$sku]['ItemCode'] = $sku;
                    $mergedItem[$orderId][$sku]['UnitPrice'] = $itemPrice;
                }
            }

            foreach ($mergedItem[$orderId] as $item) {
                $items[$orderId][] = [
                    'ItemCode' => $item['ItemCode'],
                    'Quantity' => $item['Quantity'],
                    "TaxCode" => 'T1',
                    'UnitPrice' => $item['UnitPrice']
                ];
            }

            $tempSO[$orderId]['DocumentLines'] = $items[$orderId];
        }

        foreach($tempSO as $key => $value){
            $finalSO = array_slice($tempSO[$key],0);
            $salesOrder = $odataClient->post('Orders',$finalSO); // Need to refactor this.
        }
    }
}
