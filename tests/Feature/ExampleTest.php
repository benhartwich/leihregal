<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `/` liefert bewusst keinen Inhalt, sondern ist eine Weiche
     * (siehe routes/web.php).
     */
    public function test_root_leitet_gaeste_zum_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_root_leitet_angemeldete_zum_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_healthz_meldet_status_ok(): void
    {
        $this->get('/healthz')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'db' => 'ok']);
    }
}
