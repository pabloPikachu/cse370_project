<?php
require_once "config.php";
require_once "lib/functions.php";
require_login();

$u = $_SESSION['user'];
$facility_id = isset($_GET['facility_id']) ? intval($_GET['facility_id']) : null;
$slot_id     = isset($_GET['slot_id']) ? intval($_GET['slot_id']) : null;
$msg = "";
$err = "";

/* Guard: Only students can book consultation slots */
if ( ($slot_id || (!empty($_POST['slot_id']))) && $u['role'] !== 'student') {
    header("Location: consultation.php?forbidden=1");
    exit();
}

/* Handle submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose    = trim($_POST['purpose'] ?? '');
    $start_dt   = trim($_POST['start_dt'] ?? '');
    $end_dt     = trim($_POST['end_dt'] ?? '');
    $slot_id    = !empty($_POST['slot_id']) ? intval($_POST['slot_id']) : null;
    $facility_id= !empty($_POST['facility_id']) ? intval($_POST['facility_id']) : null;

    // Normalize to :ss
    if (strlen($start_dt) === 16) $start_dt .= ':00';
    if (strlen($end_dt)   === 16) $end_dt   .= ':00';

    if ($facility_id && overlaps($pdo, $facility_id, $start_dt, $end_dt)) {
        $err = "This facility is already booked for that time.";
    } elseif ($slot_id && !slot_has_capacity($pdo, $slot_id, $start_dt, $end_dt)) {
        $err = "That consultation occurrence is full.";
    } else {
        // Admin facility bookings are auto-approved; otherwise pending
        $status = ($facility_id && $u['role'] === 'admin') ? 'approved' : 'pending';

        $st = $pdo->prepare("INSERT INTO bookings (booker_id, purpose, start_dt, end_dt, facility_id, slot_id, status)
                             VALUES (?,?,?,?,?,?,?)");
        $st->execute([$u['id'], $purpose, $start_dt, $end_dt, $facility_id, $slot_id, $status]);
        $booking_id = $pdo->lastInsertId();

        if ($status === 'pending') {
            foreach (get_admins($pdo) as $a) {
                notify($pdo, $u['id'], $a['id'], "New booking #$booking_id pending approval", $booking_id);
            }
        } else {
            // Admin booked & auto-approved -> self notification
            notify($pdo, $u['id'], $u['id'], "Facility booking #$booking_id approved.", $booking_id);
        }

        // Pass the status to dashboard so it can show the appropriate message
        header("Location: dashboard.php?booked=1&status=" . $status);
        exit();
    }
}

/* Build available 1h facility slots (next 7 days, 09:00—18:00) */
$available_slots = [];
if ($facility_id && !$slot_id) {
    date_default_timezone_set('Asia/Dhaka');
    $now = new DateTime();
    for ($d = 0; $d < 7; $d++) {
        $date = (clone $now)->modify("+$d day")->format('Y-m-d');
        for ($h = 9; $h < 18; $h++) {
            $start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . sprintf('%02d:00', $h));
            $end   = (clone $start)->modify('+1 hour');
            $start_s = $start->format('Y-m-d H:i:s');
            $end_s   = $end->format('Y-m-d H:i:s');
            if (!overlaps($pdo, $facility_id, $start_s, $end_s)) {
                $available_slots[] = [
                    'label' => $start->format('Y-m-d H:i') . ' - ' . $end->format('H:i'),
                    'start' => $start->format('Y-m-d H:i'),
                    'end'   => $end->format('Y-m-d H:i'),
                ];
            }
        }
    }
}

