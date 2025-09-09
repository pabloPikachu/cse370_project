<?php
require_once "config.php";
require_once "lib/functions.php";
require_login();

$u = $_SESSION['user'];

// Only lecturers can access this page
if ($u['role'] !== 'lecturer') {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Get lecturer ID
$st = $pdo->prepare("SELECT id FROM lecturers WHERE user_id = ?");
$st->execute([$u['id']]);
$lecturer = $st->fetch();
if (!$lecturer) {
    echo "<p>No lecturer profile found.</p>";
    exit;
}
$lecturer_id = intval($lecturer['id']);

$msg = "";
$err = "";
$edit_slot = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $day_of_week = trim($_POST['day_of_week'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 1);
        $slot_id = ($action === 'update') ? intval($_POST['slot_id']) : 0;

        // Validation
        if (!in_array($day_of_week, ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'])) {
            $err = "Invalid day of week.";
        } elseif (empty($start_time) || empty($end_time)) {
            $err = "Start and end times are required.";
        } elseif (strtotime("2025-01-01 $start_time") >= strtotime("2025-01-01 $end_time")) {
            $err = "Start time must be before end time.";
        } elseif ($capacity < 1 || $capacity > 50) {
            $err = "Capacity must be between 1 and 50.";
        } else {
            // Check for overlapping slots by the same lecturer
            $overlap_query = "SELECT COUNT(*) as count FROM consultation_slots 
                             WHERE lecturer_id = ? AND day_of_week = ? 
                             AND start_time < ? AND end_time > ?";
            $overlap_params = [$lecturer_id, $day_of_week, $end_time, $start_time];
            
            if ($action === 'update') {
                $overlap_query .= " AND id != ?";
                $overlap_params[] = $slot_id;
            }
            
            $st_check = $pdo->prepare($overlap_query);
            $st_check->execute($overlap_params);
            $overlap = $st_check->fetch()['count'];
            
            if ($overlap > 0) {
                $err = "This time slot overlaps with another of your consultation slots.";
            }
        }

        if (!$err) {
            if ($action === 'create') {
                $st = $pdo->prepare("INSERT INTO consultation_slots (lecturer_id, day_of_week, start_time, end_time, capacity) VALUES (?, ?, ?, ?, ?)");
                $st->execute([$lecturer_id, $day_of_week, $start_time, $end_time, $capacity]);
                $msg = "Consultation slot created successfully!";
            } else {
                // Check if slot belongs to this lecturer
                $st_check = $pdo->prepare("SELECT id FROM consultation_slots WHERE id = ? AND lecturer_id = ?");
                $st_check->execute([$slot_id, $lecturer_id]);
                if (!$st_check->fetch()) {
                    $err = "Slot not found or access denied.";
                } else {
                    $st = $pdo->prepare("UPDATE consultation_slots SET day_of_week = ?, start_time = ?, end_time = ?, capacity = ? WHERE id = ?");
                    $st->execute([$day_of_week, $start_time, $end_time, $capacity, $slot_id]);
                    $msg = "Consultation slot updated successfully!";
                }
            }
        }
    } elseif ($action === 'delete') {
        $slot_id = intval($_POST['slot_id']);
        
        // Check if slot belongs to this lecturer
        $st_check = $pdo->prepare("SELECT id FROM consultation_slots WHERE id = ? AND lecturer_id = ?");
        $st_check->execute([$slot_id, $lecturer_id]);
        if (!$st_check->fetch()) {
            $err = "Slot not found or access denied.";
        } else {
            // Check if there are any pending/approved bookings
            $st_bookings = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE slot_id = ? AND status IN ('pending','approved')");
            $st_bookings->execute([$slot_id]);
            $booking_count = $st_bookings->fetch()['count'];
            
            if ($booking_count > 0) {
                $err = "Cannot delete slot with existing bookings. Cancel all bookings first.";
            } else {
                $st = $pdo->prepare("DELETE FROM consultation_slots WHERE id = ?");
                $st->execute([$slot_id]);
                $msg = "Consultation slot deleted successfully!";
            }
        }
    }
}

// Handle edit mode
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $st = $pdo->prepare("SELECT * FROM consultation_slots WHERE id = ? AND lecturer_id = ?");
    $st->execute([$edit_id, $lecturer_id]);
    $edit_slot = $st->fetch(PDO::FETCH_ASSOC);
}

// Fetch current slots
$st = $pdo->prepare("SELECT * FROM consultation_slots WHERE lecturer_id = ? ORDER BY FIELD(day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), start_time");
$st->execute([$lecturer_id]);
$slots = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Manage Consultation Slots — CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css"/>
<style>
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media(max-width: 800px) { .two-col { grid-template-columns: 1fr; } }
  .small { opacity: .85; }
  .form-section { margin-bottom: 20px; }
