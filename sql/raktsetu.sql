-- ============================================================
--  RaktSetu — Blood Emergency Network
--  Full Database Schema
-- ============================================================



-- ── 1. users ────────────────────────────────────────────────
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    phone           VARCHAR(15),
    role            ENUM('donor','patient','hospital_staff','admin') NOT NULL DEFAULT 'donor',
    blood_type      ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-'),
    city            VARCHAR(100),
    state           VARCHAR(100),
    latitude        DECIMAL(10,7),
    longitude       DECIMAL(10,7),
    last_donated    DATE,
    is_eligible     TINYINT(1)    NOT NULL DEFAULT 1,
    is_verified     TINYINT(1)    NOT NULL DEFAULT 0,
    id_proof_path   VARCHAR(255),
    remember_token  VARCHAR(64),
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      ON UPDATE CURRENT_TIMESTAMP
);

-- ── 2. hospitals ─────────────────────────────────────────────
CREATE TABLE hospitals (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    address     TEXT          NOT NULL,
    city        VARCHAR(100)  NOT NULL,
    state       VARCHAR(100),
    latitude    DECIMAL(10,7),
    longitude   DECIMAL(10,7),
    phone       VARCHAR(15),
    is_verified TINYINT(1)    NOT NULL DEFAULT 0,
    cert_path   VARCHAR(255),
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── 3. blood_requests ────────────────────────────────────────
CREATE TABLE blood_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    requester_id    INT           NOT NULL,
    hospital_id     INT,
    blood_type      ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
    units_needed    INT           NOT NULL DEFAULT 1,
    units_fulfilled INT           NOT NULL DEFAULT 0,
    urgency         ENUM('critical','high','normal') NOT NULL DEFAULT 'normal',
    status          ENUM('open','in_progress','fulfilled','closed') NOT NULL DEFAULT 'open',
    patient_name    VARCHAR(100),
    notes           TEXT,
    needed_by       DATETIME,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id)  REFERENCES hospitals(id) ON DELETE SET NULL
);

-- ── 4. donor_responses ───────────────────────────────────────
CREATE TABLE donor_responses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    donor_id        INT           NOT NULL,
    request_id      INT           NOT NULL,
    status          ENUM('available','confirmed','donated','declined') NOT NULL DEFAULT 'available',
    responded_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    UNIQUE KEY uq_donor_request (donor_id, request_id)
);

-- ── 5. donations ─────────────────────────────────────────────
CREATE TABLE donations (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    donor_id                INT           NOT NULL,
    request_id              INT,
    hospital_id             INT,
    donated_on              DATE          NOT NULL,
    verified_by_hospital    TINYINT(1)    NOT NULL DEFAULT 0,
    certificate_path        VARCHAR(255),
    notes                   TEXT,
    created_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (hospital_id)REFERENCES hospitals(id) ON DELETE SET NULL
);

-- ── 6. blood_inventory ───────────────────────────────────────
CREATE TABLE blood_inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id     INT           NOT NULL,
    blood_type      ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
    units_available INT           NOT NULL DEFAULT 0,
    last_updated    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    UNIQUE KEY uq_hospital_type (hospital_id, blood_type)
);

-- ── 7. alerts ────────────────────────────────────────────────
CREATE TABLE alerts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    request_id  INT,
    type        ENUM('new_request','donor_confirmed','request_fulfilled','system') NOT NULL,
    message     VARCHAR(255)  NOT NULL,
    is_read     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL
);

-- ── 8. contact_messages ──────────────────────────────────────
CREATE TABLE contact_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    subject     VARCHAR(200),
    message     TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── Seed Data ────────────────────────────────────────────────
INSERT INTO hospitals (name, address, city, state, latitude, longitude, phone, is_verified) VALUES
('AIIMS New Delhi',       'Ansari Nagar East, New Delhi', 'New Delhi',  'Delhi',         28.5672, 77.2100, '011-26588500', 1),
('Fortis Hospital',       'Sector 44, Gurgaon',           'Gurgaon',    'Haryana',       28.4595, 77.0266, '0124-4921021', 1),
('PGI Chandigarh',        'Sector 12, Chandigarh',        'Chandigarh', 'Chandigarh',    30.7650, 76.7785, '0172-2756565', 1),
('Apollo Hyderabad',      'Film Nagar, Hyderabad',         'Hyderabad',  'Telangana',     17.4126, 78.4071, '040-23607777', 1),
('KEM Hospital Mumbai',   'Acharya Donde Marg, Mumbai',   'Mumbai',     'Maharashtra',   18.9920, 72.8335, '022-24107000', 1);

INSERT INTO blood_inventory (hospital_id, blood_type, units_available) VALUES
(1,'A+',45),(1,'A-',8),(1,'B+',32),(1,'B-',4),(1,'O+',56),(1,'O-',3),(1,'AB+',18),(1,'AB-',2),
(2,'A+',20),(2,'B+',15),(2,'O+',30),(2,'O-',5),(2,'AB-',1),
(3,'A+',12),(3,'B+',8),(3,'O+',22),(3,'O-',2);

