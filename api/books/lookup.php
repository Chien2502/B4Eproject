<?php
// file: api/books/lookup.php
// GET ?isbn=9780134190440
// Proxy đến OpenLibrary API để tránh CORS từ Flutter Web
// Không yêu cầu auth — dữ liệu sách là thông tin công khai

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ.']);
    exit;
}

$isbn = trim($_GET['isbn'] ?? '');
if (empty($isbn)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu tham số isbn.']);
    exit;
}

// Chỉ cho phép chuỗi số (ISBN-10 hoặc ISBN-13)
$isbn = preg_replace('/\D/', '', $isbn);
if (strlen($isbn) !== 10 && strlen($isbn) !== 13) {
    http_response_code(400);
    echo json_encode(['error' => 'ISBN không hợp lệ. Cần 10 hoặc 13 chữ số.']);
    exit;
}

// ── 1. Gọi OpenLibrary Works API ─────────────────────────────────────────────
$url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'B4ELibraryApp/1.0 (hotroB4E@gmail.com)',
    CURLOPT_SSL_VERIFYPEER => false, // XAMPP local dev
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Không thể kết nối đến OpenLibrary: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);
$key  = "ISBN:{$isbn}";

if (empty($data) || !isset($data[$key])) {
    // ── Fallback: thử Google Books API ───────────────────────────────────────
    $googleUrl = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}&maxResults=1";
    $ch2 = curl_init($googleUrl);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $gResponse = curl_exec($ch2);
    curl_close($ch2);

    $gData = json_decode($gResponse, true);
    if (!empty($gData['items'][0])) {
        $vol  = $gData['items'][0]['volumeInfo'];
        $book = _formatGoogleBook($vol, $isbn);
        http_response_code(200);
        echo json_encode($book);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => "Không tìm thấy sách với ISBN {$isbn}."]);
    exit;
}

// ── 2. Format dữ liệu OpenLibrary ────────────────────────────────────────────
$book = _formatOpenLibrary($data[$key], $isbn);
http_response_code(200);
echo json_encode($book);

// ─────────────────────────────────────────────────────────────────────────────
// Helper functions
// ─────────────────────────────────────────────────────────────────────────────

function _formatOpenLibrary(array $vol, string $isbn): array {
    // Tác giả
    $authors = [];
    if (!empty($vol['authors'])) {
        $authors = array_map(fn($a) => $a['name'] ?? '', $vol['authors']);
    }

    // Ảnh bìa — lấy size lớn nhất có sẵn
    $coverUrl = null;
    if (!empty($vol['cover'])) {
        $coverUrl = $vol['cover']['large']
            ?? $vol['cover']['medium']
            ?? $vol['cover']['small']
            ?? null;
    }

    // Năm xuất bản
    $publishYear = null;
    if (!empty($vol['publish_date'])) {
        preg_match('/\d{4}/', $vol['publish_date'], $m);
        $publishYear = !empty($m[0]) ? (int)$m[0] : null;
    }

    // Thể loại (subjects)
    $subjects = [];
    if (!empty($vol['subjects'])) {
        $subjects = array_slice(
            array_map(fn($s) => $s['name'] ?? $s, $vol['subjects']),
            0, 5
        );
    }

    return [
        'source'       => 'openlibrary',
        'isbn'         => $isbn,
        'title'        => $vol['title'] ?? '',
        'author'       => implode(', ', $authors),
        'description'  => $vol['notes'] ?? '',
        'publish_year' => $publishYear,
        'cover_url'    => $coverUrl,
        'page_count'   => $vol['number_of_pages'] ?? null,
        'publisher'    => $vol['publishers'][0]['name'] ?? null,
        'subjects'     => $subjects,
    ];
}

function _formatGoogleBook(array $vol, string $isbn): array {
    $coverUrl = null;
    if (!empty($vol['imageLinks'])) {
        // Ưu tiên thumbnail lớn hơn
        $raw = $vol['imageLinks']['thumbnail']
            ?? $vol['imageLinks']['smallThumbnail']
            ?? null;
        // Chuyển http → https
        if ($raw) $coverUrl = str_replace('http://', 'https://', $raw);
    }

    $publishYear = null;
    if (!empty($vol['publishedDate'])) {
        preg_match('/\d{4}/', $vol['publishedDate'], $m);
        $publishYear = !empty($m[0]) ? (int)$m[0] : null;
    }

    return [
        'source'       => 'google_books',
        'isbn'         => $isbn,
        'title'        => $vol['title'] ?? '',
        'author'       => implode(', ', $vol['authors'] ?? []),
        'description'  => strip_tags($vol['description'] ?? ''),
        'publish_year' => $publishYear,
        'cover_url'    => $coverUrl,
        'page_count'   => $vol['pageCount'] ?? null,
        'publisher'    => $vol['publisher'] ?? null,
        'subjects'     => $vol['categories'] ?? [],
    ];
}
?>
