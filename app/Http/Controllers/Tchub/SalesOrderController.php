<?php

namespace App\Http\Controllers\Tchub;

use Carbon\Carbon;
use App\Services\SapService;
use Illuminate\Http\Request;
use Grayloon\Magento\Magento;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class SalesOrderController extends Controller
{
    public function generateSalesOrder()
    {
        $orders = [];
        $currentPage = 1;
        $morePendingOrders = true;
        $rowCount = 0;
        while($morePendingOrders)
        {
            $pending_orders = $this->getPendingOrders($currentPage);
            
            foreach ($pending_orders['items'] as $pending_order) {
                array_push($orders, $pending_order);
            }

            if (count($orders) == $pending_orders['total_count']) {
                $morePendingOrders = false;
            }

            $currentPage++;
        }
        if (!empty($orders)) {
            $odata = new SapService();
            $udt = $this->udt($odata);
            foreach ($orders as $order) {
                $document_line = [];

                $so = $odata->getOdataClient()
                    ->select('DocNum')
                    ->from('Orders')
                    ->where('U_Order_ID', (string)$order['entity_id'])
                    ->where('U_Ecommerce_Type', 'TCHUB')
                    ->where('DocumentStatus', 'O')
                    ->first();

                if (is_null($so))
                {
                    foreach ($order['items'] as $item) {
                        if ($this->itemExist($item['sku'], $odata))
                        {
                            $document_line[] = [
                                'ItemCode' => $this->itemExist($item['sku'], $odata),
                                'Quantity' => $item['qty_ordered'],
                                'UnitPrice' => (float) $item['price'] / (float) $udt['PERCENTAGE'],
                                'WarehouseCode' => $udt['WAREHOUSE_CODE'],
                                'U_TCHUB_ITEM_ID' => $item['item_id'],
                            ];
                        }
                    }

                    if ($this->hasShippingFee($order['shipping_amount'], $udt)) {
                        array_push($document_line, $this->hasShippingFee($order['shipping_amount'], $udt));
                    }

                    $billing = $order['billing_address'];
                    $address = "{$billing['firstname']} {$billing['lastname']}\n{$billing['street'][0]}\n{$billing['city']} {$billing['postcode']}\n{$billing['country_id']}\nT: {$billing['telephone']}";
                    $params = [
                        'CardCode' => $udt['TCHUB_CUSTOMER'],
                        'DocDate' => Carbon::parse($order['created_at'])->format('Y-m-d'),
                        'DocDueDate' => Carbon::parse($order['created_at'])->format('Y-m-d'),
                        'NumAtCard' => $order['increment_id'],
                        'U_Ecommerce_Type' => 'TCHUB',
                        'U_Order_ID' => $order['entity_id'],
                        'U_Customer_Name' => "{$billing['firstname']} {$billing['lastname']}",
                        'U_Customer_Email' => $order['customer_email'],
                        'U_Shipping_Address' => $address,
                        'U_Billing_Address' => $address,
                        'DocTotal' => $order['grand_total'],
                        "DocumentLines" => $document_line
                    ];
                    
                    $response = $odata->getOdataClient()->post('Orders', $params);
                    $rowCount++;
                }
            }
            
        }

        return response()->json([
            'message' => "{$rowCount} sales order have been generated"
        ], 201);
    }

    private function getPendingOrders($currentPage)
    {
        $conditions = [
            'searchCriteria[filterGroups][0][filters][0][field]' => 'status',
            'searchCriteria[filterGroups][0][filters][0][value]' => 'pending',
            'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq'
        ];

        $response = (new Magento())->api('orders')->all($pageSize = 50, $currentPage = $currentPage, $filters = $conditions);
        $results = $response->json();
        
        return $results;
    }

    private function udt($odata)
    {
        $udt = [];
        $results = $odata->getOdataClient()
                    ->from(config('app.sap_udt'))
                    ->get();
        foreach ($results as $value) {
            $udt[$value['properties']['Code']] = $value['properties']['Name'];
        }
        
        return $udt;
    }

    private function hasShippingFee($shipping_amount, $udt)
    {
        if ($shipping_amount > 0)
        {
            return [
                'ItemCode' => $udt['SHIPPING_FEE'],
                'Quantity' =>  1,
                'UnitPrice' => $shipping_amount / (float) $udt['PERCENTAGE'],
                'VatGroup' => $udt['TAX_CODE'],
                'WarehouseCode' => $udt['WAREHOUSE_CODE']
            ];
        }

        return false;
    }

    private function itemExist($sku, $odata)
    {
        $item = $odata->getOdataClient()
            ->from('Items')
            ->whereNested(function($query) use ($sku) {
                $query->where('ItemCode', $sku)
                    ->orWhere('U_MPS_OLDSKU', $sku);
            })->where('U_TCHUB_INTEGRATION', 'Y')
            ->first();
        
        return !is_null($item) ? $item['ItemCode'] : false;
    }
}