-- Admin user  (password: Admin@123)
INSERT INTO users (name,email,password_hash,role,is_verified,is_eligible) VALUES
('Admin RaktSetu','admin@raktsetu.org','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',1,0);

-- ── Rich demo data (for demo day / viva) ────────────────────

-- Demo donors (password: Donor@123 for all)
INSERT IGNORE INTO users (name,email,password_hash,phone,role,blood_type,city,state,is_verified,is_eligible,last_donated) VALUES
('Arjun Mehta',     'arjun@demo.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234560','donor','O-', 'New Delhi',  'Delhi',       1,1,NULL),
('Sunita Rao',      'sunita@demo.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234561','donor','B+', 'Bangalore',  'Karnataka',   1,1,NULL),
('Ravi Kumar',      'ravi@demo.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234562','donor','O+', 'Chennai',    'Tamil Nadu',  1,1,'2024-06-15'),
('Deepika Singh',   'deepika@demo.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234563','donor','A+', 'Hyderabad',  'Telangana',   1,1,NULL),
('Fatima Begum',    'fatima@demo.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234564','donor','B-', 'Mumbai',     'Maharashtra', 1,0,'2024-09-20'),
('Karan Sharma',    'karan@demo.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234565','donor','AB+','New Delhi',  'Delhi',       1,1,NULL),
('Priya Nair',      'priya@demo.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234566','donor','A-', 'Gurgaon',    'Haryana',     1,1,NULL),
('Suresh Iyer',     'suresh@demo.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234567','donor','O-', 'Bangalore',  'Karnataka',   1,1,NULL),
('Anita Gupta',     'anita@demo.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234568','donor','B+', 'Mumbai',     'Maharashtra', 1,1,NULL),
('Mohammed Ansari', 'mohd@demo.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234569','donor','AB-','Hyderabad',  'Telangana',   1,1,NULL),
('Neha Verma',      'neha@demo.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234570','donor','A+', 'New Delhi',  'Delhi',       1,1,NULL),
('Vikram Pillai',   'vikram@demo.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','9871234571','donor','O+', 'Chennai',    'Tamil Nadu',  1,1,NULL);

-- Demo hospital staff
INSERT IGNORE INTO users (name,email,password_hash,role,city,state,is_verified) VALUES
('Dr. Prerna Malik', 'staff@aiims.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hospital_staff','New Delhi','Delhi',1);

-- Demo blood requests
INSERT INTO blood_requests (requester_id,hospital_id,blood_type,units_needed,units_fulfilled,urgency,notes,status,created_at) VALUES
(1,1,'O-',  3,1,'critical','Accident victim, RTA. Multiple injuries. Surgery in 2 hrs.',    'in_progress', NOW() - INTERVAL 2 HOUR),
(1,2,'AB-', 4,0,'critical','Rare blood type. Organ transplant patient. Very urgent.',        'open',        NOW() - INTERVAL 1 HOUR),
(1,3,'B+',  2,0,'high',    'Post-surgery complication. Patient stable but needs transfusion.','open',        NOW() - INTERVAL 3 HOUR),
(1,4,'O+',  2,2,'high',    'Trauma patient. Internal bleeding controlled.',                  'fulfilled',   NOW() - INTERVAL 6 HOUR),
(1,5,'A+',  1,0,'normal',  'Elective surgery scheduled for tomorrow morning.',               'open',        NOW() - INTERVAL 5 HOUR),
(1,1,'B-',  2,0,'normal',  'Cancer patient on chemotherapy. Routine transfusion.',           'open',        NOW() - INTERVAL 8 HOUR);

-- Demo donations
INSERT INTO donations (donor_id,request_id,hospital_id,donated_on,verified_by_hospital) VALUES
(2,4,4,'2024-09-15',1),
(3,4,4,'2024-09-15',1),
(4,1,1,'2024-10-01',1),
(6,NULL,1,'2024-08-20',1),
(7,NULL,3,'2024-07-10',1),
(8,NULL,1,'2024-06-05',1),
(8,NULL,2,'2024-03-12',1);

-- Demo donor responses
INSERT IGNORE INTO donor_responses (donor_id,request_id,status,responded_at) VALUES
(1,1,'confirmed',NOW() - INTERVAL 90 MINUTE),
(2,3,'available',NOW() - INTERVAL 45 MINUTE),
(9,5,'available',NOW() - INTERVAL 20 MINUTE);

-- Demo alerts
INSERT INTO alerts (user_id,request_id,type,message) VALUES
(2,3,'new_request','Emergency: 2 units of B+ needed at PGI Chandigarh. Urgency: High.'),
(1,1,'donor_confirmed','Arjun Mehta has responded to your O- blood request.'),
(3,4,'request_fulfilled','The O+ request at Apollo Hyderabad has been fulfilled. Thank you!');
