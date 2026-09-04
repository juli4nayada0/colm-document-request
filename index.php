<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Root Gateway Redirect
 */

// If request is targeting API directly, forward to API router
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($uri, '/api/v1')) {
    require_once __DIR__ . '/backend/public/index.php';
    exit;
}

// Redirect browser users to the frontend login page.
header('Location: frontend/login.html');
exit;
