<?php
/**
 * COLM Registrar Document Request and Tracking System
 * REST API Master Entrypoint & Router
 */

declare(strict_types=1);

// Define Root Directory
define('APP_ROOT', dirname(__DIR__, 2));

// Require Autoloader
require_once APP_ROOT . '/backend/autoload.php';

use Colm\Middleware\CsrfMiddleware;
use Colm\Helpers\ResponseHelper;
use Colm\Controllers\AuthController;
use Colm\Controllers\UserController;
use Colm\Controllers\StudentController;
use Colm\Controllers\DocumentController;
use Colm\Controllers\RequirementController;
use Colm\Controllers\RequestController;
use Colm\Controllers\PaymentController;
use Colm\Controllers\ReleaseController;
use Colm\Controllers\NotificationController;
use Colm\Controllers\ReportController;
use Colm\Controllers\AuditController;
use Colm\Controllers\FileController;
use Colm\Controllers\OrgChartController;

// Global Error / Exception Handler for JSON Responses
set_exception_handler(function (\Throwable $e) {
    error_log("[COLM API EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    $code = $e->getCode();
    $statusCode = (is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
    
    // User friendly message without leaking system internals
    $msg = ($statusCode >= 500) ? 'An internal server error occurred. Please contact the administrator.' : $e->getMessage();
    ResponseHelper::error($msg, $statusCode, null, 'ERR_' . date('Ymd_His'));
});

// Set Security & CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify CSRF token for mutating requests
CsrfMiddleware::verify();

// Parse Request URI and HTTP Method
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Strip Query Strings
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

// Normalize path by removing base project path (e.g. /colmregistrar/backend/public or /colmregistrar/api/v1)
$path = preg_replace('#^.*?/api/v1#', '', $path);
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// ==============================================================================
// ROUTING TABLE
// ==============================================================================

// 1. AUTHENTICATION ROUTES
if ($path === '/auth/login' && $requestMethod === 'POST') {
    (new AuthController())->login();
} elseif ($path === '/auth/logout' && $requestMethod === 'POST') {
    (new AuthController())->logout();
} elseif ($path === '/auth/me' && $requestMethod === 'GET') {
    (new AuthController())->me();
} elseif ($path === '/auth/csrf' && $requestMethod === 'GET') {
    (new AuthController())->csrf();
}

// 2. USER ROUTES
elseif ($path === '/users' && $requestMethod === 'GET') {
    (new UserController())->index();
} elseif ($path === '/users' && $requestMethod === 'POST') {
    (new UserController())->store();
} elseif (preg_match('#^/users/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
    (new UserController())->show((int)$matches[1]);
} elseif (preg_match('#^/users/(\d+)$#', $path, $matches) && in_array($requestMethod, ['PUT', 'POST'], true)) {
    (new UserController())->update((int)$matches[1]);
} elseif (preg_match('#^/users/(\d+)/status$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new UserController())->toggleStatus((int)$matches[1]);
}

// 3. STUDENT ROUTES & CSV IMPORT
elseif ($path === '/students/filter-options' && $requestMethod === 'GET') {
    (new StudentController())->filterOptions();
} elseif ($path === '/students' && $requestMethod === 'GET') {
    (new StudentController())->index();
} elseif ($path === '/students/deactivate-all' && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new StudentController())->deactivateAll();
} elseif ($path === '/students' && $requestMethod === 'POST') {
    (new StudentController())->store();
} elseif ($path === '/students/me' && $requestMethod === 'GET') {
    (new StudentController())->me();
} elseif ($path === '/students/import' && $requestMethod === 'POST') {
    (new StudentController())->import();
} elseif ($path === '/students/import/batches' && $requestMethod === 'GET') {
    (new StudentController())->batches();
} elseif (preg_match('#^/students/import/(\d+)/errors$#', $path, $matches) && $requestMethod === 'GET') {
    (new StudentController())->batchErrors((int)$matches[1]);
} elseif (preg_match('#^/students/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
    (new StudentController())->show((int)$matches[1]);
} elseif (preg_match('#^/students/(\d+)$#', $path, $matches) && in_array($requestMethod, ['PUT', 'POST'], true)) {
    (new StudentController())->update((int)$matches[1]);
}

// 4. DOCUMENT CATALOG ROUTES
elseif ($path === '/documents' && $requestMethod === 'GET') {
    (new DocumentController())->index();
} elseif ($path === '/documents' && $requestMethod === 'POST') {
    (new DocumentController())->store();
} elseif (preg_match('#^/documents/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
    (new DocumentController())->show((int)$matches[1]);
} elseif (preg_match('#^/documents/(\d+)$#', $path, $matches) && in_array($requestMethod, ['PUT', 'POST'], true)) {
    (new DocumentController())->update((int)$matches[1]);
} elseif (preg_match('#^/documents/(\d+)/status$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new DocumentController())->toggleStatus((int)$matches[1]);
}

// 5. REQUIREMENT ROUTES
elseif (preg_match('#^/documents/(\d+)/requirements$#', $path, $matches) && $requestMethod === 'GET') {
    (new RequirementController())->getByDocument((int)$matches[1]);
} elseif (preg_match('#^/documents/(\d+)/requirements$#', $path, $matches) && $requestMethod === 'POST') {
    (new RequirementController())->store((int)$matches[1]);
} elseif (preg_match('#^/requirements/(\d+)$#', $path, $matches) && in_array($requestMethod, ['PUT', 'POST'], true)) {
    (new RequirementController())->update((int)$matches[1]);
} elseif (preg_match('#^/requirements/(\d+)/status$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new RequirementController())->toggleStatus((int)$matches[1]);
}

// 6. REQUEST & WORKFLOW ROUTES
elseif ($path === '/requests' && $requestMethod === 'GET') {
    (new RequestController())->index();
} elseif ($path === '/requests' && $requestMethod === 'POST') {
    (new RequestController())->store();
} elseif (preg_match('#^/requests/track/([^/]+)$#', $path, $matches) && $requestMethod === 'GET') {
    (new RequestController())->publicTrack($matches[1]);
} elseif (preg_match('#^/requests/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
    (new RequestController())->show((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/timeline$#', $path, $matches) && $requestMethod === 'GET') {
    (new RequestController())->timeline((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/status$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new RequestController())->updateStatus((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/assign$#', $path, $matches) && $requestMethod === 'POST') {
    (new RequestController())->assign((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/requirements$#', $path, $matches) && $requestMethod === 'POST') {
    (new RequestController())->uploadRequirement((int)$matches[1]);
} elseif (preg_match('#^/requests/requirements/(\d+)/verify$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new RequestController())->verifyRequirement((int)$matches[1]);
}

// 7. PAYMENT ROUTES
elseif (preg_match('#^/requests/(\d+)/payment$#', $path, $matches) && $requestMethod === 'GET') {
    (new PaymentController())->show((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/payment$#', $path, $matches) && $requestMethod === 'POST') {
    (new PaymentController())->store((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/payment/confirm$#', $path, $matches) && $requestMethod === 'POST') {
    (new PaymentController())->confirm((int)$matches[1]);
} elseif (preg_match('#^/payments/(\d+)/verify$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new PaymentController())->verify((int)$matches[1]);
}

// 8. RELEASE ROUTES
elseif (preg_match('#^/requests/(\d+)/release$#', $path, $matches) && $requestMethod === 'GET') {
    (new ReleaseController())->show((int)$matches[1]);
} elseif (preg_match('#^/requests/(\d+)/release$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new ReleaseController())->store((int)$matches[1]);
}

// 9. NOTIFICATION ROUTES
elseif ($path === '/notifications' && $requestMethod === 'GET') {
    (new NotificationController())->index();
} elseif (preg_match('#^/notifications/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
    (new NotificationController())->delete((int)$matches[1]);
} elseif (preg_match('#^/notifications/(\d+)/read$#', $path, $matches) && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new NotificationController())->markRead((int)$matches[1]);
} elseif ($path === '/notifications/read-all' && in_array($requestMethod, ['PATCH', 'POST'], true)) {
    (new NotificationController())->markAllRead();
}

// 10. REPORT ROUTES
elseif ($path === '/reports/summary' && $requestMethod === 'GET') {
    (new ReportController())->summary();
} elseif ($path === '/reports/daily' && $requestMethod === 'GET') {
    (new ReportController())->daily();
} elseif ($path === '/reports/monthly' && $requestMethod === 'GET') {
    (new ReportController())->monthly();
} elseif ($path === '/reports/document-types' && $requestMethod === 'GET') {
    (new ReportController())->documentTypes();
} elseif ($path === '/reports/programs' && $requestMethod === 'GET') {
    (new ReportController())->programs();
} elseif ($path === '/reports/pending' && $requestMethod === 'GET') {
    (new ReportController())->pending();
} elseif ($path === '/reports/completed' && $requestMethod === 'GET') {
    (new ReportController())->completed();
} elseif ($path === '/reports/released' && $requestMethod === 'GET') {
    (new ReportController())->released();
} elseif ($path === '/reports/cancelled' && $requestMethod === 'GET') {
    (new ReportController())->cancelled();
} elseif ($path === '/reports/personnel' && $requestMethod === 'GET') {
    (new ReportController())->personnel();
} elseif ($path === '/reports/processing-time' && $requestMethod === 'GET') {
    (new ReportController())->processingTime();
} elseif ($path === '/reports/overdue' && $requestMethod === 'GET') {
    (new ReportController())->overdue();
} elseif ($path === '/reports/payments' && $requestMethod === 'GET') {
    (new ReportController())->payments();
} elseif ($path === '/reports/export' && $requestMethod === 'GET') {
    (new ReportController())->export();
}

// 11. AUDIT LOG ROUTES
elseif ($path === '/audit-logs' && $requestMethod === 'GET') {
    (new AuditController())->index();
}

// 12. FILE DOWNLOAD ROUTES
elseif ($path === '/files/download' && $requestMethod === 'GET') {
    (new FileController())->download();
}

// 13. ORG CHART ROUTES
elseif ($path === '/org-chart' && $requestMethod === 'GET') {
    (new OrgChartController())->index();
} elseif ($path === '/org-chart' && $requestMethod === 'POST') {
    (new OrgChartController())->store();
} elseif (preg_match('#^/org-chart/(\d+)$#', $path, $matches) && in_array($requestMethod, ['PUT', 'POST'], true)) {
    (new OrgChartController())->update((int)$matches[1]);
} elseif (preg_match('#^/org-chart/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
    (new OrgChartController())->destroy((int)$matches[1]);
}

// 14. CATCH-ALL 404 ROUTE
else {
    ResponseHelper::error("API endpoint '{$path}' [{$requestMethod}] not found.", 404, null, 'ROUTE_NOT_FOUND');
}
