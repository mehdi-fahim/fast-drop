<?php

/**
 * Router script for PHP built-in server
 * This script ensures that all requests include /index.php in the URL
 */

$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// If the request is for a static file, serve it directly
if (file_exists(__DIR__ . '/public' . $requestUri)) {
    return false;
}

// Force all URLs to include /index.php
// Remove any existing index.php from the URL
$cleanUri = preg_replace('#^/index\.php#', '', $requestUri);

// If the URL doesn't start with /index.php, redirect to include it
if (!str_starts_with($requestUri, '/index.php')) {
    $newUrl = '/index.php' . $cleanUri;
    header("Location: $newUrl", true, 301);
    exit;
}

// Set the PATH_INFO for Symfony
$_SERVER['PATH_INFO'] = $cleanUri;
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Let Symfony handle the request
require_once __DIR__ . '/public/index.php';
