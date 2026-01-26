<?php

/**
 * Root entry point acting as a bridge to public/index.php
 * This file is useful if the web server is configured to point to the project root
 * instead of the public/ directory.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// If the requested resource exists as a file in public/, serve it manually
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    $file = __DIR__ . '/public' . $uri;
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

// Otherwise, delegate to the public/index.php
require_once __DIR__ . '/vendor/autoload.php';

use App\Application;

(new Application())->run();
