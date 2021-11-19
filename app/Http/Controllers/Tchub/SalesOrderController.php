<?php

namespace App\Http\Controllers\Tchub;

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
        
        // $odata = new SapService();
        // // $udt = $odata->getOdataClient()->from(config('app.udt'))->get();
        
        // //get all pending orders from tchub.sg
        // $pending_orders = $this->getPendingOrders();
        // //check if the collection is not empty
        // if (!empty($pending_orders)) {
        //     //loop each item 
        //     foreach ($pending_orders['items'] as $pending_order) {
                
        //     }
        // }
    }

    private function getPendingOrders()
    {
        $magento = new Magento();
        $conditions = [
            'searchCriteria[filterGroups][0][filters][0][field]' => 'status',
            'searchCriteria[filterGroups][0][filters][0][value]' => 'pending',
            'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq'
        ];
        $response = $magento->api('orders')->all($pageSize = 50, $currentPage = 1, $filters = $conditions);
        return $response->json();
    }
}
