-- ============================================================
-- CINEMA DATABASE
-- ============================================================

CREATE DATABASE IF NOT EXISTS CINEMA_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE CINEMA_DB;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(80)  NOT NULL UNIQUE,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','moderator','client') NOT NULL DEFAULT 'client',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME DEFAULT NULL
);

-- ============================================================
-- TABLE: movies
-- ============================================================
CREATE TABLE movies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    genre        VARCHAR(80)  NOT NULL,
    duration     INT          NOT NULL COMMENT 'Duration in minutes',
    description  TEXT,
    release_year YEAR,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at   DATETIME DEFAULT NULL
);

-- ============================================================
-- TABLE: rooms
-- ============================================================
CREATE TABLE rooms (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL,
    capacity   INT         NOT NULL,
    deleted_at DATETIME DEFAULT NULL
);

-- ============================================================
-- TABLE: screenings
-- ============================================================
CREATE TABLE screenings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    movie_id        INT  NOT NULL,
    room_id         INT  NOT NULL,
    screening_date  DATE NOT NULL,
    screening_time  TIME NOT NULL,
    price           DECIMAL(6,2) NOT NULL DEFAULT 8.50,
    seats_available INT NOT NULL,
    deleted_at      DATETIME DEFAULT NULL,
    FOREIGN KEY (movie_id) REFERENCES movies(id),
    FOREIGN KEY (room_id)  REFERENCES rooms(id)
);

-- ============================================================
-- TABLE: reservations
-- ============================================================
CREATE TABLE reservations (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT  NOT NULL,
    screening_id INT  NOT NULL,
    seats        INT  NOT NULL DEFAULT 1,
    status       ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    reserved_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at   DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id),
    FOREIGN KEY (screening_id) REFERENCES screenings(id)
);

-- ============================================================
-- TABLE: audit_log  (for triggers)
-- ============================================================
CREATE TABLE audit_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(100) NOT NULL,
    table_name VARCHAR(80)  NOT NULL,
    record_id  INT,
    performed_by VARCHAR(80),
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    details    TEXT
);

-- ============================================================
-- TRIGGER 1: After reservation → decrease seats_available
-- ============================================================
DELIMITER $$
CREATE TRIGGER trg_after_reservation_insert
AFTER INSERT ON reservations
FOR EACH ROW
BEGIN
    UPDATE screenings
    SET seats_available = seats_available - NEW.seats
    WHERE id = NEW.screening_id;

    INSERT INTO audit_log (action, table_name, record_id, details)
    VALUES ('INSERT', 'reservations', NEW.id,
        CONCAT('User ', NEW.user_id, ' reserved ', NEW.seats, ' seat(s) for screening ', NEW.screening_id));
END$$
DELIMITER ;

-- ============================================================
-- TRIGGER 2: After reservation cancelled → restore seats
-- ============================================================
DELIMITER $$
CREATE TRIGGER trg_after_reservation_update
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status = 'confirmed' THEN
        UPDATE screenings
        SET seats_available = seats_available + OLD.seats
        WHERE id = OLD.screening_id;

        INSERT INTO audit_log (action, table_name, record_id, details)
        VALUES ('CANCEL', 'reservations', OLD.id,
            CONCAT('Reservation ', OLD.id, ' cancelled — ', OLD.seats, ' seat(s) restored'));
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- TRIGGER 3: Soft-delete movie → soft-delete its screenings
-- ============================================================
DELIMITER $$
CREATE TRIGGER trg_after_movie_softdelete
AFTER UPDATE ON movies
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
        UPDATE screenings
        SET deleted_at = NOW()
        WHERE movie_id = OLD.id AND deleted_at IS NULL;

        INSERT INTO audit_log (action, table_name, record_id, details)
        VALUES ('SOFT_DELETE_CASCADE', 'movies', OLD.id,
            CONCAT('Movie "', OLD.title, '" deleted — screenings cascade-deleted'));
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- STORED PROCEDURE 1: Get screenings for a movie (with stats)
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_get_movie_screenings(IN p_movie_id INT)
BEGIN
    SELECT
        s.id,
        s.screening_date,
        s.screening_time,
        s.price,
        s.seats_available,
        r.name  AS room_name,
        r.capacity,
        (r.capacity - s.seats_available) AS seats_taken,
        COUNT(res.id) AS total_reservations
    FROM screenings s
    JOIN rooms r ON s.room_id = r.id
    LEFT JOIN reservations res ON res.screening_id = s.id AND res.status = 'confirmed'
    WHERE s.movie_id = p_movie_id
      AND s.deleted_at IS NULL
    GROUP BY s.id
    ORDER BY s.screening_date, s.screening_time;
END$$
DELIMITER ;

-- ============================================================
-- STORED PROCEDURE 2: Get user reservation history
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_user_reservations(IN p_user_id INT)
BEGIN
    SELECT
        r.id          AS reservation_id,
        m.title       AS movie_title,
        m.genre,
        ro.name       AS room_name,
        s.screening_date,
        s.screening_time,
        r.seats,
        (r.seats * s.price) AS total_paid,
        r.status,
        r.reserved_at
    FROM reservations r
    JOIN screenings s ON r.screening_id = s.id
    JOIN movies     m ON s.movie_id = m.id
    JOIN rooms      ro ON s.room_id = ro.id
    WHERE r.user_id = p_user_id
      AND r.deleted_at IS NULL
    ORDER BY r.reserved_at DESC;
END$$
DELIMITER ;

-- ============================================================
-- STORED PROCEDURE 3: Dashboard stats for admin
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_dashboard_stats()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM movies   WHERE deleted_at IS NULL) AS total_movies,
        (SELECT COUNT(*) FROM screenings WHERE deleted_at IS NULL) AS total_screenings,
        (SELECT COUNT(*) FROM reservations WHERE status = 'confirmed' AND deleted_at IS NULL) AS total_reservations,
        (SELECT COUNT(*) FROM users    WHERE deleted_at IS NULL) AS total_users,
        (SELECT COALESCE(SUM(r.seats * s.price),0)
         FROM reservations r
         JOIN screenings s ON r.screening_id = s.id
         WHERE r.status = 'confirmed') AS total_revenue;
END$$
DELIMITER ;

-- ============================================================
-- SEED DATA
-- ============================================================
INSERT INTO users (username, email, password, role) VALUES
('admin',    'admin@cinema.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('moderator','mod@cinema.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator'),
('john_doe', 'john@example.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client');
-- Default password for all: "password"

INSERT INTO rooms (name, capacity) VALUES
('Hall A', 120),
('Hall B', 80),
('VIP Lounge', 30);

INSERT INTO movies (title, genre, duration, description, release_year) VALUES
('Inception',        'Sci-Fi',   148, 'A thief who steals corporate secrets through dreams.',       2010),
('The Dark Knight',  'Action',   152, 'Batman faces the Joker in a battle for Gotham.',             2008),
('Interstellar',     'Sci-Fi',   169, 'Explorers travel through a wormhole near Saturn.',           2014),
('Parasite',         'Thriller', 132, 'A poor family schemes to infiltrate a wealthy household.',   2019),
('Dune',             'Sci-Fi',   155, 'A noble family controls the most valuable resource.',        2021);

INSERT INTO screenings (movie_id, room_id, screening_date, screening_time, price, seats_available) VALUES
(1, 1, CURDATE(), '14:00:00', 9.50,  120),
(1, 2, CURDATE(), '20:00:00', 11.00,  80),
(2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '16:00:00', 9.50, 120),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00', 15.00, 30),
(4, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:30:00', 9.50,  80),
(5, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '21:00:00', 9.50, 120);
