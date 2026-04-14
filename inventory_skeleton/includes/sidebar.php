<?php
// includes/sidebar.php - Role-specific sidebar with correct dropdown toggle and active state
if (!isset($pdo) || !isset($user)) {
    die('Sidebar must be included after header initialization.');
}

/**
 * Compute the current page's relative URL (same logic as header.php)
 * e.g. "dashboards/super_admin/manage_users.php"
 */
function getCurrentRelativeUrl() {
    $requestUriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $app_root_path  = dirname(dirname(__FILE__)); // inventory_skeleton dir
    $doc_root       = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $base_path      = str_replace($doc_root, '', str_replace('\\', '/', $app_root_path));
    return ltrim(preg_replace('#^' . preg_quote($base_path, '#') . '#', '', $requestUriPath), '/');
}

/**
 * Check whether any descendant (child or deeper) of $parentId is the current page.
 * This is recursive so it handles any depth.
 */
function isAncestorOfActive($pdo, $userRole, $parentId, $currentUrl) {
    $sql = "SELECT sp.id, sp.page_url FROM sys_pages sp
            JOIN role_access ra ON sp.id = ra.page_id
            JOIN sys_roles sr ON ra.role_key = sr.role_key
            WHERE sr.role_key = ? AND sp.parent_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userRole, $parentId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($children as $child) {
        if ($child['page_url'] === $currentUrl) {
            return true;
        }
        // Check grandchildren recursively
        if (isAncestorOfActive($pdo, $userRole, $child['id'], $currentUrl)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if a page has accessible child pages for this role.
 */
function hasAccessibleChildren($pdo, $userRole, $parentId) {
    $sql = "SELECT COUNT(*) FROM sys_pages sp
            JOIN role_access ra ON sp.id = ra.page_id
            JOIN sys_roles sr ON ra.role_key = sr.role_key
            WHERE sr.role_key = ? AND sp.parent_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userRole, $parentId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Build menu items for a given parent (null = top-level).
 * Returns only the <li> elements (no wrapping <ul>).
 * The <ul class="nav nav-pills nav-sidebar ..."> wrapper is added once in the top-level call.
 */
function buildMenuItems($pdo, $userRole, $parentId, $currentUrl) {
    $sql = "SELECT DISTINCT sp.id, sp.page_name, sp.page_url, sp.icon_class, sp.parent_id
            FROM sys_pages sp
            JOIN role_access ra ON sp.id = ra.page_id
            JOIN sys_roles sr ON ra.role_key = sr.role_key
            WHERE sr.role_key = ?";

    if ($parentId !== null) {
        $sql .= " AND sp.parent_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userRole, $parentId]);
    } else {
        $sql .= " AND sp.parent_id IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userRole]);
    }

    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pages)) {
        return '';
    }

    $html = '';

    foreach ($pages as $page) {
        $hasChildren = hasAccessibleChildren($pdo, $userRole, $page['id']);

        if ($hasChildren) {
            // -------------------------------------------------------
            // Parent item: dropdown with children
            // -------------------------------------------------------
            $isOpen = isAncestorOfActive($pdo, $userRole, $page['id'], $currentUrl);

            // AdminLTE requires:
            //   <li class="nav-item menu-open">  ← keeps it visually open
            //   <a class="nav-link active">       ← highlights the link
            $liClass   = 'nav-item' . ($isOpen ? ' menu-open' : '');
            $linkClass = 'nav-link' . ($isOpen ? ' active' : '');
            $icon      = htmlspecialchars($page['icon_class'] ?? 'fas fa-folder', ENT_QUOTES, 'UTF-8');
            $name      = htmlspecialchars($page['page_name'], ENT_QUOTES, 'UTF-8');

            $html .= '<li class="' . $liClass . '">';
            $html .= '<a href="#" class="' . $linkClass . '">';
            $html .= '<i class="nav-icon ' . $icon . '"></i>';
            $html .= '<p>' . $name . '<i class="right fas fa-angle-left"></i></p>';
            $html .= '</a>';

            // Child sub-menu — display:block when open, hidden otherwise
            $subStyle = $isOpen ? ' style="display:block;"' : '';
            $html .= '<ul class="nav nav-treeview"' . $subStyle . '>';
            $html .= buildMenuItems($pdo, $userRole, $page['id'], $currentUrl);
            $html .= '</ul>';
            $html .= '</li>';

        } else {
            // -------------------------------------------------------
            // Leaf item: direct page link
            // -------------------------------------------------------
            $isActive  = ($page['page_url'] === $currentUrl);
            $linkClass = 'nav-link' . ($isActive ? ' active' : '');
            $icon      = htmlspecialchars($page['icon_class'] ?? 'fas fa-circle', ENT_QUOTES, 'UTF-8');
            $name      = htmlspecialchars($page['page_name'], ENT_QUOTES, 'UTF-8');
            $url       = htmlspecialchars(BASE_URL . '/' . $page['page_url'], ENT_QUOTES, 'UTF-8');

            $html .= '<li class="nav-item">';
            $html .= '<a href="' . $url . '" class="' . $linkClass . '">';
            $html .= '<i class="nav-icon ' . $icon . '"></i>';
            $html .= '<p>' . $name . '</p>';
            $html .= '</a>';
            $html .= '</li>';
        }
    }

    return $html;
}

// -----------------------------------------------------------------------
// Render the full sidebar menu (single treeview root, one data-widget)
// -----------------------------------------------------------------------
$currentUrl = getCurrentRelativeUrl();

echo '<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">';
echo buildMenuItems($pdo, $user['role'], null, $currentUrl);
echo '</ul>';
?>