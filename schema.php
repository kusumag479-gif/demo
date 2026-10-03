CREATE DATABASE IF NOT EXISTS lifeflow;
USE lifeflow;

-- Donor and user accounts
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    city VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Login tokens
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Blood banks
CREATE TABLE IF NOT EXISTS banks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    phone VARCHAR(20)
);

-- Blood stock in each bank
CREATE TABLE IF NOT EXISTS bank_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_id INT NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    units INT NOT NULL DEFAULT 0,
    UNIQUE (bank_id, blood_group),
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE CASCADE
);

-- Emergency blood requests
CREATE TABLE IF NOT EXISTS emergency_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(100) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    units_needed INT NOT NULL,
    hospital VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    status VARCHAR(30) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Donation appointments
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bank_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status VARCHAR(30) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (bank_id, appointment_date, appointment_time),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (bank_id) REFERENCES banks(id)
);

-- Demo blood banks
INSERT INTO banks (name, address, city, phone) VALUES
('LifeFlow Central Blood Bank', 'Malleswaram', 'Bengaluru', '0800000001'),
('LifeFlow City Blood Bank', 'Rajajinagar', 'Bengaluru', '0800000002');

-- Demo stock
INSERT INTO bank_stock (bank_id, blood_group, units) VALUES
(1, 'A+', 10),
(1, 'A-', 5),
(1, 'B+', 12),
(1, 'B-', 4),
(1, 'AB+', 6),
(1, 'AB-', 3),
(1, 'O+', 15),
(1, 'O-', 5),
(2, 'A+', 8),
(2, 'A-', 3),
(2, 'B+', 9),
(2, 'B-', 2),
(2, 'AB+', 4),
(2, 'AB-', 2),
(2, 'O+', 11),
(2, 'O-', 3);