</style>
</head>
<body>
<div class="header">
  <div class="brand">CampusGrid</div>
  <div>
    <?=htmlspecialchars($u['name'])." (".htmlspecialchars($u['role']).")";?> — 
    <a href="consultation.php">Consultation</a> | 
    <a href="dashboard.php">Dashboard</a>
  </div>
</div>
<div class="container">
  <?php
    if ($msg) echo "<div class='notice-success'>$msg</div>";
    if ($err) echo "<div class='notice-error'>$err</div>";
  ?>

  <div class="two-col">
    <!-- Create/Edit Form -->
    <div class="card">
      <h2><?= $edit_slot ? 'Edit' : 'Create' ?> Consultation Slot</h2>
      <form method="post" class="form-section">
        <input type="hidden" name="action" value="<?= $edit_slot ? 'update' : 'create' ?>"/>
        <?php if ($edit_slot) { ?>
          <input type="hidden" name="slot_id" value="<?=intval($edit_slot['id'])?>"/>
        <?php } ?>

        <label>Day of Week</label>
        <select name="day_of_week" class="input" required>
          <option value="">Select Day</option>
          <?php 
          $days = ['Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday','Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday'];
          foreach ($days as $val => $label) {
            $selected = ($edit_slot && $edit_slot['day_of_week'] === $val) ? 'selected' : '';
            echo "<option value='$val' $selected>$label</option>";
          }
          ?>
        </select>

        <label>Start Time</label>
        <input type="time" name="start_time" value="<?= $edit_slot ? htmlspecialchars($edit_slot['start_time']) : '' ?>" required/>

        <label>End Time</label>
        <input type="time" name="end_time" value="<?= $edit_slot ? htmlspecialchars($edit_slot['end_time']) : '' ?>" required/>

        <label>Capacity (Students per session)</label>
        <input type="number" name="capacity" min="1" max="50" value="<?= $edit_slot ? intval($edit_slot['capacity']) : 1 ?>" required/>

        <div style="margin-top: 16px;">
          <button type="submit" class="btn"><?= $edit_slot ? 'Update' : 'Create' ?> Slot</button>
          <?php if ($edit_slot) { ?>
            <a href="manage_slots.php" class="btn" style="background: #6c757d;">Cancel</a>
          <?php } ?>
        </div>
      </form>
    </div>

    <!-- Current Slots -->
    <div class="card">
      <h2>Your Consultation Slots</h2>
      <?php if (empty($slots)) { ?>
        <p class="small">No consultation slots created yet.</p>
      <?php } else { ?>
        <table class="table">
          <tr><th>Day</th><th>Time</th><th>Capacity</th><th>Actions</th></tr>
          <?php foreach ($slots as $slot) { 
            // Count current bookings for this slot
            $st_count = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE slot_id = ? AND status IN ('pending','approved')");
            $st_count->execute([intval($slot['id'])]);
            $booking_count = $st_count->fetch()['count'];
          ?>
            <tr>
              <td><?=htmlspecialchars($slot['day_of_week'])?></td>
              <td><?=htmlspecialchars($slot['start_time']).' - '.htmlspecialchars($slot['end_time'])?></td>
              <td><?=intval($slot['capacity'])?> <span class="small">(<?=$booking_count?> booked)</span></td>
              <td>
                <a href="manage_slots.php?edit=<?=intval($slot['id'])?>" class="btn" style="font-size: 12px;">Edit</a>
                <?php if ($booking_count == 0) { ?>
                  <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this slot?');">
                    <input type="hidden" name="action" value="delete"/>
                    <input type="hidden" name="slot_id" value="<?=intval($slot['id'])?>"/>
                    <button type="submit" class="btn" style="background: #dc3545; font-size: 12px;">Delete</button>
                  </form>
                <?php } else { ?>
                  <span class="small" style="color: #6c757d;">Has bookings</span>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
        </table>
      <?php } ?>
    </div>
  </div>

  <div class="card" style="margin-top: 20px;">
    <h3>Tips for Managing Consultation Slots</h3>
    <ul style="margin: 0; padding-left: 20px;">
      <li>You can create multiple slots for the same day with different times</li>
      <li>Slots with existing bookings cannot be deleted</li>
      <li>When editing a slot, existing bookings will be automatically adjusted if possible</li>
      <li>Students can see all available consultation times and book them directly</li>
    </ul>
  </div>
</div>

<script src="assets/toast.js"></script>
<?php if ($err): ?>
<script>showToast("<?=htmlspecialchars($err, ENT_QUOTES)?>");</script>
<?php endif; ?>
<?php if ($msg): ?>
<script>showToast("<?=htmlspecialchars($msg, ENT_QUOTES)?>");</script>
<?php endif; ?>
</body>
</html>