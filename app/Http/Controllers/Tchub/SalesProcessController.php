<?php

namespace App\Http\Controllers\Tchub;

use Carbon\Carbon;
use App\Services\SapService;
use Illuminate\Http\Request;
use Grayloon\Magento\Magento;
use App\Services\TchubService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;

class SalesProcessController extends Controller
{
    public function generateSalesOrder()
    {
        Log::channel('tchub')->info('TCHUB Generate Sales Order', ['data' => 'Generating sales order...']);

        $orders = [];

        $successCount = 0;

        try {
            Log::channel('tchub')->info('TCHUB Generate Sales Order', ['data' => 'Fetching items from tchub.sg...']);
            $pending_orders = $this->getPendingOrders('pending');
            
            foreach ($pending_orders['items'] as $pending_order) {
                array_push($orders, $pending_order);
            }
            Log::channel('tchub')->info('TCHUB Generate Sales Order', ['data' => 'Fetched ' . count($orders) . ' Item/s']);
            
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
                        ->where('CancelStatus', 'csNo')
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
                       
                        if (count($response) > 0)
                        {
                            $successCount++;
                            Log::channel('tchub')->info('TCHUB Generate Sales Order', ['data' => "Item {$order['increment_id']} have been generated."]);
                        } else {
                            Log::channel('tchub')->info('TCHUB Generate Sales Order', ['data' => "There was an error generatig order# {$order['increment_id']}."]);
                        }
                    }
                }
                
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Generate Sales Order', ['data' => $exception]);
        }
        

        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} Sales order have been created.");
    }

    public function deliveryOrder()
    {
        Log::channel('tchub')->info('TCHUB Update Delivery Order', ['data' => 'Updating delivery order status...']);

        $successCount = 0;

        try {
            Log::channel('tchub')->info('TCHUB Update Delivery Order', ['data' => 'Fetching items from SAP']);
            $deliveryOrders = (new SapService())->getOdataClient()
                        ->from('DeliveryNotes')
                        ->where('U_Ecommerce_Type', 'TCHUB')
                        ->where('DocumentStatus', 'O')
                        ->get();

            Log::channel('tchub')->info('TCHUB Update Delivery Order', ['data' => 'Fetched ' . count($deliveryOrders) . ' Item/s']);

            $param = [
                "capture" => true,
                "notify" => true
            ];
            foreach ($deliveryOrders as $do) {
                $tchubService = new TchubService("/order/{$do->U_Order_ID}/invoice", 'default');
                $response = Http::withToken($tchubService->getAccessToken())->post($tchubService->getFullPath(), $param);
                if ($response->status() === 200)
                {
                    $successCount++;
                    Log::channel('tchub')->info('TCHUB Update Delivery Order', ['data' => "Order ID# {$do->U_Order_ID} have been updated."]);
                } else {
                    Log::channel('tchub')->info('TCHUB Update Delivery Order', ['data' => "There was an error updating {$do->U_Order_ID}. {$response->json()['message']}"]);
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Update Delivery Order', ['data' => $exception]);
        }
        
        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} order/s status have been updated.");
    }

    public function arInvoice()
    {
        Log::channel('tchub')->info('TCHUB Update AR Invoice', ['data' => 'Updating AR invoice status...']);

        $successCount = 0;

        try {
            Log::channel('tchub')->info('TCHUB Update AR Invoice', ['data' => 'Fetching items from SAP']);
            $arInvoices = (new SapService())->getOdataClient()
                        ->from('Invoices')
                        ->where('U_Ecommerce_Type', 'TCHUB')
                        ->where('DocumentStatus', 'O')
                        ->get();
                        Log::channel('tchub')->info('TCHUB Update AR Invoice', ['data' => 'Fetched ' . count($arInvoices) . ' Item/s']);

            $param = [
                "items" => [],
                "notify" => true
            ];
            foreach ($arInvoices as $ar) {
                foreach ($ar->DocumentLines as $dl) {
                    array_push($param['items'], [
                        "order_item_id" => $dl['U_TCHUB_ITEM_ID'] ?? 0,
                        "qty" => $dl['Quantity']
                    ]);
                    $tchubService = new TchubService("/order/{$ar['U_Order_ID']}/ship", 'default');
                    $response = Http::withToken($tchubService->getAccessToken())->post($tchubService->getFullPath(), $param);
                    if ($response->status() === 200)
                    {
                        $successCount++;
                        Log::channel('tchub')->info('TCHUB Update AR Invoice', ['data' => "Order ID# {$ar['U_Order_ID']} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Update AR Invoice', ['data' => "There was an error updating {$ar['U_Order_ID']}. {$response->json()['message']}"]);
                    }
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Update AR Invoice', ['data' => $exception]);
        }
        
        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} order/s status have been updated.");
    }

    public function canceledOrder()
    {
        Log::channel('tchub')->info('TCHUB Cancel Order', ['data' => 'Canceling orders...']);

        $successCount = 0;

        $canceled_items = [];

        try {
            Log::channel('tchub')->info('TCHUB Cancel Order', ['data' => 'Fetching items from tchub.sg']);
            $canceled_orders = $this->getPendingOrders('canceled');

            foreach ($canceled_orders['items'] as $canceled_item) {
                array_push($canceled_items, $canceled_item);
            }

            Log::channel('tchub')->info('TCHUB Cancel Order', ['data' => 'Fetched ' . count($canceled_items) . ' Item/s']);

            if (!empty($canceled_items))
            {
                foreach ($canceled_orders['items'] as $co) {
                    
                    $so = (new SapService())->getOdataClient()
                        ->select('DocEntry', 'U_Order_ID')
                        ->from('Orders')
                        ->where('U_Order_ID', (string)$co['entity_id'])
                        ->where('U_Ecommerce_Type', 'TCHUB')
                        ->where('DocumentStatus', 'O')
                        ->first();

                    if(!is_null($so))
                    {
                        $response = (new SapService())->getOdataClient()->post("Orders({$so->DocEntry})/Close", []);
                        if (count($response) > 0)
                        {
                            $successCount++;
                            Log::channel('tchub')->info('TCHUB Cancel Order', ['data' => "Order ID# {$co['entity_id']} have been canceled."]);
                        } else {
                            Log::channel('tchub')->info('TCHUB Cancel Order', ['data' => "There was an error updating {$co['entity_id']}. {$response->json()['message']}"]);
                        }
                    }
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: TCHUB Cancel Order', ['data' => $exception]);
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} order/s have been canceled.");
    }

    private function getPendingOrders($status)
    {
        $conditions = "?searchCriteria[filterGroups][0][filters][0][field]=status&searchCriteria[filterGroups][0][filters][0][value]={$status}&searchCriteria[filterGroups][0][filters][0][conditionType]=eq";
        $tchubService = new TchubService("/orders/{$conditions}");
        $response = Http::withToken($tchubService->getAccessToken())->get($tchubService->getFullPath());

        return $response->json();
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

    public function view()
    {
        $items = $this->getPendingOrders('pending');
        return view('tchub.all-pending-orders', compact('items'));
    }

    public function store($entity_id)
    {
        Log::channel('tchub')->info('TCHUB Generate Single Sales Order', ['data' => 'Generating sales order...']);

        try {
            Log::channel('tchub')->info('TCHUB Generate Single Sales Order', ['data' => 'Fetching order from tchub.sg']);
            $tchubService = new TchubService("/orders/{$entity_id}");

            $response = Http::withToken($tchubService->getAccessToken())->get($tchubService->getFullPath());

            $order = $response->json();

            $odata = new SapService();
            $udt = $this->udt($odata);

            $document_line = [];

            $so = $odata->getOdataClient()
                ->select('DocNum')
                ->from('Orders')
                ->where('U_Order_ID', (string)$order['entity_id'])
                ->where('U_Ecommerce_Type', 'TCHUB')
                ->where('CancelStatus', 'csNo')
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
                return redirect()->route('tchub.pending.orders.index')
                    ->with('success', "Reference ID: {$order['increment_id']} have been generated.");
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: TCHUB Generate Single Sales Order', ['data' => $exception]);
        }
        

        return redirect()->route('tchub.pending.orders.index')
            ->with('error', "Reference ID: {$order['increment_id']} is already existed on SAP B1.");
    }
}
