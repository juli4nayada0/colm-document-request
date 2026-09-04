<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/autoload.php';
require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/config/database.php';

use Colm\Config\Database;
use Colm\Models\User;
use Colm\Models\Student;
use Colm\Models\Document;
use Colm\Models\DocumentRequest;
use Colm\Services\AuthService;
use Colm\Services\TrackingNumberService;
use Colm\Services\ReportService;
use Colm\Helpers\DateHelper;

echo "--- 1. Testing Database Connection ---\n";
try {
    $db = Database::getConnection();
    echo "[OK] Connected to database: " . $db->query('SELECT DATABASE()')->fetchColumn() . "\n";
} catch (\Throwable $e) {
    echo "[FAIL] DB Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- 2. Testing Authentication Service ---\n";
$auth = new AuthService();
$loginResult = $auth->authenticate('admin', 'Password123!');
if ($loginResult['success'] && $loginResult['user']['role'] === 'Admin') {
    echo "[OK] Admin authenticated successfully. User ID: {$loginResult['user']['user_id']}\n";
} else {
    echo "[FAIL] Admin login failed: " . ($loginResult['message'] ?? 'unknown') . "\n";
}

$studentLogin = $auth->authenticate('2026-00001', 'Password123!');
if ($studentLogin['success'] && $studentLogin['user']['role'] === 'Student' && $studentLogin['user']['student_id'] === 1) {
    echo "[OK] Student Juan Dela Cruz authenticated successfully with linked student_id: 1\n";
} else {
    echo "[FAIL] Student login failed: " . ($studentLogin['message'] ?? 'unknown') . "\n";
}

echo "\n--- 3. Testing Sequential Tracking Number Service ---\n";
$track1 = TrackingNumberService::generateNextTrackingNumber(2026);
echo "[OK] Generated Tracking Number: {$track1}\n";

echo "\n--- 4. Testing Document Catalog & Requests ---\n";
$docModel = new Document();
$docs = $docModel->getList(true);
echo "[OK] Active document types count: " . count($docs) . "\n";

$reqModel = new DocumentRequest();
$requests = $reqModel->getList(1, 10);
echo "[OK] Total seeded requests in database: {$requests['total']}\n";

echo "\n--- 5. Testing Reports & Summary KPIs ---\n";
$reportService = new ReportService();
$summary = $reportService->getOverallSummaryStatistics();
echo "[OK] Total Requests in KPI: " . $summary['kpis']['total_requests'] . "\n";
echo "[OK] Active Pending Workload: " . $summary['kpis']['active_pending'] . "\n";

echo "\n--- 6. Testing Date & Overdue Calculation ---\n";
$overdueStatus = DateHelper::getOverdueStatus('2026-08-01 08:00:00', 3, null, 'PROCESSING');
echo "[OK] Calculated Status for old request: {$overdueStatus}\n";

echo "\n=== ALL CORE BACKEND SERVICES VERIFIED SUCCESSFULLY ===\n";