/* Build consultation occurrences for the next 14 days (per time window) */
$consult_choices = [];
if ($slot_id && !$facility_id) {
    date_default_timezone_set('Asia/Dhaka');
    $st = $pdo->prepare("SELECT cs.*, u.name AS lecturer_name
                         FROM consultation_slots cs
                         JOIN lecturers l ON cs.lecturer_id = l.id
                         JOIN users u ON l.user_id = u.id
                         WHERE cs.id = ?");
    $st->execute([$slot_id]);
    $slot = $st->fetch(PDO::FETCH_ASSOC);
    if ($slot) {
        $dow        = $slot['day_of_week'];           // Mon..Sun
        $start_time = substr($slot['start_time'], 0, 5); // HH:MM
        $end_time   = substr($slot['end_time'],   0, 5);
        $map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
        $target = $map[$dow] ?? 1;

        $now = new DateTime();
        for ($i=0; $i<14; $i++) {
            $d = (clone $now)->modify("+$i day");
            if (intval($d->format('w')) === $target) {
                $date     = $d->format('Y-m-d');
                $start_dt2= DateTime::createFromFormat('Y-m-d H:i', $date.' '.$start_time);
                $end_dt2  = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$end_time);
                if (!$start_dt2 || !$end_dt2) continue;

                $start_s = $start_dt2->format('Y-m-d H:i:s');
                $end_s   = $end_dt2->format('Y-m-d H:i:s');
                if (slot_has_capacity($pdo, $slot_id, $start_s, $end_s)) {
                    $consult_choices[] = [
                        'label' => $date.' ('.$dow.') '.$start_dt2->format('H:i').' - '.$end_dt2->format('H:i'),
                        'start' => $start_dt2->format('Y-m-d H:i'),
                        'end'   => $end_dt2->format('Y-m-d H:i'),
                    ];
                }
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
<title>New Booking — CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css"/>
<style>
  .small{opacity:.85}
</style>
</head>
<body>
<div class="header"><div class="brand">CampusGrid</div><a class="btn" href="dashboard.php">Back</a></div>
<div class="container">
  <div class="card" style="max-width:640px;margin:auto">
    <h2>Create Booking</h2>
    <?php
      if ($msg) echo "<p class='small'>$msg</p>";
      if ($err) echo "<p class='small' style='color:#ff9090'>$err</p>";
    ?>
    <form method="post" onsubmit="return setTimesFromDropdown();">
      <input type="hidden" name="facility_id" value="<?=htmlspecialchars($facility_id ?? '')?>"/>
      <input type="hidden" name="slot_id" value="<?=htmlspecialchars($slot_id ?? '')?>"/>

      <label>Purpose</label>
      <input name="purpose" placeholder="Study session / Club meeting / Event" required/>

      <?php if ($facility_id && !$slot_id) { ?>
        <label>Choose Time Slot</label>
        <?= empty($available_slots) ? "<p class='small'>No available 1-hour slots in the next 7 days between 09:00—18:00.</p>" : "" ?>
        <?php if (!empty($available_slots)) { ?>
          <select id="slot_choice" class="input" required>
            <?php foreach($available_slots as $s){ ?>
              <option value="<?=htmlspecialchars($s['start'])?>|<?=htmlspecialchars($s['end'])?>">
                <?=htmlspecialchars($s['label'])?>
              </option>
            <?php } ?>
          </select>
        <?php } ?>
        <input type="hidden" name="start_dt" id="start_dt"/>
        <input type="hidden" name="end_dt" id="end_dt"/>

      <?php } elseif ($slot_id && !$facility_id) { ?>
        <label>Choose Consultation Date/Time</label>
        <?= empty($consult_choices) ? "<p class='small'>No available occurrences in the next 14 days.</p>" : "" ?>
        <?php if (!empty($consult_choices)) { ?>
          <select id="slot_choice" class="input" required>
            <?php foreach($consult_choices as $s){ ?>
              <option value="<?=htmlspecialchars($s['start'])?>|<?=htmlspecialchars($s['end'])?>">
                <?=htmlspecialchars($s['label'])?>
              </option>
            <?php } ?>
          </select>
          <input type="hidden" name="start_dt" id="start_dt"/>
          <input type="hidden" name="end_dt" id="end_dt"/>
        <?php } ?>

      <?php } else { ?>
        <label>Start (YYYY-MM-DD HH:MM)</label>
        <input name="start_dt" placeholder="2025-08-24 15:00" required/>
        <label>End (YYYY-MM-DD HH:MM)</label>
        <input name="end_dt" placeholder="2025-08-24 17:00" required/>
      <?php } ?>

      <button type="submit" style="margin-top:12px">Submit</button>
    </form>
    <p class="small">Overlap checks are enforced for facilities; consultation capacity is enforced per occurrence window.</p>
  </div>
</div>
<script>
function setTimesFromDropdown(){
  var sel = document.getElementById('slot_choice');
  if (!sel) return true; // manual mode
  var parts = sel.value.split('|');
  if (parts.length !== 2) return false;
  document.getElementById('start_dt').value = parts[0];
  document.getElementById('end_dt').value   = parts[1];
  return true;
}
</script>
<script src="assets/toast.js"></script>
<?php if ($err): ?>
<script>showToast("<?=htmlspecialchars($err, ENT_QUOTES)?>");</script>
<?php endif; ?>
</body>
</html>