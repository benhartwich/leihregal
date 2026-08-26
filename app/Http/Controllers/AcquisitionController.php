<?php

namespace App\Http\Controllers;

use App\Models\AcquisitionSuggestion;
use App\Models\Media;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcquisitionController extends Controller
{
    public function pdf()
    {
        $items = AcquisitionSuggestion::whereIn('status', ['offen', 'bestellt'])
            ->orderBy('status')
            ->orderBy('title')
            ->get();

        $pdf = Pdf::loadView('pdf.acquisitions', ['items' => $items]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('anschaffungsliste-' . now()->format('Y-m-d') . '.pdf');
    }

    public function csv(): StreamedResponse
    {
        $items = AcquisitionSuggestion::whereIn('status', ['offen', 'bestellt'])
            ->orderBy('status')
            ->orderBy('title')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="anschaffungsliste-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Titel', 'Autor', 'Verlag', 'ISBN', 'Preis (€)', 'Quelle', 'Status', 'Begründung'], ';');

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->title,
                    $item->author ?? '',
                    $item->publisher ?? '',
                    $item->isbn ?? '',
                    $item->price_estimate ? number_format($item->price_estimate, 2, ',', '.') : '',
                    $item->source === 'ki' ? 'KI-Analyse' : 'Wunsch',
                    $item->status->label(),
                    $item->reason,
                ], ';');
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function inventoryPdf()
    {
        $media = Media::whereNotIn('status', ['verloren', 'ausgemustert'])
            ->with('tags')
            ->orderBy('title')
            ->get();

        $pdf = Pdf::loadView('pdf.inventory', ['media' => $media]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('bestandsliste-' . now()->format('Y-m-d') . '.pdf');
    }
}
