<?php
require_once "config.php";
require_once "lib/functions.php";
require_login();

$u = $_SESSION['user'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = "";
$err = "";

// Fetch the booking
$st = $pdo->prepare("SELECT b.*, f.name AS facility_name, cs.day_of_week, cs.start_time, cs.end_time, 
                     u2.name AS lecturer_name
                     FROM bookings b 
                     LEFT JOIN facilities f ON b.facility_id = f.id
                     LEFT JOIN consultation_slots cs ON b.slot_id = cs.id
                     LEFT JOIN lecturers l ON cs.lecturer_id = l.id
                     LEFT JOIN users u2 ON l.user_id = u2.id
                     WHERE b.id = ?");
$st->execute([$booking_id]);
$booking = $st->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header("Location: dashboard.php?error=booking_not_found");
    exit();
}

// Check permissions: only the booker can edit their own pending bookings
if ($booking['booker_id'] !== $u['id'] || $booking['status'] !== 'pending') {
    header("Location: dashboard.php?error=no_permission");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose = trim($_POST['purpose'] ?? '');
    $start_dt = trim($_POST['start_dt'] ?? '');
    $end_dt = trim($_POST['end_dt'] ?? '');

    // Normalize to :ss
    if (strlen($start_dt) === 16) $start_dt .= ':00';
    if (strlen($end_dt) === 16) $end_dt .= ':00';

    // Validation
    if (empty($purpose)) {
        $err = "Purpose is required.";
    } elseif (empty($start_dt) || empty($end_dt)) {
        $err = "Start and end times are required.";
    } elseif (strtotime($start_dt) >= strtotime($end_dt)) {
        $err = "Start time must be before end time.";
    } elseif (strtotime($start_dt) < time()) {
        $err = "Start time must be in the future.";
    } else {
        // Check for conflicts (excluding current booking)
        if ($booking['facility_id']) {
            // For facility bookings, check overlaps
            $st_check = $pdo->prepare("SELECT COUNT(*) as count FROM bookings 
                                     WHERE facility_id = ? AND id != ? AND status IN ('pending','approved')
                                     AND start_dt < ? AND end_dt > ?");
            $st_check->execute([$booking['facility_id'], $booking_id, $end_dt, $start_dt]);
            $conflict = $st_check->fetch()['count'];
            
            if ($conflict > 0) {
                $err = "This facility is already booked for that time.";
            }
        } elseif ($booking['slot_id']) {
            // For consultation slots, check capacity
            if (!slot_has_capacity($pdo, $booking['slot_id'], $start_dt, $end_dt, $booking_id)) {
                $err = "That consultation occurrence is full.";
            }
        }
    }

    if (!$err) {
        // Update the booking
        $st = $pdo->prepare("UPDATE bookings SET purpose = ?, start_dt = ?, end_dt = ? WHERE id = ?");
        $st->execute([$purpose, $start_dt, $end_dt, $booking_id]);

        // Add to booking history
        $st = $pdo->prepare("INSERT INTO booking_history (booking_id, old_status, new_status) VALUES (?, 'pending', 'pending')");
        $st->execute([$booking_id]);

        // Notify admins of the change
        foreach (get_admins($pdo) as $admin) {
            notify($pdo, $u['id'], $admin['id'], "Booking #$booking_id has been updated and requires review", $booking_id);
        }

        $msg = "Booking updated successfully!";
        header("Location: dashboard.php?updated=1");
        exit();
    }
}

// Build available slots for consultation bookings
$consult_choices = [];
if ($booking['slot_id']) {
    date_default_timezone_set('Asia/Dhaka');
    $st = $pdo->prepare("SELECT cs.*, u.name AS lecturer_name
                         FROM consultation_slots cs
                         JOIN lecturers l ON cs.lecturer_id = l.id
                         JOIN users u ON l.user_id = u.id
                         WHERE cs.id = ?");
    $st->execute([$booking['slot_id']]);
    $slot = $st->fetch(PDO::FETCH_ASSOC);
    
    if ($slot) {
        $dow = $slot['day_of_week'];
        $start_time = substr($slot['start_time'], 0, 5);
        $end_time = substr($slot['end_time'], 0, 5);
        $map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
        $target = $map[$dow] ?? 1;

        $now = new DateTime();
        for ($i = 0; $i < 14; $i++) {
            $d = (clone $now)->modify("+$i day");
            if (intval($d->format('w')) === $target) {
                $date = $d->format('Y-m-d');
                $start_dt2 = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$start_time);
                $end_dt2 = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$end_time);
                if (!$start_dt2 || !$end_dt2) continue;

                $start_s = $start_dt2->format('Y-m-d H:i:s');
                $end_s = $end_dt2->format('Y-m-d H:i:s');
                
                // Check if this slot has capacity (excluding current booking)
                if (slot_has_capacity($pdo, $booking['slot_id'], $start_s, $end_s, $booking_id)) {
                    $selected = (substr($booking['start_dt'], 0, 16) === $start_dt2->format('Y-m-d H:i')) ? 'selected' : '';
                    $consult_choices[] = [
                        'label' => $date.' ('.$dow.') '.$start_dt2->format('H:i').' - '.$end_dt2->format('H:i'),
                        'start' => $start_dt2->format('Y-m-d H:i'),
                        'end' => $end_dt2->format('Y-m-d H:i'),
                        'selected' => $selected
                    ];
                }
            }
        }
    }
}

// For facility bookings, build available slots
$available_slots = [];
if ($booking['facility_id']) {
    date_default_timezone_set('Asia/Dhaka');
    $now = new DateTime();
    for ($d = 0; $d < 7; $d++) {
        $date = (clone $now)->modify("+$d day")->format('Y-m-d');
        for ($h = 9; $h < 18; $h++) {
            $start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . sprintf('%02d:00', $h));
            $end = (clone $start)->modify('+1 hour');
            $start_s = $start->format('Y-m-d H:i:s');
            $end_s = $end->format('Y-m-d H:i:s');
            
            // Check overlaps excluding current booking
            $st_check = $pdo->prepare("SELECT COUNT(*) as count FROM bookings 
                                     WHERE facility_id = ? AND id != ? AND status IN ('pending','approved')
                                     AND start_dt < ? AND end_dt > ?");
            $st_check->execute([$booking['facility_id'], $booking_id, $end_s, $start_s]);
            $overlap = $st_check->fetch()['count'];
            
            if ($overlap == 0) {
                $selected = (substr($booking['start_dt'], 0, 16) === $start->format('Y-m-d H:i')) ? 'selected' : '';
                $available_slots[] = [
                    'label' => $start->format('Y-m-d H:i') . ' - ' . $end->format('H:i'),
                    'start' => $start->format('Y-m-d H:i'),
                    'end' => $end->format('Y-m-d H:i'),
                    'selected' => $selected
                ];
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Edit Booking — CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css"/>
<style>
  .small { opacity: .85; }
  .booking-info { background: #f8f9fa; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
</style>
</head>
<body>
<div class="header">
  <div class="brand">CampusGrid</div>
  <a class="btn" href="dashboard.php">Back to Dashboard</a>
</div>
<div class="container">
  <div class="card" style="max-width:640px; margin:auto">
    <h2>Edit Booking #<?=intval($booking['id'])?></h2>
    
    <div class="booking-info">
      <strong>Current Booking:</strong><br>
      <?php if ($booking['facility_id']) { ?>
        <strong>Facility:</strong> <?=htmlspecialchars($booking['facility_name'])?><br>
      <?php } else { ?>
        <strong>Consultation with:</strong> <?=htmlspecialchars($booking['lecturer_name'])?><br>
      <?php } ?>
      <strong>Current Purpose:</strong> <?=htmlspecialchars($booking['purpose'])?><br>
      <strong>Current Time:</strong> <?=htmlspecialchars($booking['start_dt'])?> - <?=htmlspecialchars($booking['end_dt'])?><br>
      <strong>Status:</strong> <span class="badge"><?=htmlspecialchars($booking['status'])?></span>
    </div>

    <?php
      if ($msg) echo "<div class='notice-success'>$msg</div>";
      if ($err) echo "<div class='notice-error'>$err</div>";
    ?>

    <form method="post" onsubmit="return setTimesFromDropdown();">
      <label>Purpose</label>
      <input name="purpose" value="<?=htmlspecialchars($booking['purpose'])?>" placeholder="Study session / Club meeting / Event" required/>

      <?php if ($booking['facility_id'] && !empty($available_slots)) { ?>
        <label>Choose New Time Slot</label>
        <select id="slot_choice" class="input" required>
          <?php foreach($available_slots as $s){ ?>
            <option value="<?=htmlspecialchars($s['start'])?>|<?=htmlspecialchars($s['end'])?>" <?=$s['selected']?>>
              <?=htmlspecialchars($s['label'])?>
            </option>
          <?php } ?>
        </select>
        <input type="hidden" name="start_dt" id="start_dt"/>
        <input type="hidden" name="end_dt" id="end_dt"/>

      <?php } elseif ($booking['slot_id'] && !empty($consult_choices)) { ?>
        <label>Choose New Consultation Date/Time</label>
        <select id="slot_choice" class="input" required>
          <?php foreach($consult_choices as $s){ ?>
            <option value="<?=htmlspecialchars($s['start'])?>|<?=htmlspecialchars($s['end'])?>" <?=$s['selected']?>>
              <?=htmlspecialchars($s['label'])?>
            </option>
          <?php } ?>
        </select>
        <input type="hidden" name="start_dt" id="start_dt"/>
        <input type="hidden" name="end_dt" id="end_dt"/>

      <?php } else { ?>
        <label>Start (YYYY-MM-DD HH:MM)</label>
        <input name="start_dt" value="<?=substr($booking['start_dt'], 0, 16)?>" placeholder="2025-08-24 15:00" required/>
        <label>End (YYYY-MM-DD HH:MM)</label>
        <input name="end_dt" value="<?=substr($booking['end_dt'], 0, 16)?>" placeholder="2025-08-24 17:00" required/>
      <?php } ?>

      <div style="margin-top: 16px;">
        <button type="submit" class="btn">Update Booking</button>
        <a href="dashboard.php" class="btn" style="background: #6c757d;">Cancel</a>
      </div>
    </form>

    <?php if (($booking['facility_id'] && empty($available_slots)) || ($booking['slot_id'] && empty($consult_choices))) { ?>
      <p class="small" style="color: #ff9090;">No alternative time slots are currently available. You can only edit the purpose.</p>
    <?php } ?>
  </div>
</div>

<script>
function setTimesFromDropdown(){
  var sel = document.getElementById('slot_choice');
  if (!sel) return true; // manual mode
  var parts = sel.value.split('|');
  if (parts.length !== 2) return false;
  document.getElementById('start_dt').value = parts[0];
  document.getElementById('end_dt').value = parts[1];
  return true;
}

// Set initial values on page load
document.addEventListener('DOMContentLoaded', function() {
  setTimesFromDropdown();
});
</script>
<script src="assets/toast.js"></script>
<?php if ($err): ?>
<script>showToast("<?=htmlspecialchars($err, ENT_QUOTES)?>");</script>
<?php endif; ?>
</body>
</html>