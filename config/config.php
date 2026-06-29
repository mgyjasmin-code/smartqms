<?php
/**
 * SmartQMS -- Application Configuration
 * Loaded by every page via require_once.
 */

// ── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── App constants ─────────────────────────────────────────────
define('APP_NAME',    'SmartQMS -- Barangay Health Center');
define('APP_VERSION', '3.0');
define('APP_URL',     'http://localhost/smartqms');

// ── ML API ────────────────────────────────────────────────────
// Flask prediction server -- run: python ml/app.py
define('ML_API_URL',  'http://localhost:5000/predict');
define('ML_HEALTH',   'http://localhost:5000/health');

// ── QR Code storage ───────────────────────────────────────────
define('QR_DIR',      __DIR__ . '/../assets/qr/');
define('QR_URL',      APP_URL . '/assets/qr/');

// ── Reference number format ───────────────────────────────────
// BHC-YYYY-NNNN  e.g. BHC-2025-0001
define('REF_PREFIX',  'BHC');

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Role constants ────────────────────────────────────────────
define('ROLE_CLIENT', 'client');
define('ROLE_STAFF',  'staff');
define('ROLE_ADMIN',  'admin');

require_once __DIR__ . '/helpers.php';

// ── Helper: check if user is logged in ────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// ── Helper: require login, redirect if not ───────────────────
function requireLogin(string $role = ''): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit();
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . APP_URL . '/index.php');
        exit();
    }
}

// ── Helper: generate reference number ─────────────────────────
function generateRefNumber(mysqli $conn): string {
    $year = date('Y');
    $sql  = "SELECT COUNT(*) AS cnt FROM queue_tickets
             WHERE YEAR(issued_at) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'] + 1;
    return REF_PREFIX . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
?>
