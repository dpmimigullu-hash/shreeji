<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$user = getUserById($_SESSION['user_id']);
$role = $user['role'];
?>

<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2 sidebar-modern" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand px-4 py-3 m-0" href="index.php">
            <img src="./assets/images/logo.png" class="navbar-brand-img" width="26" height="26" alt="Shreeji Link Logo">
            <span class="ms-1 text-sm text-dark">Shreeji Link</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0 mb-2">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link text-dark <?php echo ($current_page == 'index.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="index.php">
                    <i class="material-symbols-rounded opacity-5">dashboard</i>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            <?php if ($role == 'admin' || $role == 'supervisor'): ?>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'trips.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="trips.php">
                        <i class="material-symbols-rounded opacity-5">route</i>
                        <span class="nav-link-text ms-1">Trips</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'drivers.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="drivers.php">
                        <i class="material-symbols-rounded opacity-5">people</i>
                        <span class="nav-link-text ms-1">Drivers & Vehicles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'clients.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="clients.php">
                        <i class="material-symbols-rounded opacity-5">business</i>
                        <span class="nav-link-text ms-1">Clients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'vehicles.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="vehicles.php">
                        <i class="material-symbols-rounded opacity-5">directions_car</i>
                        <span class="nav-link-text ms-1">Vehicles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'geolocation_tracker.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="geolocation_tracker.php">
                        <i class="material-symbols-rounded opacity-5">location_on</i>
                        <span class="nav-link-text ms-1">Live Tracking</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'supervisor_attendance.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="supervisor_attendance.php">
                        <i class="material-symbols-rounded opacity-5">event_available</i>
                        <span class="nav-link-text ms-1">Attendance</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($role == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'supervisors.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="supervisors.php">
                        <i class="material-symbols-rounded opacity-5">supervisor_account</i>
                        <span class="nav-link-text ms-1">Supervisors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'branches.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="branches.php">
                        <i class="material-symbols-rounded opacity-5">business</i>
                        <span class="nav-link-text ms-1">Branches</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'reports.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="reports.php">
                        <i class="material-symbols-rounded opacity-5">analytics</i>
                        <span class="nav-link-text ms-1">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'billing.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="billing.php">
                        <i class="material-symbols-rounded opacity-5">receipt_long</i>
                        <span class="nav-link-text ms-1">Driver Billing</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'whatsapp_admin.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="whatsapp_admin.php">
                        <i class="material-symbols-rounded opacity-5">chat</i>
                        <span class="nav-link-text ms-1">WhatsApp Admin</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($role == 'driver'): ?>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'trips.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="trips.php">
                        <i class="material-symbols-rounded opacity-5">route</i>
                        <span class="nav-link-text ms-1">My Trips</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'driver_trip_validation.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="driver_trip_validation.php">
                        <i class="material-symbols-rounded opacity-5">verified</i>
                        <span class="nav-link-text ms-1">Trip Validation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark <?php echo ($current_page == 'geolocation_tracker.php') ? 'active bg-gradient-dark text-white' : ''; ?>" href="geolocation_tracker.php">
                        <i class="material-symbols-rounded opacity-5">location_on</i>
                        <span class="nav-link-text ms-1">Live Tracking</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="sidenav-footer position-absolute w-100 bottom-0">
        <div class="mx-3">
            <a class="btn btn-outline-dark mt-4 w-100" href="logout.php" type="button">
                <i class="material-symbols-rounded">logout</i> Logout
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
    <i class="material-symbols-rounded">menu</i>
</button>

<!-- Mobile Overlay -->
<div class="sidenav-overlay d-lg-none" id="sidenavOverlay"></div>

<style>
    /* Enhanced Mobile Navigation Styles */
    @media (max-width: 768px) {
        .sidenav {
            transform: translateX(-100%);
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1050;
            background: var(--background-secondary);
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0 12px 12px 0;
            overflow-y: auto;
        }

        .sidenav.show {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0 !important;
            width: 100%;
        }

        /* Mobile overlay */
        .sidenav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidenav-overlay.show {
            display: block;
            opacity: 1;
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: block !important;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1060;
            background: var(--background-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .mobile-menu-toggle i {
            font-size: 18px;
            color: var(--text-primary);
        }

        /* Hide desktop navbar on mobile */
        .navbar-main {
            display: none;
        }

        /* Mobile sidebar header */
        .sidenav-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--background-secondary);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .sidebar-brand i {
            color: var(--primary-color);
            font-size: 1.3rem;
        }

        /* Mobile navigation items */
        .sidenav .navbar-nav {
            padding: 16px 0;
        }

        .sidenav .nav-item {
            margin: 4px 16px;
        }

        .sidenav .nav-link {
            padding: 14px 16px;
            border-radius: 12px;
            margin: 0;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            touch-action: manipulation;
        }

        .sidenav .nav-link i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
            color: var(--primary-color);
        }

        .sidenav .nav-link:hover {
            background: rgba(25, 118, 210, 0.08);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .sidenav .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0D47A1 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(25, 118, 210, 0.3);
        }

        .sidenav .nav-link.active i {
            color: white;
        }

        /* Mobile sidebar footer */
        .sidenav-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            background: var(--background-secondary);
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }

        .logout-btn {
            width: 100%;
            padding: 12px 16px;
            background: var(--error-50);
            color: var(--error-700);
            border: 1px solid var(--error-200);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
        }

        .logout-btn:hover {
            background: var(--error-100);
            border-color: var(--error-300);
            color: var(--error-800);
            transform: translateY(-1px);
        }

        .logout-btn i {
            font-size: 1rem;
        }
    }

    /* Tablet Navigation Styles */
    @media (min-width: 769px) and (max-width: 1024px) {
        .sidenav {
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            background: var(--background-secondary);
            box-shadow: var(--shadow-lg);
            border-radius: 0;
            transform: none !important;
        }

        .main-content {
            margin-left: 260px !important;
            width: calc(100% - 260px);
        }

        /* Hide mobile elements on tablet */
        .mobile-menu-toggle,
        .sidenav-overlay {
            display: none !important;
        }

        /* Tablet sidebar styles */
        .sidenav-header {
            padding: 24px 28px;
        }

        .sidebar-brand h2 {
            font-size: 1.2rem;
        }

        .sidenav .navbar-nav {
            padding: 20px 0;
        }

        .sidenav .nav-item {
            margin: 6px 20px;
        }

        .sidenav .nav-link {
            padding: 16px 20px;
            font-size: 1rem;
            min-height: 52px;
        }

        .sidenav .nav-link i {
            font-size: 1.3rem;
            width: 22px;
        }

        .sidenav-footer {
            padding: 24px 28px;
        }

        .logout-btn {
            font-size: 1rem;
            padding: 14px 18px;
            min-height: 48px;
        }
    }

    /* Sidebar Modern Styles */
    .sidebar-modern .nav-link {
        border-radius: 12px;
        margin: 4px 8px;
        transition: all 0.3s ease;
    }

    .sidebar-modern .nav-link:hover {
        background: rgba(25, 118, 210, 0.1);
        transform: translateX(4px);
    }

    .sidebar-modern .nav-link.active {
        background: linear-gradient(135deg, var(--primary-color) 0%, #0D47A1 100%);
        color: white;
        box-shadow: 0 4px 16px rgba(25, 118, 210, 0.3);
    }
</style>

<script>
    // Mobile navigation functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidenav = document.getElementById('sidenav-main');
        const sidenavOverlay = document.getElementById('sidenavOverlay');

        if (mobileMenuToggle && sidenav && sidenavOverlay) {
            const toggleMobileMenu = function() {
                sidenav.classList.toggle('show');
                sidenavOverlay.classList.toggle('show');
                document.body.classList.toggle('sidenav-open');
            };

            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            sidenavOverlay.addEventListener('click', toggleMobileMenu);

            // Close mobile menu when clicking on nav links
            const navLinks = sidenav.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 991) {
                        sidenav.classList.remove('show');
                        sidenavOverlay.classList.remove('show');
                        document.body.classList.remove('sidenav-open');
                    }
                });
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidenav.classList.contains('show')) {
                    sidenav.classList.remove('show');
                    sidenavOverlay.classList.remove('show');
                    document.body.classList.remove('sidenav-open');
                }
            });
        }
    });
</script>