<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBooksService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.google_books.key');
    }

    public function lookupIsbn(string $isbn): ?array
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));

        if (empty($isbn)) {
            return null;
        }

        // Normalise ISBN-10 → ISBN-13
        if (strlen($isbn) === 10) {
            $isbn = $this->isbn10to13($isbn);
        }

        // Try sources in order — first one with data wins
        $data = $this->fromGoogleBooks($isbn)
             ?? $this->fromDNB($isbn)
             ?? $this->fromOpenLibrary($isbn)
             ?? $this->fromOpenLibrarySearch($isbn)
             ?? $this->fromISBNdb($isbn);

        // Cover fallback chain
        if ($data && empty($data['cover_url'])) {
            $data['cover_url'] = $this->dnbCoverUrl($isbn)
                              ?? $this->openLibraryCoverById($isbn)
                              ?? $this->directCoverUrl($isbn)
                              ?? $this->amazonCoverUrl($isbn);
        }

        // If no metadata found at all, still try to return a cover so the user
        // can confirm they scanned the right book and fill in details manually
        if (! $data) {
            $cover = $this->dnbCoverUrl($isbn)
                  ?? $this->openLibraryCoverById($isbn)
                  ?? $this->directCoverUrl($isbn)
                  ?? $this->amazonCoverUrl($isbn);

            if ($cover) {
                return [
                    'title'       => null,
                    'author'      => null,
                    'publisher'   => null,
                    'year'        => null,
                    'language'    => 'de',
                    'cover_url'   => $cover,
                    'description' => null,
                ];
            }
        }

        return $data;
    }

    private function isbn10to13(string $isbn10): string
    {
        $body = '978' . substr($isbn10, 0, 9);
        $sum  = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return $body . ((10 - ($sum % 10)) % 10);
    }

    // ── Deutsche Nationalbibliothek (best for German books) ───────────────────

    private function fromDNB(string $isbn): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://services.dnb.de/sru/dnb', [
                'version'        => '1.1',
                'operation'      => 'searchRetrieve',
                'query'          => 'isbn=' . $isbn,
                'recordSchema'   => 'MARC21-xml',
                'maximumRecords' => 1,
            ]);

            if (! $response->successful()) return null;

            $xml = $response->body();

            if (! str_contains($xml, '<numberOfRecords>1</numberOfRecords>')) return null;

            $title     = $this->marcSubfield($xml, '245', 'a');
            $author    = $this->marcSubfield($xml, '100', 'a') ?? $this->marcSubfield($xml, '700', 'a');
            $publisher = $this->marcSubfield($xml, '264', 'b') ?? $this->marcSubfield($xml, '260', 'b');
            $yearRaw   = $this->marcSubfield($xml, '264', 'c') ?? $this->marcSubfield($xml, '260', 'c');
            $langCode  = $this->marcSubfield($xml, '041', 'a');

            // Clean author: remove trailing role suffix like ", Verfasser"
            if ($author) {
                $author = preg_replace('/,?\s*(Verfasser|Illustrator|Hrsg\.|Übersetzer).*$/iu', '', $author);
                $author = trim($author, ' ,');
            }

            // Extract 4-digit year
            preg_match('/\b(19|20)\d{2}\b/', $yearRaw ?? '', $ym);
            $year = isset($ym[0]) ? (int) $ym[0] : null;

            if (! $title) return null;

            return [
                'title'       => $title,
                'author'      => $author,
                'publisher'   => $publisher ? trim($publisher, ' ,') : null,
                'year'        => $year,
                'language'    => $langCode ?? 'de',
                'cover_url'   => $this->dnbCoverUrl($isbn),
                'description' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('DNB lookup failed', ['isbn' => $isbn, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function marcSubfield(string $xml, string $tag, string $code): ?string
    {
        // Find first matching datafield tag
        if (! preg_match('/<datafield tag="' . $tag . '"[^>]*>(.*?)<\/datafield>/s', $xml, $m)) {
            return null;
        }
        if (! preg_match('/<subfield code="' . $code . '">(.*?)<\/subfield>/', $m[1], $s)) {
            return null;
        }
        return html_entity_decode(trim($s[1]));
    }

    public function dnbCoverUrl(string $isbn): ?string
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        if (! $isbn) return null;

        $url = "https://portal.dnb.de/opac/mvb/cover?isbn={$isbn}";
        try {
            $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 6]]));
            // Real covers are typically >50KB; DNB placeholder is much smaller
            return ($data && strlen($data) > 20000) ? $url : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Google Books ──────────────────────────────────────────────────────────

    private function fromGoogleBooks(string $isbn): ?array
    {
        try {
            $params = ['q' => 'isbn:' . $isbn, 'maxResults' => 1];
            if ($this->apiKey) {
                $params['key'] = $this->apiKey;
            }

            $response = Http::timeout(8)->get('https://www.googleapis.com/books/v1/volumes', $params);

            if (! $response->successful() || empty($response->json('items'))) {
                return null;
            }

            $info = $response->json('items.0.volumeInfo') ?? [];

            $coverUrl = null;
            foreach (['extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail'] as $size) {
                if (! empty($info['imageLinks'][$size])) {
                    $coverUrl = str_replace('http://', 'https://', $info['imageLinks'][$size]);
                    break;
                }
            }

            return [
                'title'       => $info['title'] ?? null,
                'author'      => isset($info['authors']) ? implode(', ', $info['authors']) : null,
                'publisher'   => $info['publisher'] ?? null,
                'year'        => isset($info['publishedDate']) ? (int) substr($info['publishedDate'], 0, 4) : null,
                'language'    => $info['language'] ?? 'de',
                'cover_url'   => $coverUrl,
                'description' => $info['description'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('GoogleBooksService lookup failed', ['isbn' => $isbn, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // ── OpenLibrary ───────────────────────────────────────────────────────────

    private function fromOpenLibrary(string $isbn): ?array
    {
        try {
            $response = Http::timeout(8)->get(
                'https://openlibrary.org/api/books',
                ['bibkeys' => 'ISBN:' . $isbn, 'format' => 'json', 'jscmd' => 'data']
            );

            if (! $response->successful()) {
                return null;
            }

            $book = $response->json('ISBN:' . $isbn);

            if (! $book) {
                return null;
            }

            $author    = collect($book['authors'] ?? [])->pluck('name')->implode(', ') ?: null;
            $publisher = collect($book['publishers'] ?? [])->pluck('name')->first();
            preg_match('/\b(19|20)\d{2}\b/', $book['publish_date'] ?? '', $ym);
            $year = isset($ym[0]) ? (int) $ym[0] : null;
            $coverUrl  = $book['cover']['large'] ?? $book['cover']['medium'] ?? $book['cover']['small'] ?? null;

            return [
                'title'       => $book['title'] ?? null,
                'author'      => $author,
                'publisher'   => $publisher ?: null,
                'year'        => $year > 1000 ? $year : null,
                'language'    => 'de',
                'cover_url'   => $coverUrl,
                'description' => $book['excerpts'][0]['text'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('OpenLibrary lookup failed', ['isbn' => $isbn, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // ── ISBNdb (optional paid fallback, key stored in settings) ──────────────

    private function fromISBNdb(string $isbn): ?array
    {
        $key = Setting::get('isbndb_api_key');
        if (! $key) return null;

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => $key])
                ->get("https://api2.isbndb.com/book/{$isbn}");

            if (! $response->successful()) return null;

            $book = $response->json('book');
            if (! $book || empty($book['title'])) return null;

            $authors = $book['authors'] ?? [];
            preg_match('/\b(19|20)\d{2}\b/', (string) ($book['date_published'] ?? ''), $ym);

            return [
                'title'       => $book['title'],
                'author'      => implode(', ', (array) $authors) ?: null,
                'publisher'   => $book['publisher'] ?? null,
                'year'        => isset($ym[0]) ? (int) $ym[0] : null,
                'language'    => $book['language'] ?? 'de',
                'cover_url'   => $book['image'] ?? null,
                'description' => $book['synopsis'] ?? $book['overview'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('ISBNdb lookup failed', ['isbn' => $isbn, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // ── OpenLibrary Search (fallback when books API finds nothing) ────────────

    private function fromOpenLibrarySearch(string $isbn): ?array
    {
        try {
            $response = Http::timeout(8)->get('https://openlibrary.org/search.json', [
                'isbn'   => $isbn,
                'fields' => 'title,author_name,publisher,first_publish_year,language,cover_i',
                'limit'  => 1,
            ]);

            if (! $response->successful()) return null;

            $doc = $response->json('docs.0');
            if (! $doc || empty($doc['title'])) return null;

            $coverUrl = null;
            if (! empty($doc['cover_i'])) {
                $coverUrl = "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-L.jpg";
            }

            $lang = isset($doc['language']) ? ($doc['language'][0] ?? 'de') : 'de';

            return [
                'title'       => $doc['title'],
                'author'      => isset($doc['author_name']) ? implode(', ', (array) $doc['author_name']) : null,
                'publisher'   => isset($doc['publisher'])   ? ((array) $doc['publisher'])[0] : null,
                'year'        => $doc['first_publish_year'] ?? null,
                'language'    => $lang,
                'cover_url'   => $coverUrl,
                'description' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('OpenLibrary search failed', ['isbn' => $isbn, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function openLibraryCoverById(string $isbn): ?string
    {
        try {
            $response = Http::timeout(6)->get('https://openlibrary.org/search.json', [
                'isbn'   => $isbn,
                'fields' => 'cover_i',
                'limit'  => 1,
            ]);
            $coverId = $response->json('docs.0.cover_i');
            return $coverId ? "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg" : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Cover fallback: direct OpenLibrary URL ────────────────────────────────

    public function amazonCoverUrl(string $isbn): ?string
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        if (! $isbn) return null;

        $url = "https://images-eu.ssl-images-amazon.com/images/P/{$isbn}.jpg";
        try {
            $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 6]]));
            return ($data && strlen($data) > 10000) ? $url : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function directCoverUrl(string $isbn): ?string
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        if (! $isbn) return null;

        $url = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg";
        try {
            $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 6]]));
            // OL returns a tiny placeholder (~8KB) when no cover exists
            return ($data && strlen($data) > 10000) ? $url : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
