
-- Mawgifi Parking System Database
-- Created: December 8, 2025


-- Drop database if exists and create new one
DROP DATABASE IF EXISTS mawgifi;
CREATE DATABASE mawgifi;
USE mawgifi;


-- Table: User
-- Description: Stores user information

CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    UserName VARCHAR(100) NOT NULL,
    Email VARCHAR(150) UNIQUE NOT NULL,
    PhoneNumber VARCHAR(20),
    UserType ENUM('admin', 'user', 'staff') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    Address TEXT,
    INDEX idx_email (Email),
    INDEX idx_usertype (UserType)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: Vehicle
-- Description: Stores vehicle information linked to users

CREATE TABLE Vehicle (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_type VARCHAR(50),
    vehicle_model VARCHAR(100),
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    grant_document VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT,
    Approved_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES User(user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_license_plate (license_plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: Event
-- Description: Stores event information

CREATE TABLE Event (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50),
    event_time DATETIME,
    duration_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_name VARCHAR(150),
    RecordReport TEXT,
    INDEX idx_event_time (event_time),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: Availability
-- Description: Stores availability schedules

CREATE TABLE Availability (
    Availability_id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
    date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    INDEX idx_date (date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: ParkingArea
-- Description: Stores parking area information

CREATE TABLE ParkingArea (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    Availability_id INT,
    area_name VARCHAR(100) NOT NULL,
    area_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    AreaSize DECIMAL(10, 2),
    FOREIGN KEY (Availability_id) REFERENCES Availability(Availability_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_area_name (area_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: ParkingSpace
-- Description: Stores individual parking space information

CREATE TABLE ParkingSpace (
    Space_id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    Availability_id INT,
    space_number VARCHAR(20) NOT NULL,
    qr_code VARCHAR(255) UNIQUE,
    qr_img_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES ParkingArea(area_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (Availability_id) REFERENCES Availability(Availability_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_area_id (area_id),
    INDEX idx_space_number (space_number),
    UNIQUE KEY unique_space (area_id, space_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: Booking
-- Description: Stores booking information

CREATE TABLE Booking (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    event_id INT,
    Space_id INT,
    Availability_id INT,
    booking_end DATETIME,
    booking_qr_code VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    Booking_invoice VARCHAR(255),
    booking_start DATETIME,
    FOREIGN KEY (vehicle_id) REFERENCES Vehicle(vehicle_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (event_id) REFERENCES Event(event_id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (Space_id) REFERENCES ParkingSpace(Space_id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (Availability_id) REFERENCES Availability(Availability_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_booking_start (booking_start),
    INDEX idx_booking_end (booking_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Insert Sample Data


-- Sample Users
INSERT INTO User (UserName, Email, PhoneNumber, UserType, password, Address) VALUES
('Admin User', 'admin@example.com', '1234567890', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123 Admin Street'),
('Staff User', 'staff@example.com', '5556667777', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '456 Staff Boulevard'),
('John Doe', 'student@example.com', '9876543210', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '789 Student Avenue'),
('Jane Smith', 'student2@example.com', '5551234567', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '321 Campus Road');

-- Sample Vehicles
INSERT INTO Vehicle (user_id, vehicle_type, vehicle_model, license_plate, approved_by, Approved_date) VALUES
(3, 'Car', 'Toyota Camry', 'ABC123', 1, NOW()),
(3, 'Motorcycle', 'Honda CBR', 'XYZ789', 1, NOW()),
(4, 'Car', 'BMW 3 Series', 'DEF456', 1, NOW());

-- Sample Events
INSERT INTO Event (event_type, event_time, duration_minutes, event_name, RecordReport) VALUES
('Conference', '2025-12-15 09:00:00', 480, 'Tech Conference 2025', 'Annual technology conference'),
('Concert', '2025-12-20 18:00:00', 240, 'Music Festival', 'Evening music event'),
('Sports', '2025-12-25 14:00:00', 180, 'Football Match', 'Local football championship');

-- Sample Availability
INSERT INTO Availability (status, date, start_time, end_time) VALUES
('available', '2025-12-10', '08:00:00', '18:00:00'),
('available', '2025-12-11', '08:00:00', '18:00:00'),
('occupied', '2025-12-12', '08:00:00', '18:00:00'),
('maintenance', '2025-12-13', '08:00:00', '18:00:00');

-- Sample Parking Areas
INSERT INTO ParkingArea (Availability_id, area_name, area_type, AreaSize) VALUES
(1, 'Area A', 'Standard', 500.00),
(2, 'Area B', 'Premium', 300.00),
(3, 'Area C', 'VIP', 200.00);

-- Sample Parking Spaces
INSERT INTO ParkingSpace (area_id, Availability_id, space_number, qr_code, qr_img_path) VALUES
(1, 1, 'A-01', 'QR_A01_001', '/qrcodes/a01.png'),
(1, 1, 'A-02', 'QR_A02_002', '/qrcodes/a02.png'),
(1, 2, 'A-03', 'QR_A03_003', '/qrcodes/a03.png'),
(2, 2, 'B-01', 'QR_B01_004', '/qrcodes/b01.png'),
(2, 3, 'B-02', 'QR_B02_005', '/qrcodes/b02.png'),
(3, 4, 'C-01', 'QR_C01_006', '/qrcodes/c01.png');

-- Sample Bookings
INSERT INTO Booking (vehicle_id, event_id, Space_id, Availability_id, booking_start, booking_end, booking_qr_code, Booking_invoice) VALUES
(1, 1, 1, 1, '2025-12-10 08:00:00', '2025-12-10 18:00:00', 'BOOKING_QR_001', 'INV-001'),
(2, 2, 4, 2, '2025-12-11 08:00:00', '2025-12-11 18:00:00', 'BOOKING_QR_002', 'INV-002'),
(3, 3, 6, 4, '2025-12-13 08:00:00', '2025-12-13 18:00:00', 'BOOKING_QR_003', 'INV-003');


-- Create Views for Common Queries


-- View: Active Bookings with Details
CREATE VIEW active_bookings AS
SELECT 
    b.booking_id,
    u.UserName,
    u.Email,
    v.license_plate,
    v.vehicle_type,
    ps.space_number,
    pa.area_name,
    b.booking_start,
    b.booking_end,
    a.status,
    e.event_name
FROM Booking b
JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
JOIN User u ON v.user_id = u.user_id
LEFT JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id
LEFT JOIN Availability a ON b.Availability_id = a.Availability_id
LEFT JOIN Event e ON b.event_id = e.event_id
WHERE b.booking_end >= NOW();

-- View: Available Parking Spaces
CREATE VIEW available_parking_spaces AS
SELECT 
    ps.Space_id,
    ps.space_number,
    pa.area_name,
    pa.area_type,
    a.status,
    a.date
FROM ParkingSpace ps
JOIN ParkingArea pa ON ps.area_id = pa.area_id
LEFT JOIN Availability a ON ps.Availability_id = a.Availability_id
WHERE a.status = 'available';

-- View: User Vehicle Summary
CREATE VIEW user_vehicle_summary AS
SELECT 
    u.user_id,
    u.UserName,
    u.Email,
    COUNT(v.vehicle_id) as total_vehicles,
    GROUP_CONCAT(v.license_plate SEPARATOR ', ') as vehicle_plates
FROM User u
LEFT JOIN Vehicle v ON u.user_id = v.user_id
GROUP BY u.user_id, u.UserName, u.Email;


-- Create Stored Procedures


DELIMITER //

-- Procedure: Get Available Spaces by Date
CREATE PROCEDURE GetAvailableSpaces(IN search_date DATE)
BEGIN
    SELECT 
        ps.Space_id,
        ps.space_number,
        pa.area_name,
        pa.area_type,
        a.start_time,
        a.end_time
    FROM ParkingSpace ps
    JOIN ParkingArea pa ON ps.area_id = pa.area_id
    JOIN Availability a ON ps.Availability_id = a.Availability_id
    WHERE a.date = search_date 
    AND a.status = 'available'
    AND ps.Space_id NOT IN (
        SELECT Space_id FROM Booking 
        WHERE DATE(booking_start) = search_date
    );
END //

-- Procedure: Book Parking Space
CREATE PROCEDURE BookParkingSpace(
    IN p_vehicle_id INT,
    IN p_space_id INT,
    IN p_availability_id INT,
    IN p_start DATETIME,
    IN p_end DATETIME,
    IN p_event_id INT
)
BEGIN
    DECLARE booking_qr VARCHAR(255);
    DECLARE invoice_num VARCHAR(255);
    
    SET booking_qr = CONCAT('BKG_', UUID());
    SET invoice_num = CONCAT('INV-', LPAD(FLOOR(RAND() * 99999), 5, '0'));
    
    INSERT INTO Booking (
        vehicle_id, 
        Space_id, 
        Availability_id, 
        booking_start, 
        booking_end, 
        event_id,
        booking_qr_code,
        Booking_invoice
    ) VALUES (
        p_vehicle_id, 
        p_space_id, 
        p_availability_id, 
        p_start, 
        p_end, 
        p_event_id,
        booking_qr,
        invoice_num
    );
    
    SELECT LAST_INSERT_ID() as booking_id, booking_qr, invoice_num;
END //

DELIMITER ;


