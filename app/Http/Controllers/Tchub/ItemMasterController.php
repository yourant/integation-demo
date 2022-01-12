<?php

namespace App\Http\Controllers\Tchub;

use Exception;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Traits\SapItemsTrait;
use Grayloon\Magento\Magento;
use App\Services\TchubService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;


class ItemMasterController extends Controller
{
    use SapItemsTrait;
    
    public function updateItemStatus()
    {
        Log::channel('tchub')->info('TCHUB Item Master', ['data' => 'Updating item status...']);

        $successCount = 0;
        
        try {
            Log::channel('tchub')->info('TCHUB Item Master', ['data' => 'Fetching items from SAP']);
            $sapItems = $this->sapItems();
            Log::channel('tchub')->info('TCHUB Item Master', ['data' => 'Fetched ' . count($sapItems) . ' Item/s']);
            foreach ($sapItems as $item) {
                $itemCode = $item->U_MPS_OLDSKU ?? $item->ItemCode;
                
                $tchubService = new TchubService("/products/{$itemCode}");
                if ($item->Valid === 'tNO') {
                    $param = [
                        "product" => [
                            "status"=> 2,
                        ]
                    ];
                    
                    $response = Http::withToken($tchubService->getAccessToken())->put($tchubService->getFullPath(), $param);
                    if ($response->status() === 200)
                    {
                        $successCount++;
                        Log::channel('tchub')->info('TCHUB Item Master', ['data' => "Item {$item->U_MPS_OLDSKU} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Item Master', ['data' => "There was an error updating {$item->U_MPS_OLDSKU}. {$response->json()['message']}"]);
                    }
                } else {
                    $param = [
                        "product" => [
                            "status"=> 1,
                        ]
                    ];
                    
                    $response = Http::withToken($tchubService->getAccessToken())->put($tchubService->getFullPath(), $param);
                    if ($response->status() === 200)
                    {
                        $successCount++;
                        Log::channel('tchub')->info('TCHUB Item Master', ['data' => "Item {$item->U_MPS_OLDSKU} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Item Master', ['data' => "There was an error updating {$item->U_MPS_OLDSKU}. {$response->json()['message']}"]);
                    }
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Item Master', ['data' => $exception]);
        }
        
        return redirect()->route('tchub.dashboard')
            ->with('success', 'Item status have been updated.');
    }

    public function updatePrices()
    {
        try {
            $items = $this->sapItems();

            foreach ($items as $item) {
                $itemCode = $item->U_MPS_OLDSKU ?? $item->ItemCode;
                if ($item->U_UPDATE_INVENTORY === 'Y') {
                    $param = [
                        "product" => [
                            "sku" => $item->U_MPS_OLDSKU ?? $item->ItemCode,
                            "price" => $item->ItemPrices[9]['Price'],
                            "status" => 1,
                            "tier_prices" => [
                                [
                                    "customer_group_id" => 4,
                                    "qty" => 99,
                                    "value" => $item->ItemPrices[10]['Price']
                                ],
                                [
                                    "customer_group_id" => 5,
                                    "qty" => 99,
                                    "value" => $item->ItemPrices[11]['Price']
                                ],
                                [
                                    "customer_group_id" => 6,
                                    "qty" => 99,
                                    "value" => $item->ItemPrices[12]['Price']
                                ]
                            ],
                        ]
                    ];
                    
                    $tchubService = new TchubService("/products/{$itemCode}");
                    $results = Http::withToken($tchubService->getAccessToken())->put($tchubService->getFullPath(), $param);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', 'Item prices have been updated.');
    }

    public function updateStocks()
    {
        try {
            $items = $this->sapItems();
        
            foreach ($items as $item) {
                $itemCode = $item->U_MPS_OLDSKU ?? $item->ItemCode;
                if ($item->U_UPDATE_INVENTORY === 'Y') {
                    $param = [
                        "product" => [
                            "extension_attributes" => [
                                "stock_item" => [
                                    "qty" => $item->QuantityOnStock,
                                    "is_in_stock" => true
                                ]
                            ]
                        ]
                    ];
                    
                    $tchubService = new TchubService("/products/{$itemCode}");
                    $results = Http::withToken($tchubService->getAccessToken())->put($tchubService->getFullPath(), $param);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', 'Stocks have been updated.');
    }

    public function createProduct()
    {
        try {
            $items = $this->sapItems();
        
            $rowCount = 0;
            foreach ($items as $item) {
                $param = [
                    "product"=> [
                        "sku"=> $item->U_MPS_OLDSKU ?? $item->ItemCode,
                        "name"=> $item->ItemName,
                        "attribute_set_id"=> 4,
                        "price"=> $item->ItemPrices[9]['Price'],
                        "status"=> 1,
                        "visibility"=> 4,
                        "type_id"=> "simple",
                        "extension_attributes"=> [
                            "website_ids"=> [
                                1
                            ],
                            "stock_item"=> [
                                "qty"=> $item->QuantityOnStock,
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
                                "value"=> $item->ItemPrices[10]['Price'],
                                "extension_attributes"=> [
                                    "website_id"=> 0
                                ]
                            ],
                            [
                                "customer_group_id"=> 5,
                                "qty"=> 99,
                                "value"=> $item->ItemPrices[11]['Price'],
                                "extension_attributes"=> [
                                    "website_id"=> 0
                                ]
                            ],
                            [
                                "customer_group_id"=> 6,
                                "qty"=> 99,
                                "value"=> $item->ItemPrices[12]['Price'],
                                "extension_attributes"=> [
                                    "website_id"=> 0
                                ]
                            ]
                        ],
                        "custom_attributes"=> [
                            [
                                "attribute_code"=> "description",
                                "value"=> $item->ItemName
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

                $tchubService = new TchubService('/products', 'default');
                $results = Http::withToken($tchubService->getAccessToken())->post($tchubService->getFullPath(), $param);
                
            }
        } catch (Exception $exception) {
            //throw $th;
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', 'Product have been created.');
    }

    // public function updateStock()
    // {
    //     $items = $this->getAllItems();
        
    //     foreach ($items as $item) {
    //         if ($item['update_inventory'] == 'Y') {
    //             $param = [
    //                 "product" => [
    //                     "extension_attributes" => [
    //                         "stock_item" => [
    //                             "qty" => $item['quantity'],
    //                             "is_in_stock" => true
    //                         ]
    //                     ]
    //                 ]
    //             ];
    
    //             $results = Http::withToken(env('MAGENTO_ACCESS_TOKEN'))->put("https://test.tchub.sg/index.php/rest/V1/products/{$item['old_sku']}", $param);
                
    //         }
    //     }

    //     return response()->json([
    //         'message' => "Items stock have been updated"
    //     ], 200);
    // }

    // public function updateInactiveItems()
    // {
    //     $items = $this->getAllItems();

    //     foreach ($items as $item) {
    //         if ($item['Valid'] == 'tNO') {
    //             $param = [
    //                 "product" => [
    //                     "status"=> 2,
    //                 ]
    //             ];
    //             $results = Http::withToken(env('MAGENTO_ACCESS_TOKEN'))->put("https://test.tchub.sg/index.php/rest/V1/products/{$item['old_sku']}", $param);
    //         }
    //     }

    //     return response()->json([
    //         'message' => "Items have been updated"
    //     ], 200);
    // }

    // private function getAllItems()
    // {
    //     $count = 0;
    //     $moreItems = true;
    //     $items = [];

    //     while($moreItems){
    //         $sapItems = (new SapService())->getOdataClient()->from('Items')
    //                         ->where('U_TCHUB_INTEGRATION','Y')
    //                         ->skip($count)
    //                         ->get();

    //         if($sapItems->isNotEmpty())
    //         {
    //             foreach($sapItems as $item) {
    //                 $items[] = [
    //                     'update_inventory' => $item['U_UPDATE_INVENTORY'],
    //                     'old_sku' => $item['U_MPS_OLDSKU'],
    //                     'itemCode' => $item['ItemCode'],
    //                     'itemName' => $item['ItemName'],
    //                     'quantity' => $item['QuantityOnStock'],
    //                     'prices' => $item['ItemPrices'],
    //                     'valid' => $item['Valid']
    //                 ];
    //             }

    //             $count += count($sapItems);
    //         } else {
    //             $moreItems = false;
    //         }
    //     }
        
    //     return $items;
    // }
}
