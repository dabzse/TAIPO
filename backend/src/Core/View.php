<?php

namespace App\Core;

use Exception;
use App\Exception\ViewNotFoundException;

class View
{
    /**
     * Renders a view file and extracts data into its scope.
     *
     * @param string $view The view filename (relative to views/ directory)
     * @param array $data Data to be extracted into the view scope
     */
    public static function render(string $view, array $data = []): void
    {
        extract($data);

        $viewPath = __DIR__ . '/../../views/' . $view;

        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            throw new ViewNotFoundException("View file not found: $viewPath");
        }
    }
}
