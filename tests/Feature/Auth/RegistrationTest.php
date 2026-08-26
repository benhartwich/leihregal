<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Selbstregistrierung ist ein ausdrückliches Nicht-Ziel (Spec 4.7):
 * Konten legt ausschließlich der Admin an. Dieser Test hält das fest,
 * damit eine versehentlich reaktivierte Route auffällt.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrierungsseite_ist_nicht_erreichbar(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_es_gibt_keine_register_route(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('register'),
            'Es existiert eine benannte Route "register" – Selbstregistrierung ist laut Spec 4.7 nicht vorgesehen.'
        );
    }
}
