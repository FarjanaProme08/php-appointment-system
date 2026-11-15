<?php
/**
 * User Panel - Car Workshop Appointment System
 * Customer Booking Portal & Workshop Information
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
    <title>Book Workshop Service | Apex Auto Specialists</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container" aria-live="polite"></div>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-content">
            <a href="index.php" class="brand-logo">
                <div class="brand-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                </div>
                <span>Apex Auto Specialists</span>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="nav-item active">Book Appointment</a></li>
                <li><a href="admin.php" class="nav-item">Admin Panel</a></li>
                <li><a href="database/setup.php" class="nav-item" style="color:var(--gray-500);font-size:0.8rem;">Database Setup</a></li>
            </ul>
        </div>
    </nav>

    <!-- Page Hero Banner -->
    <header class="page-hero">
        <div class="container">
            <h1>Schedule Your Vehicle Service</h1>
            <p>
                Experience master-grade automobile maintenance. Select your desired date, choose from our 5 certified specialists, and reserve your dedicated slot.
            </p>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-wrapper">
        <div class="container">

            <?php if (!$dbConnected): ?>
                <div class="alert alert-danger" style="margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <strong style="display:block; font-size:1.05rem; margin-bottom:4px;">Database Connection Notice</strong>
                        <span><?= htmlspecialchars($dbError) ?></span>
                    </div>
                    <a href="database/setup.php" class="btn btn-primary btn-sm">Run 1-Click Database Setup</a>
                </div>
            <?php endif; ?>

            <!-- Confirmation Card Container (Dynamic) -->
            <div id="bookingConfirmation" style="display: none;"></div>

            <div class="booking-layout">

                <!-- Left Column: Booking Form -->
                <section class="card">
                    <div class="card-header">
                        <h2>Appointment Booking Form</h2>
                        <span class="text-xs text-muted"><span class="required" style="color:var(--danger)">*</span> Required Fields</span>
                    </div>

                    <div class="card-body">
                        <form id="bookingForm" novalidate autocomplete="off">

                            <!-- Client Contact Details -->
                            <div class="form-group">
                                <label for="clientName" class="form-label">
                                    Client Full Name <span class="required">*</span>
                                </label>
                                <input type="text" id="clientName" name="client_name" class="form-control" placeholder="e.g. Jonathan Reynolds" required>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="clientAddress" class="form-label">
                                    Street Address <span class="required">*</span>
                                </label>
                                <input type="text" id="clientAddress" name="client_address" class="form-control" placeholder="e.g. 104 Industrial Parkway, Suite 2" required>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="grid grid-2">
                                <div class="form-group">
                                    <label for="clientPhone" class="form-label">
                                        Phone Number <span class="required">*</span>
                                    </label>
                                    <input type="tel" id="clientPhone" name="client_phone" class="form-control" placeholder="e.g. 01712345678" inputmode="numeric" required>
                                    <span class="form-hint">Numbers only (7 to 15 digits)</span>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <div class="form-group">
                                    <label for="carLicenseNo" class="form-label">
                                        Car License No (Plate) <span class="required">*</span>
                                    </label>
                                    <input type="text" id="carLicenseNo" name="car_license_no" class="form-control" placeholder="e.g. DHK-METRO-GA-1234" required>
                                    <span class="form-hint">Vehicle registration plate</span>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <div class="grid grid-2">
                                <div class="form-group">
                                    <label for="carEngineNo" class="form-label">
                                        Car Engine No <span class="required">*</span>
                                    </label>
                                    <input type="text" id="carEngineNo" name="car_engine_no" class="form-control" placeholder="e.g. 1982736450" inputmode="numeric" required>
                                    <span class="form-hint">Numeric engine block ID</span>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <div class="form-group">
                                    <label for="appointmentDate" class="form-label">
                                        Appointment Date <span class="required">*</span>
                                    </label>
                                    <input type="date" id="appointmentDate" name="appointment_date" class="form-control" required>
                                    <span class="form-hint">Choose service date to see live slots</span>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>

                            <!-- Mechanic Selection with Dynamic Remaining Slots -->
                            <div class="form-group">
                                <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>Select Mechanic <span class="required">*</span></span>
                                    <span class="text-xs text-muted" style="font-weight:normal;">Strict limit: Max 4 cars/day per specialist</span>
                                </label>

                                <!-- Hidden input storing selected mechanic ID -->
                                <input type="hidden" id="selectedMechanicId" name="mechanic_id" value="">
                                <div class="invalid-feedback"></div>

                                <!-- Dynamic Mechanics Cards List -->
                                <div id="mechanicsList" class="mechanic-selection-wrapper">
                                    <div class="text-center text-muted" style="padding: 24px 0;">
                                        Loading available mechanic slots...
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Notes / Symptoms -->
                            <div class="form-group">
                                <label for="bookingNotes" class="form-label">
                                    Service Notes / Symptoms <span class="text-xs text-muted">(Optional)</span>
                                </label>
                                <textarea id="bookingNotes" name="notes" class="form-control" rows="2" placeholder="Describe symptoms, periodic service needs, oil grade preference, etc."></textarea>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" id="submitBookingBtn" class="btn btn-primary btn-block" style="padding: 14px; font-size: 1rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Confirm Appointment</span>
                            </button>

                        </form>
                    </div>
                </section>

                <!-- Right Column: Workshop Facilities & Core Policies -->
                <aside class="info-section">

                    <!-- Core Booking Rules Card -->
                    <div class="rules-banner">
                        <h4>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            Core Booking Rules & Quality Promise
                        </h4>
                        <ul>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span><strong>5 Master Specialists:</strong> Every vehicle is personally diagnosed and repaired by a certified automotive technician.</span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span><strong>Strict Daily Capacity:</strong> Exactly 4 appointments per mechanic per day. No overbooking, guaranteeing focused attention.</span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span><strong>One Booking Per Day:</strong> To ensure fair access, clients (by phone or car license) cannot book more than once on the same date.</span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span><strong>Live Slot Display:</strong> Real-time slot countdown updates immediately when you switch appointment dates.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Workshop Facilities & Amenities -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Workshop Facilities & Services</h3>
                        </div>
                        <div class="card-body">
                            <ul class="facility-list">
                                <li class="facility-item">
                                    <div class="facility-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                            <line x1="8" y1="21" x2="16" y2="21"></line>
                                            <line x1="12" y1="17" x2="12" y2="21"></line>
                                        </svg>
                                    </div>
                                    <div class="facility-text">
                                        <strong>OEM Computerized Diagnostics</strong>
                                        <span>Full OBD-II ECU code scans, sensor calibration, and electronic fault isolation.</span>
                                    </div>
                                </li>

                                <li class="facility-item">
                                    <div class="facility-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M12 8v4l3 3"></path>
                                        </svg>
                                    </div>
                                    <div class="facility-text">
                                        <strong>Express Oil & Multi-Point Inspection</strong>
                                        <span>Full synthetic oil change, 35-point safety check, filter replacements and fluids top-up.</span>
                                    </div>
                                </li>

                                <li class="facility-item">
                                    <div class="facility-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                        </svg>
                                    </div>
                                    <div class="facility-text">
                                        <strong>Precision Brake & Suspension Rig</strong>
                                        <span>Computerized 3D laser alignment, rotor disc skimming, and shock absorber testing.</span>
                                    </div>
                                </li>

                                <li class="facility-item">
                                    <div class="facility-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                                            <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                                            <line x1="6" y1="1" x2="6" y2="4"></line>
                                            <line x1="10" y1="1" x2="10" y2="4"></line>
                                            <line x1="14" y1="1" x2="14" y2="4"></line>
                                        </svg>
                                    </div>
                                    <div class="facility-text">
                                        <strong>Executive Lounge & Free Wi-Fi</strong>
                                        <span>Quiet air-conditioned lounge, artisan coffee bar, workstation desks, and viewing window.</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Workshop Hours & Contact Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Hours & Workshop Location</h3>
                        </div>
                        <div class="card-body" style="font-size:0.875rem;">
                            <div style="margin-bottom:12px;">
                                <strong style="display:block;color:var(--gray-900);">Operating Hours:</strong>
                                <span class="text-muted">Monday – Saturday: 8:00 AM – 6:00 PM</span><br>
                                <span class="text-muted">Sunday: Emergency Repairs Only</span>
                            </div>
                            <div style="margin-bottom:12px;">
                                <strong style="display:block;color:var(--gray-900);">Workshop Address:</strong>
                                <span class="text-muted">742 Motorway Avenue, Auto Hub District</span>
                            </div>
                            <div>
                                <strong style="display:block;color:var(--gray-900);">Direct Helpline:</strong>
                                <span class="text-muted">+1 (800) 555-APEX / service@apexautocare.com</span>
                            </div>
                        </div>
                    </div>

                </aside>

            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p>&copy; <?= date('Y') ?> Apex Auto Specialists. All Rights Reserved. Built with PHP, MySQL, HTML5 &amp; CSS3.</p>
        </div>
    </footer>

    <!-- Client-Side Scripts -->
    <script src="assets/js/booking.js"></script>
</body>
</html>
