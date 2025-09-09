<?php
require_once "config.php"; 
require_once "lib/functions.php";
require_login();
$u = $_SESSION['user'];

// Handle status messages
$success_msg = "";
if (isset($_GET['booked'])) {
    $status = $_GET['status'] ?? 'pending';
    if ($status === 'approved') {
        $success_msg = "Booking created and automatically approved!";
    } else {
        $success_msg = "Booking request submitted! It's pending admin approval.";
    }
} elseif (isset($_GET['updated'])) {
    $success_msg = "Booking updated successfully!";
} elseif (isset($_GET['cancelled'])) {
    $success_msg = "Booking cancelled successfully!";
}

$error_msg = "";
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    switch($error) {
        case 'booking_not_found':
            $error_msg = "Booking not found.";
            break;
        case 'no_permission':
            $error_msg = "You don't have permission to edit this booking.";
            break;
        case 'access_denied':
            $error_msg = "Access denied.";
            break;
        default:
            $error_msg = "An error occurred.";
    }
}

// Get user's bookings
$user_bookings = get_user_bookings($pdo, $u['id']);

// Separate bookings by status
$pending_bookings = array_filter($user_bookings, function($b) { return $b['status'] === 'pending'; });
$approved_bookings = array_filter($user_bookings, function($b) { return $b['status'] === 'approved'; });
$other_bookings = array_filter($user_bookings, function($b) { return !in_array($b['status'], ['pending', 'approved']); });

