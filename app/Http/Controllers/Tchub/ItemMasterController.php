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
        Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => 'Updating item status...']);

        $successCount = 0;
        
        try {
            Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => 'Fetching items from SAP']);
            $sapItems = $this->sapItems('UpdateDate');
            Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => 'Fetched ' . count($sapItems) . ' Item/s']);
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
                        Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => "Item {$item->U_MPS_OLDSKU} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => "There was an error updating {$item->U_MPS_OLDSKU}. {$response->json()['message']}"]);
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
                        Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => "Item {$itemCode} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Update Item Status', ['data' => "There was an error updating {$itemCode}. {$response->json()['message']}"]);
                    }
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Update Item Status', ['data' => $exception]);
        }
        
        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} Item/s have been updated.");
    }

    public function updatePrices()
    {
        Log::channel('tchub')->info('TCHUB Update Price', ['data' => 'Updating item price...']);

        $successCount = 0;

        try {
            Log::channel('tchub')->info('TCHUB Update Price', ['data' => 'Fetching items from SAP B1...']);
            $sapItems = $this->sapItems('UpdateDate');
            Log::channel('tchub')->info('TCHUB Update Price', ['data' => 'Fetched ' . count($sapItems) . ' Item/s']);
            foreach ($sapItems as $item) {
                $itemCode = $item->U_MPS_OLDSKU ?? $item->ItemCode;
                if ($item->U_UPDATE_INVENTORY === 'Y') {
                    $param = [
                        "product" => [
                            "sku" => $itemCode,
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
                    $response = Http::withToken($tchubService->getAccessToken())->put($tchubService->getFullPath(), $param);
                    if ($response->status() === 200)
                    {
                        $successCount++;
                        Log::channel('tchub')->info('TCHUB Update Price', ['data' => "Item {$itemCode} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Update Price', ['data' => "There was an error updating {$itemCode}. {$response->json()['message']}"]);
                    }
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Update Price', ['data' => $exception]);
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} Item/s have been updated.");
    }

    public function updateStocks()
    {
        Log::channel('tchub')->info('TCHUB Update Stock', ['data' => 'Updating item stock...']);

        $successCount = 0;
        
        try {
            Log::channel('tchub')->info('TCHUB Update Stock', ['data' => 'Fetching items from SAP B1...']);
            $sapItems = $this->sapItems('UpdateDate');
            Log::channel('tchub')->info('TCHUB Update Stock', ['data' => 'Fetched ' . count($sapItems) . ' Item/s']);
            foreach ($sapItems as $item) {
                $itemCode = $item->U_MPS_OLDSKU ?? $item->ItemCode;
                if ($item->U_UPDATE_INVENTORY === 'Y' && $item->U_UPDATE_INVENTORY === null) {
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
                    $response = Http::withToken($tchubService->getAccessToken())->put($tchubService->getFullPath(), $param);
                    if ($response->status() === 200)
                    {
                        $successCount++;
                        Log::channel('tchub')->info('TCHUB Update Stock', ['data' => "Item {$itemCode} have been updated."]);
                    } else {
                        Log::channel('tchub')->info('TCHUB Update Stock', ['data' => "There was an error updating {$itemCode}. {$response->json()['message']}"]);
                    }
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Update Stock', ['data' => $exception]);
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} Item/s have been updated.");
    }

    public function createProduct()
    {
        Log::channel('tchub')->info('TCHUB Create Product', ['data' => 'Creating Product...']);

        $successCount = 0;

        try {
            Log::channel('tchub')->info('TCHUB Create Product', ['data' => 'Fetching items from SAP B1...']);
            $sapItems = $this->sapItems('CreateDate');
            Log::channel('tchub')->info('TCHUB Create Product', ['data' => 'Fetched ' . count($sapItems) . ' Item/s']);
            
            foreach ($sapItems as $item) {
                $itemCode = $item->U_MPS_OLDSKU ?? $item->ItemCode;
                $param = [
                    "product"=> [
                        "sku"=> $itemCode,
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
                $response = Http::withToken($tchubService->getAccessToken())->post($tchubService->getFullPath(), $param);
                if ($response->status() === 200)
                {
                    $successCount++;
                    Log::channel('tchub')->info('TCHUB Create Product', ['data' => "Item {$itemCode} have been updated."]);
                } else {
                    Log::channel('tchub')->info('TCHUB Create Product', ['data' => "There was an error creating {$itemCode}. {$response->json()['message']}"]);
                }
            }
        } catch (ClientException $exception) {
            Log::channel('tchub')->error('Error: Create Product', ['data' => $exception]);
        }

        return redirect()->route('tchub.dashboard')
            ->with('success', "{$successCount} Item/s have been created.");
    }
}
