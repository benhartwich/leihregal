<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Etiketten-Erzeugung (Spec 4.1): Code128, 30 pro A4, als PDF.
 *
 * Vorher waren es QR-Codes in einer HTML-Druckansicht. Der Wechsel auf
 * Code128 nutzt `picqer/php-barcode-generator`, das bis dahin ungenutzt
 * installiert war.
 */
class BarcodeLabelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Holt den Text aus dem PDF zurück. dompdf legt Seiteninhalte als
     * zlib-komprimierte Streams ab.
     */
    private function pdfText(TestResponse $response): string
    {
        $roh  = $response->getContent();
        $text = '';

        if (preg_match_all("/stream\r?\n(.*?)\r?\nendstream/s", $roh, $treffer)) {
            foreach ($treffer[1] as $stream) {
                $entpackt = @gzuncompress($stream);
                if ($entpackt !== false) {
                    $text .= $entpackt;
                }
            }
        }

        return $text;
    }

    private function seitenzahl(TestResponse $response): int
    {
        preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent(), $treffer);

        return count($treffer[0]);
    }

    public function test_einzeletikett_ist_ein_pdf(): void
    {
        $kurator = User::factory()->kurator()->create();
        $media   = Media::factory()->create(['internal_code' => 'LIB-424242']);

        $response = $this->actingAs($kurator)
            ->get(route('media.barcode', $media))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(1, $this->seitenzahl($response));
        $this->assertStringContainsString('LIB-424242', $this->pdfText($response));
    }

    public function test_sammelbogen_enthaelt_alle_aktiven_medien(): void
    {
        $kurator = User::factory()->kurator()->create();

        Media::factory()->create(['internal_code' => 'LIB-100001']);
        Media::factory()->create(['internal_code' => 'LIB-100002']);
        Media::factory()->ausgemustert()->create(['internal_code' => 'LIB-900001']);
        Media::factory()->verloren()->create(['internal_code' => 'LIB-900002']);

        $response = $this->actingAs($kurator)
            ->get(route('media.barcode.batch'))
            ->assertOk();

        $text = $this->pdfText($response);

        $this->assertStringContainsString('LIB-100001', $text);
        $this->assertStringContainsString('LIB-100002', $text);
        $this->assertStringNotContainsString('LIB-900001', $text, 'Ausgemustertes Medium im Etikettenbogen.');
        $this->assertStringNotContainsString('LIB-900002', $text, 'Verlorenes Medium im Etikettenbogen.');
    }

    /**
     * Spec 4.1 verlangt 30 Etiketten je A4-Bogen. Weicht das Raster ab,
     * passen die Etiketten nicht mehr auf handelsübliche Bogen.
     */
    public function test_dreissig_etiketten_passen_auf_einen_bogen(): void
    {
        $kurator = User::factory()->kurator()->create();
        Media::factory()->count(30)->create();

        $response = $this->actingAs($kurator)->get(route('media.barcode.batch'))->assertOk();

        $this->assertSame(1, $this->seitenzahl($response), '30 Etiketten müssen auf einen Bogen passen.');
    }

    public function test_einunddreissig_etiketten_ergeben_zwei_bogen(): void
    {
        $kurator = User::factory()->kurator()->create();
        Media::factory()->count(31)->create();

        $response = $this->actingAs($kurator)->get(route('media.barcode.batch'))->assertOk();

        $this->assertSame(2, $this->seitenzahl($response));
    }

    public function test_sammelbogen_ohne_medien_liefert_404(): void
    {
        $kurator = User::factory()->kurator()->create();

        $this->actingAs($kurator)
            ->get(route('media.barcode.batch'))
            ->assertNotFound();
    }

    public function test_betreuer_darf_keine_etiketten_erzeugen(): void
    {
        $betreuer = User::factory()->create();
        $media    = Media::factory()->create();

        $this->actingAs($betreuer)
            ->get(route('media.barcode', $media))
            ->assertForbidden();
    }
}
