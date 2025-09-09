<?php

function require_login() {
    if (!isset($_SESSION)) session_start();
    if (empty($_SESSION['user'])) {
        header("Location: login.php");
        exit();
    }
}

function password_ok($plain, $hash_or_plain) {
  return $plain === $hash_or_plain;
}

function find_user_by_email(PDO $pdo, $email) {
  $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $st->execute([$email]);
  return $st->fetch();
}

function get_admins(PDO $pdo) {
  $st = $pdo->query("SELECT id, name, email FROM users WHERE role = 'admin'");
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

function notify(PDO $pdo, $sender_id, $receiver_id, $message, $booking_id=null) {
  $st = $pdo->prepare("INSERT INTO notifications (sender_id, receiver_id, message, booking_id) VALUES (?,?,?,?)");
  $st->execute([$sender_id, $receiver_id, $message, $booking_id]);
}

function overlaps(PDO $pdo, $facility_id, $start_dt, $end_dt, $exclude_booking_id = null) {
  // Returns true if any existing booking (not rejected/cancelled) overlaps
  $sql = "SELECT COUNT(*) AS c FROM bookings 
          WHERE facility_id = ? AND status IN ('pending','approved')
          AND start_dt < ? AND end_dt > ?";
  $params = [$facility_id, $end_dt, $start_dt];
  if ($exclude_booking_id) {
    $sql .= " AND id <> ?";
    $params[] = $exclude_booking_id;
  }
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $row = $st->fetch();
  return intval($row['c']) > 0;
}

function slot_is_full(PDO $pdo, $slot_id) {
  // Count non-rejected/cancelled bookings for this slot (legacy check)
  $st = $pdo->prepare("SELECT capacity FROM consultation_slots WHERE id = ?");
  $st->execute([$slot_id]);
  $slot = $st->fetch();
  if (!$slot) return true;
  $cap = intval($slot['capacity']);
  $st = $pdo->prepare("SELECT COUNT(*) AS c FROM bookings WHERE slot_id = ? AND status IN ('pending','approved')");
  $st->execute([$slot_id]);
  $cnt = intval($st->fetch()['c']);
  return $cnt >= $cap;
}

function add_history(PDO $pdo, $booking_id, $old_status, $new_status) {
  $st = $pdo->prepare("INSERT INTO booking_history (booking_id, old_status, new_status) VALUES (?,?,?)");
  $st->execute([$booking_id, $old_status, $new_status]);
}

function slot_capacity_used(PDO $pdo, $slot_id, $start_dt, $end_dt) {
  // Count bookings on this slot that overlap a specific time window
  $st = $pdo->prepare("SELECT COUNT(*) AS c FROM bookings 
                       WHERE slot_id = ? 
                         AND status IN ('pending','approved') 
                         AND start_dt < ? 
                         AND end_dt > ?");
  $st->execute([$slot_id, $end_dt, $start_dt]);
  $row = $st->fetch();
  return intval($row['c']);
}

function slot_has_capacity(PDO $pdo, $slot_id, $start_dt, $end_dt, $exclude_booking_id = null) {
  // True if overlapping bookings for the window are below the slot's capacity
  $st = $pdo->prepare("SELECT capacity FROM consultation_slots WHERE id = ?");
  $st->execute([$slot_id]);
  $slot = $st->fetch();
  if (!$slot) return false;
  $cap = intval($slot['capacity']);
  
  $sql = "SELECT COUNT(*) AS c FROM bookings 
          WHERE slot_id = ? AND status IN ('pending','approved')
          AND start_dt < ? AND end_dt > ?";
  $params = [$slot_id, $end_dt, $start_dt];
  
  if ($exclude_booking_id) {
    $sql .= " AND id <> ?";
    $params[] = $exclude_booking_id;
  }
  
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $used = intval($st->fetch()['c']);
  
  return $used < $cap;
}

function get_user_bookings(PDO $pdo, $user_id, $status = null) {
    $query = "SELECT b.*, f.name AS facility_name, f.code AS facility_code,
              cs.day_of_week, cs.start_time AS slot_start_time, cs.end_time AS slot_end_time,
              u.name AS lecturer_name
              FROM bookings b
              LEFT JOIN facilities f ON b.facility_id = f.id
              LEFT JOIN consultation_slots cs ON b.slot_id = cs.id
              LEFT JOIN lecturers l ON cs.lecturer_id = l.id
              LEFT JOIN users u ON l.user_id = u.id
              WHERE b.booker_id = ?";
    
    $params = [$user_id];
    
    if ($status) {
        $query .= " AND b.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY b.start_dt DESC";
    
    $st = $pdo->prepare($query);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function can_edit_booking($booking, $user) {
    // Only the booker can edit their own pending bookings
    return $booking['booker_id'] == $user['id'] && $booking['status'] === 'pending';
}

function format_booking_time($start_dt, $end_dt) {
    $start = new DateTime($start_dt);
    $end = new DateTime($end_dt);
    
    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
        return $start->format('M j, Y') . ' from ' . $start->format('g:i A') . ' to ' . $end->format('g:i A');
    } else {
        return $start->format('M j, Y g:i A') . ' to ' . $end->format('M j, Y g:i A');
    }
}

function get_lecturer_consultation_stats(PDO $pdo, $lecturer_id) {
    $stats = [];
    
    // Total slots
    $st = $pdo->prepare("SELECT COUNT(*) as count FROM consultation_slots WHERE lecturer_id = ?");
    $st->execute([$lecturer_id]);
    $stats['total_slots'] = $st->fetch()['count'];
    
    // Total bookings this month
    $st = $pdo->prepare("SELECT COUNT(*) as count FROM bookings b 
                         JOIN consultation_slots cs ON b.slot_id = cs.id 
                         WHERE cs.lecturer_id = ? AND b.status IN ('pending','approved')
                         AND MONTH(b.start_dt) = MONTH(CURRENT_DATE())
                         AND YEAR(b.start_dt) = YEAR(CURRENT_DATE())");
    $st->execute([$lecturer_id]);
    $stats['monthly_bookings'] = $st->fetch()['count'];
    
    // Pending bookings
    $st = $pdo->prepare("SELECT COUNT(*) as count FROM bookings b 
                         JOIN consultation_slots cs ON b.slot_id = cs.id 
                         WHERE cs.lecturer_id = ? AND b.status = 'pending'");
    $st->execute([$lecturer_id]);
    $stats['pending_bookings'] = $st->fetch()['count'];
    
    return $stats;
}

// No closing PHP tag