# CampusGrid — Core PHP + MySQL (Demo Data Included)

## Setup
1) Create a MySQL database named **campusgrid**.
2) Import `db.sql` into that database (e.g., via phpMyAdmin or `mysql -u root -p campusgrid < db.sql`).
3) Open `config.php` and set `$DB_USER` / `$DB_PASS` if needed.
4) Run a PHP server in this folder:
```
php -S 127.0.0.1:8080 -t .
```
5) Visit `http://127.0.0.1:8080/` → Login.

### Demo Accounts
- Admin: `admin@campusgrid.edu` / `admin123`
- Lecturer: `lect1@bracu.ac.bd` / `lect123`
- Student: `stud1@bracu.ac.bd` / `stud123`

## Features
- Non-overlapping facility bookings enforced server-side.
- Consultation slot capacity enforced.
- Admin panel to approve/reject bookings + booking history recorded.
- Notification feed for each user.
- Clean minimal CSS UI.

## Notes
- For production, replace plain passwords with `password_hash()` and add CSRF protection.
