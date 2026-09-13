<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$size = (int)($_GET['s'] ?? 192);
if (!in_array($size, [180, 192, 512], true)) {
    $size = 192;
}

$src = ROOT_PATH . '/assets/images/logo-transparent.png';
if (!is_file($src) || !function_exists('imagecreatetruecolor')) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/assets/images/logo-transparent.png', true, 302);
    exit;
}

$img = @imagecreatefrompng($src);
if ($img === false) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/assets/images/logo-transparent.png', true, 302);
    exit;
}

$out = imagecreatetruecolor($size, $size);
imagealphablending($out, false);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefilledrectangle($out, 0, 0, $size, $size, $transparent);
imagealphablending($out, true);

$sw = imagesx($img);
$sh = imagesy($img);
$scale = min($size / max($sw, 1), $size / max($sh, 1)) * 0.86;
$dw = (int)round($sw * $scale);
$dh = (int)round($sh * $scale);
$dx = (int)(($size - $dw) / 2);
$dy = (int)(($size - $dh) / 2);
imagecopyresampled($out, $img, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($out);
imagedestroy($img);
imagedestroy($out);
