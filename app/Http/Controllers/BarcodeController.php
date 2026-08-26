<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Etiketten für den internen Barcode (Spec 4.1).
 *
 * Code128 als PNG, 30 Etiketten je A4-Bogen (Raster Avery/Zweckform 3474),
 * ausgegeben als PDF. Code128 ist bewusst gewählt: Handelsübliche
 * Laser-Handscanner lesen 1D-Codes zuverlässiger als QR, und die Etiketten
 * sind schmal genug für Buchrücken.
 */
class BarcodeController extends Controller
{
    public function single(Media $media)
    {
        $labels = $this->buildLabels(collect([$media]));

        return Pdf::loadView('pdf.barcode-labels', ['labels' => $labels])
            ->setPaper('a4')
            ->stream($this->dateiname("etikett-{$media->internal_code}"));
    }

    public function batch()
    {
        $medien = Media::whereNotIn('status', ['verloren', 'ausgemustert'])
            ->orderBy('title')
            ->get(['id', 'internal_code', 'title']);

        if ($medien->isEmpty()) {
            abort(404, 'Keine Medien vorhanden.');
        }

        $labels = $this->buildLabels($medien);

        return Pdf::loadView('pdf.barcode-labels', ['labels' => $labels])
            ->setPaper('a4')
            ->stream($this->dateiname('etiketten-bestand'));
    }

    /**
     * @param  Collection<int, Media>  $medien
     * @return Collection<int, array{code: string, title: string, barcode: string}>
     */
    private function buildLabels(Collection $medien): Collection
    {
        $generator = new BarcodeGeneratorPNG();

        return $medien->map(function (Media $medium) use ($generator) {
            // widthFactor 2 / Höhe 60 px ergibt bei 54 mm Druckbreite eine
            // Strichstärke, die Handscanner sicher auflösen.
            $png = $generator->getBarcode(
                $medium->internal_code,
                BarcodeGeneratorPNG::TYPE_CODE_128,
                2,
                60,
            );

            return [
                'code'  => $medium->internal_code,
                'title' => mb_substr($medium->title, 0, 60),
                // Als Daten-URI einbetten: dompdf müsste eine Datei sonst vom
                // Dateisystem lesen, was für hunderte Etiketten unnötig wäre.
                'barcode' => 'data:image/png;base64,' . base64_encode($png),
            ];
        })->values();
    }

    private function dateiname(string $basis): string
    {
        return $basis . '-' . now()->format('Y-m-d') . '.pdf';
    }
}
