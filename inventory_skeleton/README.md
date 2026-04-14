# University Inventory & Asset Management System (Innovation Edition)

An enterprise-grade, role-based University Inventory and Asset Management System built with PHP and MySQL. Designed to handle the complete lifecycle of university assets—from faculty procurement requests, through financial approvals, physical receipt, allocation, and final deadstock/scrap disposal. 

Features a modern, fluid "Glassmorphic" UI with a standardized dark-mode theme utilizing Vanilla CSS and Bootstrap 5.

## 🚀 Key Features

### 1. Robust Procurement Workflow
- **Indent Requests**: Faculty and HODs can formally request new equipment.
- **Purchase Orders (POs)**: Automated PO generation and vendor tracking from approved indents.
- **Goods Receipt Notes (GRN)**: Store officers record received shipments, logging item condition, and triggering automatic asset drafting into the active inventory.

### 2. Comprehensive Asset Lifecycle & Tracking
- **Smart Asset Tracking**: Items are tracked with auto-generated serials, tags, and QR Codes for physical scanning.
- **Asset Allocations & Reservations**: Users can check out, reserve, or return equipment.
- **Automated Maintenence & Deadstock**: Items can be flagged for repair ("In Repair") or condemned as scrap ("Deadstock") once deprecation is reached. The system handles secure approval hierarchies just for disposal.

### 3. Departmental & Budget Isolation 
- **Role-Based Workflows**: Strictly isolated views for Head of Departments (HOD) and Coordinators. An HOD only sees the active assets, staff, and budget parameters belonging to their specific department block.
- **Budget Management**: Dedicated financial ledgers allowing Super Admins to allocate yearly fiscal budgets to departments. Features manual expense/refund transaction logging that prevents budget overdraw. 

### 4. Advanced System Administration
- **Dynamic Permission Matrix**: Granular GUI matrix for the Super Admin to toggle specific page accesses (Read/Write) per user role in real-time.
- **Intelligent Dashboard Analytics**: Visual metric reports using Chart.js to map budget utilization, departmental distribution, and system-wide serviceability percentages. 

## 🛠 Tech Stack

- **Backend Logic**: PHP 8.x (Vanilla)
- **Database Architecture**: MySQL / MariaDB (using secure `PDO` abstraction)
- **Frontend / UI**: HTML5, Vanilla JavaScript, Bootstrap 5 (Customized overrides)
- **Styling Details**: Modern CSS including native CSS Variables for global state management, theming (`--nc-bg-card`, etc.), and CSS Glassmorphism.
- **Plugins**: Chart.js (Analytics), QRCode.js (Hardware Tagging), FontAwesome 5 (Iconography).

## 🗄️ Database Setup

1. Create a MySQL database (e.g. `inventory_db`).
2. Import your latest schema dump into the database.
3. Establish your DB connection by editing the `core/db.php` file using the established constants (configured in `core/config.php`):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'inventory_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

## 🔒 Security Summary
- Extensive PDO prepared statement usage handling all dynamic SQL parameters to prevent SQLi.
- Native session handling and dynamic role verification integrated directly into the core `includes/header.php`.
- CSRF token logic and verification functions applied to critical data-mutating endpoints.

## 👥 User Roles Summary
- **Super Admin**: Absolute visibility and configuration control.
- **HOD (Head of Department)**: Manages indent requests, departmental budget, and specific staff allocations.
- **Coordinator**: Operational support assisting HOD workflows.
- **Staff/Default**: Basic view and reservation access for circulating hardware.

---
*Created as an end-to-end framework for seamless academic hardware organization.*
