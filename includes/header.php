<?php
// includes/header.php - Updated with proper role-based handling
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/functions.php';

requireLogin(); // Ensure user is logged in

$pdo = getPDO();
$user = currentUser();

// Load system settings
$stmt = $pdo->query("SELECT k, v FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['k']] = $row['v'];
}

$system_name = $settings['system_name'] ?? 'University Asset Management System';
$system_logo = $settings['system_logo'] ?? '';
$footer_text = $settings['footer_text'] ?? '';

// Get current page details
$requestUriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Determine the base path of the application relative to the document root.
// This assumes the 'includes' directory is at the root of the application.
$app_root_path = dirname(__DIR__); // e.g., c:\xampp\htdocs\inventory_skeleton
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_path = str_replace($doc_root, '', str_replace('\\', '/', $app_root_path));

// Create a relative URL by removing the base path and leading slash
$currentUrl = ltrim(preg_replace('#^' . preg_quote($base_path, '#') . '#', '', $requestUriPath), '/');

$stmt = $pdo->prepare("SELECT id, page_name, parent_id FROM sys_pages WHERE page_url = ?");
$stmt->execute([$currentUrl]);
$pageDetails = $stmt->fetch();

$pageId = $pageDetails['id'] ?? null;
$pageName = $pageDetails['page_name'] ?? 'Dashboard';
$parentId = $pageDetails['parent_id'] ?? null;

// Check access - This is the critical part for role-based access
if ($pageId && !checkPageAccess($pdo, $user['role'], $pageId)) {
    // If user doesn't have access to this page, redirect them to their dashboard
    $dashboardRedirect = BASE_URL . '/';
    switch ($user['role']) {
        case 'hod':
            $dashboardRedirect .= 'dashboards/hod_dashboard.php';
            break;
        case 'coordinator':
            $dashboardRedirect .= 'dashboards/coordinator_dashboard.php';
            break;
        case 'store_officer':
            $dashboardRedirect .= 'dashboards/store_officer_dashboard.php';
            break;
        case 'faculty':
            $dashboardRedirect .= 'dashboards/faculty_dashboard.php';
            break;
        case 'clerk':
            $dashboardRedirect .= 'dashboards/clerk_dashboard.php';
            break;
        default:
            $dashboardRedirect .= 'dashboards/super_admin/index.php';
            break;
    }

    http_response_code(403);
    header("Location: $dashboardRedirect");
    exit();
}

