# Apex Auto Workshop - Car Appointment Management System

A full-stack **PHP / MySQL / HTML5 / CSS3 / JavaScript** web application for managing vehicle repair and maintenance appointments at an automotive workshop.

---

## 🚀 Key Features & Core Rules

### 1. Core Business Rules
* **5 Mechanics Total:** The workshop operates with exactly 5 master specialists, each with a unique automotive discipline (Engine & Diagnostics, Transmission & Drivetrain, Brake & Suspension, Electrical/Hybrid, and Performance Tuning).
* **Max 4 Appointments Per Day Per Mechanic:** Each specialist is capped at 4 appointments daily to ensure top-quality craftsmanship and dedicated attention per vehicle.
* **Duplicate Booking Prevention:** Clients cannot book more than once on the same date. Duplicate attempts (matching client phone number or car license registration plate on that date) are rejected automatically.
* **Strict Capacity Enforcement:** The booking engine rejects any booking if the chosen specialist already has 4 confirmed appointments on the chosen date. Concurrency is protected via database transactions (`FOR UPDATE` locking).

### 2. User Panel (`index.php`)
* **Interactive Booking Form:**
  * Client Full Name
  * Street Address
  * Phone Number *(validated: numbers only, 7–15 digits)*
  * Car License Plate No *(e.g. `ABC-1234`)*
  * Car Engine No *(validated: numbers only, numeric block ID)*
  * Appointment Date *(validated: cannot be in the past)*
  * Mechanic Selection *(dynamic slot display)*
  * Optional Service Notes & Symptoms
* **Dynamic Slot Calculation:**
  * Changing the appointment date instantly triggers an AJAX request to fetch real-time remaining slots for each of the 5 mechanics.
  * Mechanics with 0 remaining slots are flagged as **Fully Booked** and disabled from selection.
  * Real-time badges indicate availability (`4 / 4 slots available`, `Only 1 slot left!`, `Fully Booked`).
* **Dual Validation (JavaScript + PHP):**
  * Client-side live input filtering and inline error feedback.
  * Server-side PDO validation ensuring empty field checks, strict digit checking (`ctype_digit`), date validity, duplicate prevention, and capacity limits.
* **Workshop Facilities & Help Text:**
  * Computerized OBD-II Diagnostics, Brake Resurfacing Lab, Express Oil & Multi-Point Inspection, and Customer Lounge amenities.
  * Clear booking policy guidelines and operating hours.
  * Live booking confirmation banner displaying reference number and appointment summary upon submission.

### 3. Admin Panel (`admin.php`)
* **KPI Dashboard Stats:** Total Appointments, Today's Appointments, Upcoming Bookings, and Active Specialists.
* **Search & Filter Toolbar:** Real-time search across Client Name, Phone, Car License No, filter by appointment date, or filter by specialist.
* **Appointments Table:**
  * **Client Name** (with Booking ID reference)
  * **Phone Number** (clickable `tel:` link)
  * **Car License (Registration) No** (monospace badge)
  * **Appointment Date** (formatted date)
  * **Assigned Mechanic** (specialist name and discipline)
  * **Status** (Confirmed / Completed / Cancelled badge)
  * **Actions** (Edit modal trigger and Cancel/Delete)
* **Edit Functionality:**
  * Allows administrators to modify the **Appointment Date** and assign a **different available mechanic**.
  * Dynamically queries mechanic capacity for the new date, excluding the current appointment from the count so maintaining the same specialist doesn't trigger false-positive capacity blocks.
  * Prevents moving a client to a date where they already have another active appointment.

---

## 📁 Project Directory Structure

```
php-appointment-system/
├── admin.php                      # Admin Management Panel & Table
├── index.php                      # Customer Booking Portal & Facilities
├── README.md                      # Documentation & Setup Guide
├── api/
│   ├── book_appointment.php       # POST: Validate & insert client booking
│   ├── delete_appointment.php     # POST: Delete / Cancel appointment
│   ├── get_appointment.php        # GET: Fetch single appointment by ID
│   ├── get_appointments.php       # GET: List appointments with filters & stats
│   ├── get_mechanics_availability.php # GET: Live slot counts for 5 mechanics
│   └── update_appointment.php     # POST: Admin edit date & mechanic assignment
├── assets/
│   ├── css/
│   │   ├── admin.css              # Admin-specific UI & modal styles
│   │   └── style.css              # Main design system & responsive layout
│   └── js/
│       ├── admin.js               # Admin table rendering, filtering & edit modal
│       └── booking.js             # Customer form validation & dynamic slots
├── config/
│   └── db.php                     # PDO connection, credentials, response helpers
└── database/
    ├── schema.sql                 # MySQL schema, indexes & initial seed data
    └── setup.php                  # 1-Click web & CLI database installer
```

---

## 🛠️ Database Setup

### Step 1: SQL Setup Script (`database/schema.sql`)
The system includes `database/schema.sql`, which sets up:
1. `car_workshop_db` database with `utf8mb4_unicode_ci`.
2. `mechanics` table pre-seeded with the **5 master mechanics**.
3. `appointments` table with foreign keys, cascading rules, and performance indexes.
4. Sample appointments to verify capacity countdowns out of the box.

To run via MySQL CLI:
```bash
mysql -u root -p < database/schema.sql
```

### Step 2: Auto-Installer Wizard
Alternatively, launch the auto-installer script in your browser or terminal:
```bash
php database/setup.php
```
Or open in browser: `http://localhost:8000/database/setup.php`

---

## ⚙️ Configuration (`config/db.php`)

Database settings can be configured via environment variables or directly in `config/db.php`:

```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'car_workshop_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
```

---

## 🏃 Running the Application

### Option A: Built-in PHP Web Server
If you have PHP installed on your machine:
```bash
cd /Users/prome/php-appointment-system
php -S localhost:8000
```
Then visit:
* **Customer Booking Portal:** [http://localhost:8000/index.php](http://localhost:8000/index.php)
* **Admin Management Panel:** [http://localhost:8000/admin.php](http://localhost:8000/admin.php)
* **Database Setup Wizard:** [http://localhost:8000/database/setup.php](http://localhost:8000/database/setup.php)

### Option B: XAMPP / MAMP / Apache
1. Copy the `php-appointment-system` directory to your web server root (`htdocs` or `www`).
2. Start Apache and MySQL services.
3. Import `database/schema.sql` via phpMyAdmin or run `http://localhost/php-appointment-system/database/setup.php`.
4. Open `http://localhost/php-appointment-system/index.php`.

---

## 🔒 Security & Validation Implementation

1. **Prepared Statements (PDO):** All database queries use parameterized prepared statements, preventing SQL injection vulnerabilities.
2. **XSS Protection:** All user inputs and database outputs rendered in HTML are sanitized via `htmlspecialchars()` and DOM text nodes.
3. **Data Integrity & Concurrency:** Booking and update transactions use `BEGIN TRANSACTION`, `SELECT ... FOR UPDATE`, and `COMMIT`/`ROLLBACK` to guarantee that two simultaneous bookings cannot exceed the 4-slot limit.
4. **Strict Type & Regex Checks:**
   * Phone Number: verified with `ctype_digit` and length between 7–15 digits.
   * Car Engine No: verified with `ctype_digit` and length between 4–50 digits.
   * Appointment Date: verified against valid calendar format (`YYYY-MM-DD`) and disallowed for past dates.
