<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use App\Http\Controllers\Tchub\TchubController;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TchubTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_index()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_can_view_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(action([TchubController::class, 'index']));

        $response->assertStatus(200);
    }
}