function checkPageAccess($pdo, $userRole, $pageId)
{
    $stmt = $pdo->prepare("
        SELECT 1 FROM role_access ra
        JOIN sys_roles r ON ra.role_key = r.role_key
        WHERE ra.page_id = ? AND r.role_key = ?
    ");
    $stmt->execute([$pageId, $userRole]);
    return $stmt->fetch() !== false;
}

// Build breadcrumbs
$breadcrumbs = [];
if ($pageId) {
    $currentBreadcrumbId = $pageId;
    while ($currentBreadcrumbId) {
        $stmt = $pdo->prepare("SELECT page_name, parent_id FROM sys_pages WHERE id = ?");
        $stmt->execute([$currentBreadcrumbId]);
        $breadcrumb = $stmt->fetch();

        if (!$breadcrumb)
            break;

        array_unshift($breadcrumbs, $breadcrumb['page_name']);
        $currentBreadcrumbId = $breadcrumb['parent_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageName) ?> - <?= escape($system_name) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AdminLTE v4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════════════════
           NexusCore 2.0 — Innovation Edition Design System
           ═══════════════════════════════════════════════════════════ */

        :root {
            --nc-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --nc-primary: #6366f1;
            --nc-primary-rgb: 99, 102, 241;
            --nc-secondary: #8b5cf6;
            --nc-secondary-rgb: 139, 92, 246;
            --nc-accent: #06b6d4;
            --nc-accent-rgb: 6, 182, 212;
            --nc-success: #10b981;
            --nc-success-rgb: 16, 185, 129;
            --nc-warning: #f59e0b;
            --nc-warning-rgb: 245, 158, 11;
            --nc-danger: #ef4444;
            --nc-danger-rgb: 239, 68, 68;
            --nc-info: #3b82f6;
            --nc-info-rgb: 59, 130, 246;

            /* Light theme (Innovation Default) */
            --nc-bg-main: #fcfaff;
            --nc-bg-content: #f8fafc;
            --nc-bg-card: rgba(255, 255, 255, 0.75);
            --nc-bg-card-solid: #ffffff;
            --nc-border: rgba(99, 102, 241, 0.12);
            --nc-text: #334155;
            --nc-text-muted: #64748b;
            --nc-text-heading: #1e293b;
            --nc-glass-bg: rgba(255, 255, 255, 0.6);
            --nc-glass-border: rgba(99, 102, 241, 0.15);
            --nc-glass-shadow: 0 10px 40px rgba(99, 102, 241, 0.08);
            --nc-navbar-bg: rgba(255, 255, 255, 0.8);
            --nc-table-hover: rgba(99, 102, 241, 0.05);
            --nc-table-stripe: rgba(99, 102, 241, 0.02);
            --nc-input-bg: #ffffff;
            --nc-input-border: rgba(99, 102, 241, 0.2);
            --nc-footer-bg: rgba(255, 255, 255, 0.85);

            /* Ease tokens */
            --nc-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
            --nc-ease-out: cubic-bezier(0, 0, 0.2, 1);
        }

        /* ── Dark Mode Overrides (Refined Innovation) ── */
        body.dark-mode {
            --nc-bg-main: #06070a;
            --nc-bg-content: #0a0c14;
            --nc-bg-card: rgba(20, 22, 38, 0.75);
            --nc-bg-card-solid: #121421;
            --nc-border: rgba(99, 102, 241, 0.18);
            --nc-text: #a0aec0;
            --nc-text-muted: #718096;
            --nc-text-heading: #edf2f7;
            --nc-glass-bg: rgba(18, 20, 33, 0.7);
            --nc-glass-border: rgba(99, 102, 241, 0.22);
            --nc-glass-shadow: 0 15px 50px rgba(0, 0, 0, 0.5);
            --nc-navbar-bg: rgba(6, 7, 10, 0.85);
            --nc-table-hover: rgba(99, 102, 241, 0.12);
            --nc-table-stripe: rgba(255, 255, 255, 0.03);
            --nc-input-bg: rgba(18, 20, 33, 0.85);
            --nc-input-border: rgba(99, 102, 241, 0.35);
            --nc-footer-bg: rgba(6, 7, 10, 0.96);
        }

        /* ── Global Theme Adaptation Utilities ── */
        body.dark-mode .bg-white,
        body.dark-mode .card-header.bg-white,
        body.dark-mode .card-footer.bg-white {
            background-color: var(--nc-bg-card) !important;
            color: var(--nc-text) !important;
        }

        body.dark-mode .text-dark {
            color: var(--nc-text-heading) !important;
        }

        body.dark-mode .text-muted {
            color: var(--nc-text-muted) !important;
        }

        body.dark-mode .border-bottom,
        body.dark-mode .border-top {
            border-color: var(--nc-border) !important;
        }

        body.dark-mode .main-sidebar {
            background: linear-gradient(180deg, #0a0b14 0%, #1e1b4b 50%, #0a0b14 100%) !important;
            border-right: 1px solid var(--nc-border) !important;
        }

        body.dark-mode .breadcrumb-item.active {
            color: var(--nc-text-muted) !important;
        }


        /* ── Base ── */
        body,
        .wrapper,
        .content-wrapper,
        .main-sidebar,
        .main-header {
            font-family: var(--nc-font) !important;
        }

        body {
            color: var(--nc-text);
            -webkit-font-smoothing: antialiased;
            background: var(--nc-bg-main);
            overflow-x: hidden;
            position: relative;
        }

        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 40vw;
            height: 40vw;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.12;
            pointer-events: none;
            animation: meshFloat 20s infinite alternate ease-in-out;
        }

        body::before {
            background: var(--nc-primary);
            top: -10vw;
            right: -5vw;
        }

        body::after {
            background: var(--nc-accent);
            bottom: -5vw;
            left: -5vw;
            animation-delay: -5s;
        }

        /* ── Animated Background Mesh ── */
        .content-wrapper {
            background: var(--nc-bg-content) !important;
            overflow: hidden;
        }

        @keyframes meshFloat {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(5vw, 5vw) scale(1.15) rotate(10deg);
            }
        }

        /* ── Glassmorphic Navbar ── */
        .main-header.navbar {
            background: var(--nc-navbar-bg) !important;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid var(--nc-glass-border) !important;
            box-shadow: 0 1px 20px rgba(99, 102, 241, 0.05) !important;
            transition: all 0.3s var(--nc-ease-in-out);
        }

        .main-header .nav-link {
            color: var(--nc-text-heading) !important;
            transition: all 0.3s var(--nc-ease-out);
            border-radius: 12px;
            padding: 8px 16px !important;
            font-weight: 500;
        }

        .main-header .nav-link:hover {
            background: rgba(var(--nc-primary-rgb), 0.08);
            color: var(--nc-primary) !important;
            transform: translateY(-2px);
        }

        /* ── Innovation Sidebar ── */
        .main-sidebar {
            background: linear-gradient(165deg, #1e1b4b 0%, #312e81 35%, #4338ca 70%, #1e1b4b 100%) !important;
            border-right: none !important;
            box-shadow: 4px 0 40px rgba(0, 0, 0, 0.25);
        }

        .main-sidebar .brand-link {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 20px 16px !important;
        }

        .brand-text {
            font-weight: 800 !important;
            letter-spacing: 2px !important;
            font-size: 1.1rem !important;
            color: #fff !important;
        }

        .brand-text span {
            font-weight: 300 !important;
            opacity: 0.85;
        }

        .sidebar .user-panel {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 24px 12px !important;
        }

        .sidebar .user-panel .rounded-circle {
            background: linear-gradient(135deg, var(--nc-primary), var(--nc-secondary)) !important;
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7) !important;
            margin: 4px 12px;
            border-radius: 14px !important;
            padding: 12px 16px !important;
            transition: all 0.3s var(--nc-ease-in-out) !important;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .nav-sidebar .nav-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.1) !important;
            transform: translateX(6px);
        }

        .nav-sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--nc-primary), var(--nc-secondary)) !important;
            color: #fff !important;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .nav-sidebar .nav-icon {
            margin-right: 12px !important;
            font-size: 1.1rem !important;
        }

        /* ── Floating 3D Stat Panels ── */
        .nc-stat-card {
            position: relative;
            border-radius: 24px;
            padding: 28px;
            color: #fff;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            min-height: 140px;
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .nc-stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 120%;
            height: 120%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            transition: transform 0.6s ease;
        }

        .nc-stat-card:hover {
            transform: translateY(-12px) scale(1.03) rotateX(4deg);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }

        .nc-stat-card:hover::before {
            transform: translate(15%, 15%);
        }

        .nc-stat-card .nc-stat-icon {
            position: absolute;
            right: 20px;
            bottom: 20px;
            font-size: 4.5rem;
            opacity: 0.15;
            transition: all 0.5s ease;
            transform: translateZ(20px);
        }

        .nc-stat-card:hover .nc-stat-icon {
            opacity: 0.25;
            transform: translateZ(40px) scale(1.15) rotate(-8deg);
        }

        .nc-stat-card .nc-stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.9;
            margin-bottom: 8px;
            transform: translateZ(30px);
        }

        .nc-stat-card .nc-stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
            transform: translateZ(50px);
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .nc-stat-card .nc-stat-sub {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        /* Stat card gradient variants */
        .nc-gradient-cyan {
            background: linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #22d3ee 100%);
            box-shadow: 0 10px 30px rgba(6, 182, 212, 0.3);
        }

        .nc-gradient-amber {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
        }

        .nc-gradient-rose {
            background: linear-gradient(135deg, #e11d48 0%, #f43f5e 50%, #fb7185 100%);
            box-shadow: 0 10px 30px rgba(244, 63, 94, 0.3);
        }

        .nc-gradient-emerald {
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }

        .nc-gradient-indigo {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .nc-gradient-purple {
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }

        /* ── Glassmorphic Cards ── */
        .card,
        .info-box {
            background: var(--nc-glass-bg) !important;
            backdrop-filter: blur(12px) saturate(150%);
            -webkit-backdrop-filter: blur(12px) saturate(150%);
            border: 1px solid var(--nc-glass-border) !important;
            border-radius: 16px !important;
            box-shadow: var(--nc-glass-shadow);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .card:hover {
            border-color: rgba(var(--nc-primary-rgb), 0.3) !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2), 0 0 20px rgba(var(--nc-primary-rgb), 0.05);
            transform: translateY(-2px);
        }

        .card .card-header {
            background: transparent !important;
            border-bottom: 1px solid var(--nc-border) !important;
            padding: 16px 20px;
        }

        .card .card-title {
            font-weight: 700;
            color: var(--nc-text-heading);
            font-size: 0.95rem;
        }

        .card .card-body {
            color: var(--nc-text);
        }

        .card-outline {
            border-top: 3px solid var(--nc-primary) !important;
        }

        .card-outline.card-primary {
            border-top-color: var(--nc-primary) !important;
        }

        .card-outline.card-info {
            border-top-color: var(--nc-accent) !important;
        }

        .card-outline.card-secondary {
            border-top-color: var(--nc-secondary) !important;
        }

        /* ── Info Box Enhancement ── */
        .info-box {
            min-height: auto;
            padding: 16px;
            border-radius: 14px !important;
        }

        .info-box .info-box-icon {
            border-radius: 12px;
            width: 60px;
            height: 60px;
            line-height: 60px;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .info-box .info-box-text {
            color: var(--nc-text-muted) !important;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .info-box .info-box-number {
            color: var(--nc-text-heading) !important;
            font-weight: 800;
        }

        /* ── Modern Tables ── */
        .table {
            color: var(--nc-text) !important;
        }

        .table thead th {
            background: rgba(var(--nc-primary-rgb), 0.08) !important;
            color: var(--nc-text-heading) !important;
            border-bottom: 2px solid var(--nc-border) !important;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px !important;
        }

        .table tbody tr {
            transition: all 0.25s ease;
            border-bottom: 1px solid var(--nc-border);
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: var(--nc-table-stripe) !important;
        }

        .table tbody tr:hover,
        .table-hover tbody tr:hover {
            background-color: var(--nc-table-hover) !important;
            box-shadow: inset 4px 0 0 var(--nc-primary);
        }

        .table td {
            padding: 12px 16px !important;
            vertical-align: middle !important;
            border-color: var(--nc-border) !important;
        }

        .table td code {
            background: rgba(var(--nc-primary-rgb), 0.1);
            color: var(--nc-primary);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.825rem;
        }

        /* ── Gradient Buttons ── */
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(var(--nc-primary-rgb), 0.3);
            border-radius: 10px !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--nc-primary-rgb), 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #10b981) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(var(--nc-success-rgb), 0.3);
            border-radius: 10px !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--nc-success-rgb), 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #0284c7, #0ea5e9) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(var(--nc-info-rgb), 0.3);
            border-radius: 10px !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--nc-info-rgb), 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #d97706, #f59e0b) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(var(--nc-warning-rgb), 0.3);
            border-radius: 10px !important;
            font-weight: 600;
            color: #fff !important;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--nc-warning-rgb), 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(var(--nc-danger-rgb), 0.3);
            border-radius: 10px !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--nc-danger-rgb), 0.4);
        }

        .btn {
            position: relative;
            overflow: hidden;
        }

        .btn .nc-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.35);
            transform: scale(0);
            animation: ncRipple 0.6s ease-out;
            pointer-events: none;
        }

        @keyframes ncRipple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* ── Enhanced Badges ── */
        .badge {
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }

        .badge-success,
        .bg-success {
            background: linear-gradient(135deg, #059669, #10b981) !important;
        }

        .badge-warning,
        .bg-warning {
            background: linear-gradient(135deg, #d97706, #f59e0b) !important;
            color: #fff !important;
        }

        .badge-danger,
        .bg-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444) !important;
        }

        .badge-info,
        .bg-info {
            background: linear-gradient(135deg, #0284c7, #0ea5e9) !important;
        }

        .badge-primary,
        .bg-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
        }

        .badge-secondary {
            background: linear-gradient(135deg, #475569, #64748b) !important;
        }

        .badge-light {
            background: rgba(var(--nc-primary-rgb), 0.1) !important;
            color: var(--nc-text) !important;
            border: 1px solid var(--nc-border);
        }

        /* ── Animated Progress Bars ── */
        .progress {
            background: rgba(var(--nc-primary-rgb), 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: ncShimmer 2s infinite;
        }

        @keyframes ncShimmer {
            to {
                left: 100%;
            }
        }

        /* ── Form Controls ── */
        .form-control,
        .form-select,
        select.form-control {
            background-color: var(--nc-input-bg) !important;
            border: 1px solid var(--nc-input-border) !important;
            color: var(--nc-text) !important;
            border-radius: 12px !important;
            padding: 0.65rem 1.1rem;
            transition: all 0.3s var(--nc-ease-in-out);
            font-family: var(--nc-font);
            line-height: 1.5;
        }

        .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--nc-text-heading);
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }

        .form-control-sm,
        .form-select-sm {
            padding: 0.25rem 0.75rem !important;
            border-radius: 8px !important;
            font-size: 0.85rem !important;
        }

        .form-control:focus,
        .form-select:focus,
        select.form-control:focus {
            border-color: var(--nc-primary) !important;
            box-shadow: 0 0 0 3px rgba(var(--nc-primary-rgb), 0.15) !important;
            outline: none;
        }

        /* ── Modals ── */
        .modal-content {
            background: var(--nc-bg-card-solid) !important;
            border: 1px solid var(--nc-glass-border) !important;
            border-radius: 20px !important;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(20px);
        }

        .modal-header {
            border-radius: 20px 20px 0 0 !important;
            border-bottom: 1px solid var(--nc-border) !important;
        }

        .modal-header.bg-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1, #818cf8) !important;
        }

        .modal-footer {
            border-top: 1px solid var(--nc-border) !important;
        }

        /* ── Glassmorphic Footer ── */
        .main-footer {
            background: var(--nc-footer-bg) !important;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-top: 1px solid var(--nc-glass-border) !important;
            color: var(--nc-text-muted);
        }

        /* ── Pagination ── */
        .page-item .page-link {
            background: var(--nc-glass-bg);
            border: 1px solid var(--nc-border);
            color: var(--nc-text);
            border-radius: 8px !important;
            margin: 0 2px;
            transition: all 0.3s ease;
        }

        .page-item .page-link:hover {
            background: rgba(var(--nc-primary-rgb), 0.15);
            color: var(--nc-primary);
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
            border-color: transparent !important;
            box-shadow: 0 4px 12px rgba(var(--nc-primary-rgb), 0.3);
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(10px);
        }

        /* ── Custom Scrollbar ── */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(var(--nc-primary-rgb), 0.3);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(var(--nc-primary-rgb), 0.5);
        }

        /* ── Content Header ── */
        .content-header h1 {
            font-weight: 800;
            color: var(--nc-text-heading);
            font-size: 1.5rem;
        }

        .breadcrumb {
            background: transparent;
        }

        .breadcrumb-item a {
            color: var(--nc-primary);
            font-weight: 500;
        }

        .breadcrumb-item.active {
            color: var(--nc-text-muted);
        }

        /* ── Quick Action Buttons (d-grid) ── */
        .d-grid .btn {
            text-align: left;
            padding: 12px 18px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .d-grid .btn i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .d-grid .btn:hover i {
            transform: scale(1.2);
        }

        /* ── User Dropdown ── */
        .user-dropdown-premium {
            background: var(--nc-bg-card-solid) !important;
            border: 1px solid var(--nc-glass-border) !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4) !important;
            min-width: 280px;
            overflow: hidden;
        }

        .user-dropdown-premium .user-header {
            text-align: center;
            padding: 24px 16px;
            background: linear-gradient(135deg, rgba(var(--nc-primary-rgb), 0.15), rgba(139, 92, 246, 0.1));
        }

        .user-dropdown-premium .user-header p {
            color: var(--nc-text-heading);
            font-weight: 600;
        }

        .user-dropdown-premium .user-footer {
            padding: 12px 16px;
            background: transparent;
        }

        /* ── Entrance Animations ── */
        @keyframes ncFadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .nc-animate-in {
            animation: ncFadeInUp 0.6s cubic-bezier(0.23, 1, 0.32, 1) both;
        }

        /* ── Counter Animation ── */
        .nc-counter {
            display: inline-block;
        }

        /* ── Legacy dark-mode token support ── */
        .dark-mode {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
        }

        /* ── Info box icon gradient overrides ── */
        .info-box-icon.bg-info {
            background: linear-gradient(135deg, #0284c7, #0ea5e9) !important;
        }

        .info-box-icon.bg-success {
            background: linear-gradient(135deg, #059669, #10b981) !important;
        }

        .info-box-icon.bg-warning {
            background: linear-gradient(135deg, #d97706, #f59e0b) !important;
        }

        .info-box-icon.bg-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444) !important;
        }

        .info-box-icon.bg-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-light elevation-1">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                            class="fas fa-bars-staggered"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <span class="nav-link text-muted font-weight-light">Inventory Management</span>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="toggleDarkMode()" title="Toggle Theme">
                        <i class="fas fa-circle-half-stroke"></i>
                    </a>
                </li>
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fa-lg"></i>
                        <span class="d-none d-md-inline ms-1"><?= escape($user['name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end user-dropdown-premium">
                        <!-- User image -->
                        <li class="user-header bg-transparent">
                            <i class="fas fa-user-astronaut fa-3x text-primary mb-3"></i>
                            <p>
                                <?= escape($user['name']) ?>
                                <small class="text-muted d-block mt-1"><?= escape($user['role']) ?></small>
                            </p>
                        </li>
                        <!-- Menu Footer-->
                        <li class="user-footer border-top border-light">
                            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-danger float-right">
                                <i class="fas fa-power-off me-1"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar elevation-4">
            <!-- Brand Logo -->
            <a href="<?php
            switch ($user['role']) {
                case 'hod':
                    echo BASE_URL . '/dashboards/hod_dashboard.php';
                    break;
                case 'coordinator':
                    echo BASE_URL . '/dashboards/coordinator_dashboard.php';
                    break;
                case 'store_officer':
                    echo BASE_URL . '/dashboards/store_officer_dashboard.php';
                    break;
                case 'faculty':
                    echo BASE_URL . '/dashboards/faculty_dashboard.php';
                    break;
                case 'clerk':
                    echo BASE_URL . '/dashboards/clerk_dashboard.php';
                    break;
                default:
                    echo BASE_URL . '/dashboards/super_admin/index.php';
                    break;
            }
            ?>" class="brand-link border-bottom border-light">
                <div class="brand-image-container d-inline-block ps-2">
                    <i class="fas fa-cubes-stacked text-primary"></i>
                </div>
                <span class="brand-text font-weight-bold ms-1" style="letter-spacing: 1px;">NEXUS<span
                        class="text-primary">CORE</span></span>
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel mt-4 pb-4 mb-4 d-flex align-items-center">
                    <div class="image">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <span class="text-white font-weight-bold"><?= substr($user['name'], 0, 1) ?></span>
                        </div>
                    </div>
                    <div class="info ms-3">
                        <a href="#" class="d-block font-weight-600 mb-0"><?= escape($user['name']) ?></a>
                        <span
                            class="text-xs text-muted text-uppercase tracking-wider"><?= escape($user['role']) ?></span>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <?php include __DIR__ . '/sidebar.php'; ?>
                </nav>
                <!-- /.sidebar-menu -->
            </div>
            <!-- /.sidebar -->
        </aside>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0"><?= escape($pageName) ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php
                                switch ($user['role']) {
                                    case 'hod':
                                        echo BASE_URL . '/dashboards/hod_dashboard.php';
                                        break;
                                    case 'coordinator':
                                        echo BASE_URL . '/dashboards/coordinator_dashboard.php';
                                        break;
                                    case 'store_officer':
                                        echo BASE_URL . '/dashboards/store_officer_dashboard.php';
                                        break;
                                    case 'faculty':
                                        echo BASE_URL . '/dashboards/faculty_dashboard.php';
                                        break;
                                    case 'clerk':
                                        echo BASE_URL . '/dashboards/clerk_dashboard.php';
                                        break;
                                    default:
                                        echo BASE_URL . '/dashboards/super_admin/index.php';
                                        break;
                                }
                                ?>">Home</a></li>
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item active"><?= escape($crumb) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.content-header -->

            <!-- Main content -->
            <div class="content">
                <div class="container-fluid">