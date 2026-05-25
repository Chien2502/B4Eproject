<?php
/**
 * Tải ảnh bìa sách từ DuckDuckGo Image Search
 * - Tìm token VQD → gọi DuckDuckGo Image JSON API
 * - Ưu tiên ảnh từ: cdn1.fahasa.com, thuviensach.vn, sachvui.com, nhasachphuongnam.com
 * - Lưu vào uploads/img/Book/ và update database
 */
require_once __DIR__ . '/config/database.php';
set_time_limit(600);

$db   = new Database();
$conn = $db->connect();

$stmt = $conn->query("SELECT id, title, author FROM books WHERE image_url LIKE '%_ph.png'");
$books = $stmt->fetchAll();

echo "Found " . count($books) . " books to update.\n\n";

$success  = 0;
$failed   = 0;
$notFound = 0;

// Domain ưu tiên (ảnh sách Việt Nam chất lượng tốt)
$priorityDomains = [
    'cdn1.fahasa.com',
    'cdn0.fahasa.com',
    'thuviensach.vn',
    'nhasachphuongnam.com',
    'sachvui.com',
    'down-vn.img.susercontent.com',
    'salt.tikicdn.com',
];

$browserHeaders = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Accept-Language: vi-VN,vi;q=0.9,en-US;q=0.8',
];

function curlGet(string $url, array $headers = [], int $timeout = 12): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body ?: ''];
}

function getDDGToken(string $q, array $headers): ?string {
    $url = 'https://duckduckgo.com/?q=' . urlencode($q) . '&iax=images&ia=images';
    [, $html] = curlGet($url, $headers, 10);
    preg_match('/vqd=([\d-]+)/', $html, $m);
    return $m[1] ?? null;
}

function searchImages(string $q, string $vqd, array $headers): array {
    $url = 'https://duckduckgo.com/i.js?l=vn-vi&o=json&q=' . urlencode($q) . '&vqd=' . urlencode($vqd);
    $headers[] = 'Referer: https://duckduckgo.com/';
    [$code, $json] = curlGet($url, $headers, 12);
    if ($code !== 200) return [];
    $data = json_decode($json, true);
    return $data['results'] ?? [];
}

function pickBestImage(array $results, array $priorityDomains): ?string {
    // Ưu tiên domain tin cậy
    foreach ($priorityDomains as $domain) {
        foreach ($results as $r) {
            $imgUrl = $r['image'] ?? '';
            if ($imgUrl && str_contains($imgUrl, $domain)) {
                return $imgUrl;
            }
        }
    }
    // Fallback: ảnh đầu tiên có kích thước hợp lý (tránh icon nhỏ)
    foreach ($results as $r) {
        $w = $r['width'] ?? 0;
        $h = $r['height'] ?? 0;
        if ($w >= 100 && $h >= 150) {
            return $r['image'] ?? null;
        }
    }
    return $results[0]['image'] ?? null;
}

foreach ($books as $book) {
    $id     = $book['id'];
    $title  = $book['title'];
    $author = $book['author'];

    echo "[Book $id] $title — $author\n";

    // Từ khoá tìm kiếm
    $q = "bìa sách \"$title\" $author";

    // Lấy VQD token
    $vqd = getDDGToken($q, $browserHeaders);
    if (!$vqd) {
        echo "  ✗ Không lấy được VQD token\n";
        $notFound++;
        usleep(500000);
        continue;
    }

    // Tìm ảnh
    $results = searchImages($q, $vqd, $browserHeaders);
    if (empty($results)) {
        // Thử tìm lại không có dấu ngoặc kép
        $vqd2 = getDDGToken("$title $author bìa sách", $browserHeaders);
        if ($vqd2) {
            $results = searchImages("$title $author bìa sách", $vqd2, $browserHeaders);
        }
    }

    if (empty($results)) {
        echo "  ~ Không tìm thấy ảnh\n";
        $notFound++;
        usleep(400000);
        continue;
    }

    $imgUrl = pickBestImage($results, $priorityDomains);

    if (!$imgUrl) {
        echo "  ~ Không chọn được ảnh phù hợp\n";
        $notFound++;
        usleep(400000);
        continue;
    }

    // Tải ảnh về
    $ext      = (str_ends_with(strtolower(parse_url($imgUrl, PHP_URL_PATH)), '.png')) ? 'png' : 'jpg';
    $filename = "img/Book/book_{$id}_cover.{$ext}";
    $filepath = __DIR__ . "/uploads/{$filename}";

    [$dlCode, $imgData] = curlGet($imgUrl, $browserHeaders, 20);

    if ($dlCode === 200 && strlen($imgData) > 3000) {
        file_put_contents($filepath, $imgData);
        $upd = $conn->prepare("UPDATE books SET image_url = ? WHERE id = ?");
        $upd->execute([$filename, $id]);
        $kb = round(strlen($imgData) / 1024, 1);
        echo "  ✓ Saved: $filename ({$kb} KB) — source: " . parse_url($imgUrl, PHP_URL_HOST) . "\n";
        $success++;
    } else {
        echo "  ✗ Download failed (HTTP $dlCode, " . strlen($imgData) . " bytes) — $imgUrl\n";
        $failed++;
    }

    // Nghỉ giữa các request để tránh bị rate-limit
    usleep(600000); // 0.6s
}

echo "\n=== HOÀN THÀNH ===\n";
echo "Thành công : $success / " . count($books) . "\n";
echo "Thất bại DL: $failed\n";
echo "Không tìm : $notFound\n";
