1. Introduction

CampusGrid is a web-based system designed to simplify facility booking and consultation management for students, lecturers, and administrators within the university.

The system integrates three main user roles:

Students → request facilities, book consultations, and track notifications.
Lecturers → create consultation slots and manage bookings with students.
Admins → approve/reject facility bookings, manage facilities, and oversee system operations.

This manual provides step-by-step instructions on installation, login, system features, and troubleshooting, ensuring smooth usage during daily academic activities.

 2. Installation & Setup

To run CampusGrid locally:

Download Source Code – Extract the project files.
 Place Files – Copy the project folder into your web server directory (`htdocs` for        XAMPP).
 Database Setup – Import the provided `.sql` file into MySQL using phpMyAdmin.
 Update Configurations** – Edit `dbconnect.php` and set your database username, password, and database name.
 Start Server – Run Apache and MySQL in XAMPP.
 Launch Project – Open a browser and visit:


http://localhost/campusgrid
   



 3. Login & Authentication

CampusGrid uses role-based authentication.
Here are the default test accounts:
<img width="715" height="152" alt="image" src="https://github.com/user-attachments/assets/2aae4154-6107-4196-ab45-850d2b45f3b9" />


 New accounts can be created through the database .

<img width="1912" height="927" alt="image" src="https://github.com/user-attachments/assets/114c692e-60df-4c2e-a4f6-dfd93c5797c6" />



4. User Roles & Features

Student Features
<img width="1918" height="903" alt="image" src="https://github.com/user-attachments/assets/aa23f5db-079d-495d-ac7c-1161c7269659" />


View Facilities – Browse available labs, classrooms, or seminar halls.
Make Booking – Submit a request for a facility with preferred date/time.
Book Consultation – Select available lecturer slots and confirm booking.
Check My Bookings – Track approval status (Pending/Approved/Rejected).
Receive Notifications – Stay updated about booking approvals/rejections.



Lecturer Features
<img width="1917" height="893" alt="image" src="https://github.com/user-attachments/assets/e73293aa-05ca-4821-8395-6e02b621dd9b" />

Create Consultation Slots – Define weekly or one-time slots for student meetings.
View Bookings – See which students booked available slots.
Manage Slots – Update, edit, or cancel consultation timings.





Admin Features

<img width="1918" height="902" alt="image" src="https://github.com/user-attachments/assets/1237cea3-576c-4851-b566-15acf4ee79b6" />


Dashboard – View all pending student booking requests.
Approve/Reject Requests – Decide availability of facilities.
Manage Facilities – Add, update, or remove resources (labs, halls, rooms).
System Oversight – Monitor all bookings, users, and notifications.
Notifications – Receive booking requests and send approval updates.


5. System Rules & Logic

Facility Booking
<img width="1917" height="921" alt="image" src="https://github.com/user-attachments/assets/53c26d7e-dd49-4d7e-94ee-8bb3d2bf45c2" />


  Student requests → marked as Pending.
  Admin approves/rejects → status updated accordingly.


Admin Booking

   Bookings made by admins are auto-approved.

Consultation Booking
<img width="1917" height="908" alt="image" src="https://github.com/user-attachments/assets/9bdbdd7e-53f6-4017-8e66-8f65358f5bea" />


   Students can only book available lecturer slots.
  No double-booking allowed (system prevents overlaps).

Conflict Prevention

   The system checks time conflicts before confirming any booking.


 
 8. Conclusion

CampusGrid provides an integrated solution for managing campus facilities and consultations.
It ensures:

 Transparency in resource management.
 Reduced scheduling conflicts.
 Efficient communication between students, lecturers, and admins.

With a user-friendly interface and reliable database-driven backend, CampusGrid demonstrates the power of web technologies in solving real-life campus management problems.
