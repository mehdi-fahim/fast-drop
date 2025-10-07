<?php

/**
 * Router script for PHP built-in server
 * This script ensures that all requests are handled by Symfony
 */

// If the request is for a static file, serve it directly
if (file_exists(__DIR__ . '/public' . $_SERVER['REQUEST_URI'])) {
    return false;
}

// Otherwise, let Symfony handle the request
require_once __DIR__ . '/public/index.php';
