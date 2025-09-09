<?php
require_once "config.php";
require_once "lib/functions.php";
require_login();

$u = $_SESSION['user'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the booking
$st = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
$st->execute([$booking_id]);
$booking = $st->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header("Location: dashboard.php?error=booking_not_found");
    exit();
}

// Check permissions: only the booker can cancel their own pending bookings
if ($booking['booker_id'] !== $u['id'] || !in_array($booking['status'], ['pending', 'approved'])) {
    header("Location: dashboard.php?error=no_permission");
    exit();
}

// Update booking status to cancelled
$st = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
$st->execute([$booking_id]);

// Add to booking history
$st = $pdo->prepare("INSERT INTO booking_history (booking_id, old_status, new_status) VALUES (?, ?, 'cancelled')");
$st->execute([$booking_id, $booking['status']]);

// Notify admins
foreach (get_admins($pdo) as $admin) {
    notify($pdo, $u['id'], $admin['id'], "Booking #$booking_id has been cancelled by user", $booking_id);
}

header("Location: dashboard.php?cancelled=1");
exit();