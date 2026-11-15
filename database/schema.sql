-- =====================================================================
-- Car Workshop Appointment System - Database Schema & Setup Script
-- =====================================================================

-- 1. Create Database if not exists
CREATE DATABASE IF NOT EXISTS `car_workshop_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `car_workshop_db`;

-- 2. Drop existing tables if re-initializing (appointments first due to FK)
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `mechanics`;

-- 3. Mechanics Table
-- System is restricted to 5 mechanics total, with a max of 4 appointments per day each
CREATE TABLE `mechanics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `specialty` VARCHAR(150) NOT NULL DEFAULT 'General Auto Repair',
    `max_daily_slots` INT NOT NULL DEFAULT 4,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Appointments Table
-- Holds client details, vehicle details, date, and assigned mechanic
CREATE TABLE `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(150) NOT NULL,
    `client_address` TEXT NOT NULL,
    `client_phone` VARCHAR(25) NOT NULL,
    `car_license_no` VARCHAR(50) NOT NULL,
    `car_engine_no` VARCHAR(50) NOT NULL,
    `appointment_date` DATE NOT NULL,
    `mechanic_id` INT NOT NULL,
    `notes` TEXT NULL,
    `status` ENUM('confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key to mechanics
    CONSTRAINT `fk_appointment_mechanic` 
        FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Performance and Query Optimization Indexes
-- Optimize lookup for daily appointments per mechanic (capacity check)
CREATE INDEX `idx_mechanic_date` ON `appointments` (`mechanic_id`, `appointment_date`, `status`);

-- Optimize lookup for client appointments on a specific date (duplicate booking check)
CREATE INDEX `idx_phone_date` ON `appointments` (`client_phone`, `appointment_date`, `status`);
CREATE INDEX `idx_license_date` ON `appointments` (`car_license_no`, `appointment_date`, `status`);

-- 6. Seed Data: Exactly 5 Mechanics
INSERT INTO `mechanics` (`id`, `name`, `specialty`, `max_daily_slots`, `status`) VALUES
(1, 'Marcus Vance', 'Master Diagnostics & Engine Specialist', 4, 'active'),
(2, 'Sarah Jenkins', 'Transmission & Drivetrain Expert', 4, 'active'),
(3, 'David Rodriguez', 'Brake Systems & Suspension Specialist', 4, 'active'),
(4, 'Elena Rostova', 'Electrical & Hybrid Systems Technician', 4, 'active'),
(5, 'Kenji Tanaka', 'Performance Tuning & General Maintenance', 4, 'active');

-- 7. Seed Sample Appointments for Demonstration & Testing
-- Seed appointments on current date and tomorrow to demonstrate remaining slot calculation
INSERT INTO `appointments` 
(`client_name`, `client_address`, `client_phone`, `car_license_no`, `car_engine_no`, `appointment_date`, `mechanic_id`, `notes`, `status`)
VALUES
('Arthur Pendelton', '742 Evergreen Terrace, Springfield', '5550192834', 'ABC-1234', '98451203', CURDATE(), 1, 'Periodic 10k mile maintenance and synthetic oil change', 'confirmed'),
('Beatrice Miller', '12 Oak Ridge Blvd, Maplewood', '5550183921', 'XYZ-9876', '45812930', CURDATE(), 1, 'Engine check light diagnostic and sensor scan', 'confirmed'),
('Charles Sterling', '88 Riverbend Way, Austin', '5550174829', 'TEX-4421', '12093847', CURDATE(), 2, 'Transmission fluid flush and gear slipping check', 'confirmed'),
('Diana Prince', '350 Fifth Ave, Metropolis', '5550165738', 'NY-7800', '88392019', CURDATE(), 3, 'Ceramic brake pad replacement and rotor resurfacing', 'confirmed'),
('Ethan Hunt', '100 Mission St, San Francisco', '5550156647', 'CA-0077', '73629104', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, 'Suspension noise diagnosis over speed bumps', 'confirmed'),
('Fiona Gallagher', '211 North Side Lane, Chicago', '5550147556', 'IL-5542', '66201948', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 'Clutch adjustment and safety inspection', 'confirmed');
