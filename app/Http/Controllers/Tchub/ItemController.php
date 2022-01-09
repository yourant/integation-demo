<?php

namespace App\Http\Controllers\Tchub;

use App\Services\SapService;
use Illuminate\Http\Request;
use Grayloon\Magento\Magento;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class ItemController extends Controller
{
    public function itemSync()
    {
        $items = $this->getAllItems();
        
        $rowCount = 0;
        foreach ($items as $item) {
            $param = [
                "product"=> [
                    "sku"=> $item['itemCode'],
                    "name"=> $item['itemName'],
                    "attribute_set_id"=> 4,
                    "price"=> $item['prices'][6]['Price'],
                    "status"=> 1,
                    "visibility"=> 4,
                    "type_id"=> "simple",
                    "extension_attributes"=> [
                        "website_ids"=> [
                            1
                        ],
                        "stock_item"=> [
                            "qty"=> $item['quantity'],
                            "is_in_stock"=> true,
                        ]
                    ],
                    "product_links"=> [],
                    "options"=> [],
                    "media_gallery_entries"=> [],
                    "tier_prices"=> [
                        [
                            "customer_group_id"=> 4,
                            "qty"=> 99,
                            "value"=> $item['prices'][5]['Price'],
                            "extension_attributes"=> [
                                "website_id"=> 0
                            ]
                        ],
                        [
                            "customer_group_id"=> 5,
                            "qty"=> 99,
                            "value"=> $item['prices'][7]['Price'],
                            "extension_attributes"=> [
                                "website_id"=> 0
                            ]
                        ],
                        [
                            "customer_group_id"=> 6,
                            "qty"=> 99,
                            "value"=> $item['prices'][8]['Price'],
                            "extension_attributes"=> [
                                "website_id"=> 0
                            ]
                        ]
                    ],
                    "custom_attributes"=> [
                        [
                            "attribute_code"=> "description",
                            "value"=> $item['itemName']
                        ],
                        [
                            "attribute_code"=> "tax_class_id",
                            "value"=> "0"
                        ],
                        [
                            "attribute_code"=> "material",
                            "value"=> "148"
                        ],
                        [
                            "attribute_code"=> "pattern",
                            "value"=> "196"
                        ],
                        [
                            "attribute_code"=> "color",
                            "value"=> "52"
                        ],
                        [
                            "attribute_code"=> "size",
                            "value"=> "168"
                        ]
                    ]
                ]
            ];

            
            $results = Http::withToken(env('MAGENTO_ACCESS_TOKEN'))->post('https://test.tchub.sg/index.php/rest/default/V1/products', $param);
            if ($results->status() === 200) {
                $rowCount++;
            }
        }

        return response()->json([
            'message' => "{$rowCount} Item/s have been created"
        ], 201);
    }

    public function updateStock()
    {
        $items = $this->getAllItems();
        
        foreach ($items as $item) {
            if ($item['update_inventory'] == 'Y') {
                $param = [
                    "product" => [
                        "extension_attributes" => [
                            "stock_item" => [
                                "qty" => $item['quantity'],
                                "is_in_stock" => true
                            ]
                        ]
                    ]
                ];
    
                $results = Http::withToken(env('MAGENTO_ACCESS_TOKEN'))->put("https://test.tchub.sg/index.php/rest/V1/products/{$item['old_sku']}", $param);
                
            }
        }

        return response()->json([
            'message' => "Items stock have been updated"
        ], 200);
    }

    public function updateInactiveItems()
    {
        $items = $this->getAllItems();

        foreach ($items as $item) {
            if ($item['Valid'] == 'tNO') {
                $param = [
                    "product" => [
                        "status"=> 2,
                    ]
                ];
                $results = Http::withToken(env('MAGENTO_ACCESS_TOKEN'))->put("https://test.tchub.sg/index.php/rest/V1/products/{$item['old_sku']}", $param);
            }
        }

        return response()->json([
            'message' => "Items have been updated"
        ], 200);
    }

    private function getAllItems()
    {
        $count = 0;
        $moreItems = true;
        $items = [];

        while($moreItems){
            $sapItems = (new SapService())->getOdataClient()->from('Items')
                            ->where('U_TCHUB_INTEGRATION','Y')
                            ->skip($count)
                            ->get();

            if($sapItems->isNotEmpty())
            {
                foreach($sapItems as $item) {
                    $items[] = [
                        'update_inventory' => $item['U_UPDATE_INVENTORY'],
                        'old_sku' => $item['U_MPS_OLDSKU'],
                        'itemCode' => $item['ItemCode'],
                        'itemName' => $item['ItemName'],
                        'quantity' => $item['QuantityOnStock'],
                        'prices' => $item['ItemPrices'],
                        'valid' => $item['Valid']
                    ];
                }

                $count += count($sapItems);
            } else {
                $moreItems = false;
            }
        }
        
        return $items;
    }
}
