<?php
header('Content-Type: application/json; charset=utf-8');

$galleryPath = __DIR__ . '/../img/gallery';

if (!is_dir($galleryPath)) {
    echo json_encode(["error" => "갤러리 폴더가 존재하지 않습니다."]);
    exit;
}

// -----------------------------
//  원본 자동 리사이즈 함수 (2000px 이하 유지)
// -----------------------------
function resizeImageIfNeeded($sourcePath, $maxSize = 2000) {

    list($width, $height, $type) = getimagesize($sourcePath);

    // 이미 2000px 이하 → 리사이즈 필요 없음
    if ($width <= $maxSize && $height <= $maxSize) {
        return false;
    }

    // 비율 유지한 채 리사이즈
    $scale = min($maxSize / $width, $maxSize / $height);
    $newWidth  = intval($width * $scale);
    $newHeight = intval($height * $scale);

    // 이미지 타입별 처리
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($sourcePath);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        default:
            return false; // JPG/PNG만 처리
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    // PNG 투명도 유지
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    // 리사이즈 수행
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 
        $newWidth, $newHeight, $width, $height
    );

    // 덮어쓰기 저장
    if ($type === IMAGETYPE_JPEG) {
        imagejpeg($dst, $sourcePath, 90);
    } else {
        imagepng($dst, $sourcePath);
    }

    return true;
}

$allowedExt = ['jpg','jpeg','png'];
$images = [];

$files = scandir($galleryPath);

foreach ($files as $file) {

    if ($file === '.' || $file === '..') continue;
    if (strpos($file, '.') === 0) continue;

    $fullPath = $galleryPath . '/' . $file;

    if (is_dir($fullPath)) continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $nameOnly = pathinfo($file, PATHINFO_FILENAME);

    if (!in_array($ext, $allowedExt)) continue;

    // 1) 원본 자동 리사이즈 (2000px 제한)
    resizeImageIfNeeded($fullPath, 2000);

    // 2) JPG/PNG 그대로 반환
    $images[] = ["src" => $file];
}

usort($images, function($a, $b){
    return strcmp($b["src"], $a["src"]);
});

echo json_encode($images, JSON_UNESCAPED_UNICODE);
exit;
?>
