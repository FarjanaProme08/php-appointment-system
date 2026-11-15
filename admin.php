<?php
/**
 * Admin Panel - Car Workshop Appointment System
 * Displays Appointment Table and allows editing Appointment Date and Mechanic
 */

require_once __DIR__ . '/config/db.php';

$dbConnected = false;
$dbError = null;

try {
    $db = getDbConnection();
    $dbConnected = true;
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workshop Administration Panel | Apex Auto</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container" aria-live="polite"></div>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-content">
            <a href="admin.php" class="brand-logo">
                <div class="brand-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </div>
                <span>Apex Workshop Admin</span>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="nav-item">Book Appointment</a></li>
                <li><a href="admin.php" class="nav-item active">Admin Panel</a></li>
                <li><a href="database/setup.php" class="nav-item" style="color:var(--gray-500);font-size:0.8rem;">Database Setup</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-wrapper" style="padding-top: 32px;">
        <div class="container">

            <?php if (!$dbConnected): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <strong style="display:block; font-size:1.05rem; margin-bottom:4px;">Database Connection Notice</strong>
                        <span><?= htmlspecialchars($dbError) ?></span>
                    </div>
                    <a href="database/setup.php" class="btn btn-primary btn-sm">Run 1-Click Database Setup</a>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="admin-header">
                <div>
                    <h1>Appointments Management</h1>
                    <p class="text-muted">Review client appointments, monitor specialist schedules, and modify dates &amp; assigned mechanics.</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>New Booking</span>
                    </a>
                </div>
            </div>

            <!-- KPI Stats Summary Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div>
                        <div class="stat-label">Total Appointments</div>
                        <div id="statTotal" class="stat-value">--</div>
                    </div>
                    <div class="stat-icon blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="stat-label">Today's Schedule</div>
                        <div id="statToday" class="stat-value">--</div>
                    </div>
                    <div class="stat-icon amber">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="stat-label">Upcoming Bookings</div>
                        <div id="statUpcoming" class="stat-value">--</div>
                    </div>
                    <div class="stat-icon green">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="stat-label">Active Specialists</div>
                        <div id="statMechanics" class="stat-value">5</div>
                    </div>
                    <div class="stat-icon purple">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="filter-card">
                <form id="filterForm" class="filter-form" onsubmit="return false;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="filterSearch" class="form-label text-xs">Search Records</label>
                        <input type="text" id="filterSearch" class="form-control" placeholder="Search Client Name, Phone, Car License..." autocomplete="off">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="filterDate" class="form-label text-xs">Filter by Date</label>
                        <input type="date" id="filterDate" class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="filterMechanic" class="form-label text-xs">Filter by Specialist</label>
                        <select id="filterMechanic" class="form-select">
                            <option value="">All 5 Specialists</option>
                        </select>
                    </div>

                    <div>
                        <button type="button" id="resetFiltersBtn" class="btn btn-secondary btn-block">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                <path d="M3 3v5h5"></path>
                            </svg>
                            <span>Reset Filters</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Appointments Data Table -->
            <div class="table-card">
                <div class="table-header-bar">
                    <h3 style="font-size:1.1rem;font-weight:700;">Appointments List</h3>
                    <span class="text-xs text-muted">Real-time database sync</span>
                </div>

                <div class="table-responsive">
                    <table class="data-table" aria-label="Appointments Table">
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Phone</th>
                                <th>Car License No</th>
                                <th>Appointment Date</th>
                                <th>Assigned Mechanic</th>
                                <th>Status</th>
                                <th style="text-align: right; padding-right: 24px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTableBody">
                            <!-- Populated via AJAX (assets/js/admin.js) -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Edit Appointment Modal Dialog -->
    <div id="editModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="modalTitle">Edit Appointment</h3>
                <button type="button" class="modal-close close-modal-trigger" aria-label="Close dialog">&times;</button>
            </div>

            <form id="editAppointmentForm">
                <div class="modal-body">
                    <!-- Dynamic Alert inside Modal for errors/warnings -->
                    <div id="modalAlertBox"></div>

                    <!-- Client Context Info Box (Read-only reference) -->
                    <div class="client-summary-box">
                        <div class="client-summary-item">
                            <strong>Client Name</strong>
                            <span id="modalClientName">--</span>
                        </div>
                        <div class="client-summary-item">
                            <strong>Phone Number</strong>
                            <span id="modalClientPhone">--</span>
                        </div>
                        <div class="client-summary-item">
                            <strong>Car License (Plate)</strong>
                            <span id="modalCarLicense">--</span>
                        </div>
                        <div class="client-summary-item">
                            <strong>Car Engine No</strong>
                            <span id="modalCarEngine">--</span>
                        </div>
                    </div>

                    <input type="hidden" id="modalAppointmentId" name="id" value="">

                    <!-- Editable Field 1: Appointment Date -->
                    <div class="form-group">
                        <label for="modalAppointmentDate" class="form-label">
                            Appointment Date <span class="required">*</span>
                        </label>
                        <input type="date" id="modalAppointmentDate" name="appointment_date" class="form-control" required>
                        <span class="form-hint">Changing date re-calculates available specialist capacity.</span>
                    </div>

                    <!-- Editable Field 2: Mechanic Selection with Dynamic Available Slots -->
                    <div class="form-group">
                        <label for="modalMechanicSelect" class="form-label">
                            Assign Mechanic <span class="required">*</span>
                        </label>
                        <select id="modalMechanicSelect" name="mechanic_id" class="form-select" required>
                            <option value="">Loading mechanic slots...</option>
                        </select>
                        <span class="form-hint">Enforces 4 cars/day capacity limit. Full mechanics cannot be assigned.</span>
                    </div>

                    <!-- Status Selection -->
                    <div class="form-group">
                        <label for="modalStatusSelect" class="form-label">Appointment Status</label>
                        <select id="modalStatusSelect" name="status" class="form-select">
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Optional Admin Notes -->
                    <div class="form-group">
                        <label for="modalNotes" class="form-label">Service / Administrative Notes</label>
                        <textarea id="modalNotes" name="notes" class="form-control" rows="2" placeholder="Internal remarks or service notes..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-modal-trigger">Cancel</button>
                    <button type="submit" id="saveEditBtn" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p>&copy; <?= date('Y') ?> Apex Auto Specialists &bull; Admin Management Portal</p>
        </div>
    </footer>

    <!-- Admin JS Scripts -->
    <script src="assets/js/admin.js"></script>
</body>
</html>
