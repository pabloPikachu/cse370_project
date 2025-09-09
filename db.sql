-- db.sql — CampusGrid schema + demo data (non-overlapping)
DROP TABLE IF EXISTS booking_history;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS consultation_slots;
DROP TABLE IF EXISTS lecturers;
DROP TABLE IF EXISTS facilities;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(120) NOT NULL,
  department VARCHAR(80),
  phone VARCHAR(40),
  role ENUM('student','lecturer','admin') NOT NULL
);

CREATE TABLE lecturers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE,
  office_location VARCHAR(80),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE consultation_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id INT NOT NULL,
  day_of_week VARCHAR(10) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT DEFAULT 1,
  FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
);

CREATE TABLE facilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('classroom','lab','hall','field','auditorium','multipurpose') NOT NULL,
  capacity INT,
  code VARCHAR(40),
  status VARCHAR(40) DEFAULT 'available'
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booker_id INT NOT NULL,
  purpose VARCHAR(255) NOT NULL,
  start_dt DATETIME NOT NULL,
  end_dt DATETIME NOT NULL,
  facility_id INT,
  slot_id INT,
  status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booker_id) REFERENCES users(id),
  FOREIGN KEY (facility_id) REFERENCES facilities(id),
  FOREIGN KEY (slot_id) REFERENCES consultation_slots(id)
);

CREATE TABLE booking_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  old_status VARCHAR(30),
  new_status VARCHAR(30),
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT,
  receiver_id INT NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  booking_id INT,
  FOREIGN KEY (sender_id) REFERENCES users(id),
  FOREIGN KEY (receiver_id) REFERENCES users(id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- Seed users
INSERT INTO users (name,email,password,department,role) VALUES
('Admin','admin@campusgrid.edu','admin123',NULL,'admin'),
('Siam (Student 1)','stud1@bracu.ac.bd','stud123','CSE','student'),
('Student 2','stud2@bracu.ac.bd','stud223','EEE','student'),
('Dr. Rahman','lect1@bracu.ac.bd','lect123','CSE','lecturer'),
('Dr. Nabila','lect2@bracu.ac.bd','lect223','CSE','lecturer');

-- Lecturers profile
INSERT INTO lecturers (user_id, office_location) VALUES
((SELECT id FROM users WHERE email='lect1@bracu.ac.bd'),'UB3-101'),
((SELECT id FROM users WHERE email='lect2@bracu.ac.bd'),'UB3-102');

-- Consultation slots (no overlaps per lecturer)
INSERT INTO consultation_slots (lecturer_id, day_of_week, start_time, end_time, capacity) VALUES
(1,'Mon','10:00','11:00',5),
(1,'Wed','14:00','15:00',5),
(2,'Tue','11:00','12:00',5),
(2,'Thu','13:00','14:00',5);

-- Facilities
INSERT INTO facilities (name,type,capacity,code) VALUES
('UB401','classroom',40,'UB401'),
('UB402','classroom',40,'UB402'),
('CSE Lab 1','lab',30,'LAB1'),
('CSE Lab 2','lab',30,'LAB2'),
('Main Field','field',0,'FIELD1'),
('Multipurpose Hall 1','multipurpose',120,'MPH1'),
('Auditorium','auditorium',300,'AUD1');

-- Sample bookings (non-overlapping)
-- Student 1 books UB401 on 2025-08-24 09:00–11:00 (pending)
INSERT INTO bookings (booker_id,purpose,start_dt,end_dt,facility_id,slot_id,status) VALUES
((SELECT id FROM users WHERE email='stud1@bracu.ac.bd'),'Study group',
 '2025-08-24 09:00:00','2025-08-24 11:00:00',
 (SELECT id FROM facilities WHERE code='UB401'),NULL,'pending');

-- Student 2 books CSE Lab 1 on 2025-08-24 14:00–16:00 (approved)
INSERT INTO bookings (booker_id,purpose,start_dt,end_dt,facility_id,slot_id,status) VALUES
((SELECT id FROM users WHERE email='stud2@bracu.ac.bd'),'Practice session',
 '2025-08-24 14:00:00','2025-08-24 16:00:00',
 (SELECT id FROM facilities WHERE code='LAB1'),NULL,'approved');

-- Student 1 books consultation slot with Dr Rahman (Wed slot) (approved)
INSERT INTO bookings (booker_id,purpose,start_dt,end_dt,facility_id,slot_id,status) VALUES
((SELECT id FROM users WHERE email='stud1@bracu.ac.bd'),'Consultation about project',
 '2025-08-27 14:00:00','2025-08-27 15:00:00',
 NULL,(SELECT id FROM consultation_slots WHERE lecturer_id=1 AND day_of_week='Wed'),'approved');

-- Student 2 books Multipurpose Hall 1 12:00–14:00 (pending)
INSERT INTO bookings (booker_id,purpose,start_dt,end_dt,facility_id,slot_id,status) VALUES
((SELECT id FROM users WHERE email='stud2@bracu.ac.bd'),'Club orientation',
 '2025-08-25 12:00:00','2025-08-25 14:00:00',
 (SELECT id FROM facilities WHERE code='MPH1'),NULL,'pending');

-- Notifications reflecting existing bookings
INSERT INTO notifications (sender_id, receiver_id, message, booking_id) VALUES
((SELECT id FROM users WHERE email='admin@campusgrid.edu'), (SELECT id FROM users WHERE email='stud2@bracu.ac.bd'), 'Your booking for CSE Lab 1 is approved.', 2),
((SELECT id FROM users WHERE email='admin@campusgrid.edu'), (SELECT id FROM users WHERE email='stud1@bracu.ac.bd'), 'Your consultation booking is approved.', 3),
((SELECT id FROM users WHERE email='admin@campusgrid.edu'), (SELECT id FROM users WHERE email='stud2@bracu.ac.bd'), 'Your hall booking is pending admin approval.', 4);
