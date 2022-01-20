<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Services\SapService;

trait SapItemsTrait
{
    public function sapItems($field)
    {
        $now = Carbon::now();
        $weekStartDate = $now->startOfWeek()->format('Y-m-d');
        
        $count = 0;
        $moreItems = true;
        $items = [];

        while($moreItems)
        {
            $sapItems = (new SapService())->getOdataClient()
                            ->select('ItemCode','ItemName','QuantityOnStock','ItemPrices','Valid','U_MPS_OLDSKU','U_TCHUB_INTEGRATION','U_UPDATE_INVENTORY', 'UpdateDate')
                            ->from('Items')
                            ->where('U_TCHUB_INTEGRATION','Y')
                            ->where($field, '>', $weekStartDate)
                            ->order('UpdateDate', 'desc')
                            ->skip($count)
                            ->get();

            if($sapItems->isNotEmpty())
            {
                foreach($sapItems as $item) {
                    $items[] = $item;
                }

                $count += count($sapItems);
            } else {
                $moreItems = false;
            }
        }
        
        return $items;
    }
}