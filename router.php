<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Application;

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Define the public path
$publicPath = __DIR__ . '/public';

// If the requested resource exists as a file in public/, serve it
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    $file = $publicPath . $uri;
    $mime = mime_content_type($file);

    // Fix for CSS/JS mime types if mime_content_type is generic
    if (str_ends_with($file, '.css')) {
        $mime = 'text/css';
    } elseif (str_ends_with($file, '.js')) {
        $mime = 'application/javascript';
    }

    header('Content-Type: ' . $mime);
    readfile($file);
    exit;
}

// Otherwise, run the application
(new Application())->run();
