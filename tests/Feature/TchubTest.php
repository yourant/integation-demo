<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\TchubService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Controllers\Tchub\DashboardController;
use App\Http\Controllers\Tchub\SalesProcessController;

class TchubTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_tchub_config_setting_in_the_laravel_config()
    {
        $this->assertEquals(config('app.tchub_base_url'), 'https://test.tchub.sg/index.php');
        $this->assertEquals(config('app.tchub_base_path'), 'rest');
        $this->assertEquals(config('app.tchub_api_version'), 'V1');
        $this->assertNotEmpty(config('app.tchub_access_token'));
    }

    public function test_check_sap_config_setting_in_the_laravel_config()
    {
        $this->assertNotEmpty(config('app.sap_db'));
        $this->assertNotEmpty(config('app.sap_user'));
        $this->assertNotEmpty(config('app.sap_pword'));
        $this->assertNotEmpty(config('app.sap_path'));
        $this->assertNotEmpty(config('app.sap_udt'));
    }

    public function test_can_view_index()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_can_view_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(action(DashboardController::class));

        $response->assertOk();
    }

    public function test_can_call_magento_api_get_pending_orders_all_with_filter()
    {
        Http::fake([
            '*rest/V1/orders*' => Http::response([], 200),
        ]);
        
        $conditions = "?searchCriteria[filterGroups][0][filters][0][field]=status&searchCriteria[filterGroups][0][filters][0][value]=pending&searchCriteria[filterGroups][0][filters][0][conditionType]=eq";
        $tchubService = new TchubService("/orders/{$conditions}");
        $response = Http::withToken($tchubService->getAccessToken())->get($tchubService->getFullPath());

        $this->assertTrue($response->ok());
    }

    public function test_can_call_magento_api_update_order_pending_status_to_processing()
    {
        Http::fake([
            '*rest/default/V1/order*' => Http::response([], 200),
        ]);
        
        $param = [
            "capture" => true,
            "notify" => true
        ];
        $tchubService = new TchubService("/order/1/invoice", 'default');
        $response = Http::withToken($tchubService->getAccessToken())->post($tchubService->getFullPath(), $param);

        $this->assertTrue($response->ok());
    }

    public function test_can_call_magento_api_update_order_processing_status_to_complete()
    {
        Http::fake([
            '*rest/default/V1/order*' => Http::response([], 200),
        ]);
        
        $param = [
            "items" => [
                "order_item_id" => 1,
                "qty" => 1
            ],
            "notify" => true
        ];
        $tchubService = new TchubService("/order/1/ship", 'default');
        $response = Http::withToken($tchubService->getAccessToken())->post($tchubService->getFullPath(), $param);

        $this->assertTrue($response->ok());
    }

    public function test_can_call_magento_api_get_canceled_orders_all_with_filter()
    {
        Http::fake([
            '*rest/V1/orders*' => Http::response([], 200),
        ]);
        
        $conditions = "?searchCriteria[filterGroups][0][filters][0][field]=status&searchCriteria[filterGroups][0][filters][0][value]=canceled&searchCriteria[filterGroups][0][filters][0][conditionType]=eq";
        $tchubService = new TchubService("/orders/{$conditions}");
        $response = Http::withToken($tchubService->getAccessToken())->get($tchubService->getFullPath());

        $this->assertTrue($response->ok());
    }
}
