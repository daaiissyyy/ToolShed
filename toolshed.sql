-- ToolShed database

CREATE DATABASE IF NOT EXISTS toolshed;
USE toolshed;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    item_condition VARCHAR(30) DEFAULT 'Good',
    status ENUM('available', 'borrowed') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE borrow_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    borrower_id INT NOT NULL,
    status ENUM('pending', 'approved', 'declined', 'returned') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (borrower_id) REFERENCES users(id) ON DELETE CASCADE
);

-- a couple of sample rows so the app isn't empty on first run
INSERT INTO users (username, password) VALUES
('demo_user', '$2y$10$4Vd1QwF5c2b8YV3q9Q9F9OeQ2eYQyN0h6r2m1z6f9GfF5Q3n2q3S6'); -- password: demo123

INSERT INTO items (owner_id, title, description, category, item_condition) VALUES
(1, 'DSLR Camera (Canon 1200D)', 'Basic DSLR, comes with an 18-55mm kit lens. Great for photography assignments.', 'Electronics', 'Good'),
(1, 'Soldering Iron Kit', 'Includes iron, stand, and solder wire. Used for CSE 2xxx lab projects.', 'Lab Tools', 'Fair');