// Get notifications for this user
$st = $pdo->prepare("SELECT * FROM notifications WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 5");
$st->execute([$u['id']]);
$notifications = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Dashboard — CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css"/>
<style>
  .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
  .action-card { background: #f8f9fa; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #e9ecef; text-decoration: none; color: inherit; display: block; }
  .action-card:hover { background: #e9ecef; text-decoration: none; color: inherit; }
  .booking-section { margin-bottom: 24px; }
  .booking-item { background: #fff; border: 1px solid #e9ecef; border-radius: 4px; padding: 12px; margin-bottom: 8px; }
  .booking-meta { font-size: 14px; color: #6c757d; margin-top: 4px; }
  .booking-actions { margin-top: 8px; }
  .booking-actions .btn { font-size: 12px; margin-right: 8px; }
  .status-pending { color: #856404; background: #fff3cd; }
  .status-approved { color: #155724; background: #d4edda; }
  .status-rejected { color: #721c24; background: #f8d7da; }
  .status-cancelled { color: #6c757d; background: #e2e3e5; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
</style>
</head>
<body>
<div class="header">
  <div class="brand">CampusGrid</div>
  <div><?php echo htmlspecialchars($u['name'])." (".htmlspecialchars($u['role']).")"; ?> — <a href="logout.php">Logout</a></div>
</div>

<div class="container">
  <?php if ($success_msg): ?>
    <div class="notice-success"><?=htmlspecialchars($success_msg)?></div>
  <?php endif; ?>
  
  <?php if ($error_msg): ?>
    <div class="notice-error"><?=htmlspecialchars($error_msg)?></div>
  <?php endif; ?>

  <?php if(isset($_GET['booked']) && $_GET['booked']=='1'){ 
    // Check if status is passed (for determining auto-approved vs pending)
    $status = $_GET['status'] ?? '';
    
    // Determine the message based on status or user role
    if ($status === 'approved' || ($u['role'] === 'admin' && $status !== 'pending')) {
      // Admin facility bookings are auto-approved
      $message = "<strong>Success:</strong> Facility booked successfully.";
    } else {
      // Regular users and consultation bookings need approval
      $message = "<strong>Success:</strong> Booking submitted and pending admin approval.";
    }
  ?>
  <div id="bookedNotice" class="notice-success">
    <?php echo $message; ?>
    <button type="button" aria-label="Dismiss" onclick="document.getElementById('bookedNotice').style.display='none';">×</button>
  </div>
  <script>
    setTimeout(function(){
      var n = document.getElementById('bookedNotice');
      if(n){ n.style.display='none'; }
    }, 5000);
  </script>
  <?php } ?>

  <h1>Dashboard</h1>
  
  <!-- Quick Actions -->
  <div class="quick-actions">
    <a href="facilities.php" class="action-card">
      <h3>📍 Facilities</h3>
      <p>View and book facilities</p>
    </a>
    <a href="consultation.php" class="action-card">
      <h3>💬 Consultations</h3>
      <p>Book consultation sessions</p>
    </a>
    <?php if ($u['role'] === 'lecturer'): ?>
      <a href="manage_slots.php" class="action-card">
        <h3>⚙️ Manage Slots</h3>
        <p>Manage consultation slots</p>
      </a>
    <?php endif; ?>
    <?php if ($u['role'] === 'admin'): ?>
      <a href="admin.php" class="action-card">
        <h3>🔧 Admin Panel</h3>
        <p>Manage users and bookings</p>
      </a>
    <?php endif; ?>
    <a href="notifications.php" class="action-card">
      <h3>🔔 Notifications</h3>
      <p>View notifications & history</p>
    </a>
  </div>

  <!-- Pending Bookings -->
  <?php if (!empty($pending_bookings)): ?>
  <div class="booking-section">
    <h2>Pending Bookings</h2>
    <?php foreach ($pending_bookings as $booking): ?>
    <div class="booking-item">
      <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div style="flex: 1;">
          <strong><?=htmlspecialchars($booking['purpose'])?></strong>
          <span class="badge status-pending">Pending</span>
          <div class="booking-meta">
            <?php if ($booking['facility_id']): ?>
              📍 <?=htmlspecialchars($booking['facility_name'])?> (<?=htmlspecialchars($booking['facility_code'])?>)
            <?php else: ?>
              💬 Consultation with <?=htmlspecialchars($booking['lecturer_name'])?>
            <?php endif; ?>
            <br>
            🕒 <?=format_booking_time($booking['start_dt'], $booking['end_dt'])?>
          </div>
          <div class="booking-actions">
            <a href="edit_booking.php?id=<?=intval($booking['id'])?>" class="btn">Edit</a>
            <a href="cancel_booking.php?id=<?=intval($booking['id'])?>" class="btn" style="background: #dc3545;" onclick="return confirm('Are you sure you want to cancel this booking?')">Cancel</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Approved Bookings -->
  <?php if (!empty($approved_bookings)): ?>
  <div class="booking-section">
    <h2>Approved Bookings</h2>
    <?php foreach ($approved_bookings as $booking): ?>
    <div class="booking-item">
      <div>
        <strong><?=htmlspecialchars($booking['purpose'])?></strong>
        <span class="badge status-approved">Approved</span>
        <div class="booking-meta">
          <?php if ($booking['facility_id']): ?>
            📍 <?=htmlspecialchars($booking['facility_name'])?> (<?=htmlspecialchars($booking['facility_code'])?>)
          <?php else: ?>
            💬 Consultation with <?=htmlspecialchars($booking['lecturer_name'])?>
          <?php endif; ?>
          <br>
          🕒 <?=format_booking_time($booking['start_dt'], $booking['end_dt'])?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Other Bookings (Rejected/Cancelled) -->
  <?php if (!empty($other_bookings)): ?>
  <div class="booking-section">
    <h2>Past Bookings</h2>
    <?php foreach (array_slice($other_bookings, 0, 5) as $booking): ?>
    <div class="booking-item">
      <div>
        <strong><?=htmlspecialchars($booking['purpose'])?></strong>
        <span class="badge status-<?=htmlspecialchars($booking['status'])?>"><?=ucfirst(htmlspecialchars($booking['status']))?></span>
        <div class="booking-meta">
          <?php if ($booking['facility_id']): ?>
            📍 <?=htmlspecialchars($booking['facility_name'])?> (<?=htmlspecialchars($booking['facility_code'])?>)
          <?php else: ?>
            💬 Consultation with <?=htmlspecialchars($booking['lecturer_name'])?>
          <?php endif; ?>
          <br>
          🕒 <?=format_booking_time($booking['start_dt'], $booking['end_dt'])?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Recent Notifications -->
  <?php if (!empty($notifications)): ?>
  <div class="card">
    <h2>Recent Notifications</h2>
    <?php foreach ($notifications as $notification): ?>
    <div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
      <div><?=htmlspecialchars($notification['message'])?></div>
      <div class="booking-meta"><?=date('M j, Y g:i A', strtotime($notification['created_at']))?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Empty State -->
  <?php if (empty($user_bookings)): ?>
  <div class="card" style="text-align: center; padding: 40px;">
    <h3>Welcome to CampusGrid!</h3>
    <p>You haven't made any bookings yet. Get started by booking a facility or consultation session.</p>
    <div style="margin-top: 20px;">
      <a href="facilities.php" class="btn">Browse Facilities</a>
      <a href="consultation.php" class="btn">Book Consultation</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Fallback to original grid if no bookings but want to show legacy dashboard -->
  <?php if (empty($user_bookings) && isset($_GET['legacy'])): ?>
  <div class="grid">
    <div class="card"><h2>Facilities</h2><p><a class="btn" href="facilities.php">Browse & Book</a></p></div>
    <?php if ($u['role']!=='admin') { ?><div class="card"><h2>Consultation</h2><p><a class="btn" href="consultation.php">See Slots</a></p></div><?php } ?>
    <?php if ($u['role']==='admin') { ?>
    <div class="card"><h2>Approve Requests</h2><p><a class="btn" href="admin.php">View</a></p></div>
    <?php } ?>
    <div class="card"><h2>Notifications &amp; History</h2><p><a class="btn" href="notifications.php">View</a></p></div>
  </div>
  <?php endif; ?>
</div>

<script src="assets/toast.js"></script>
<?php if ($success_msg): ?>
<script>showToast("<?=htmlspecialchars($success_msg, ENT_QUOTES)?>");</script>
<?php endif; ?>
</body>
</html>