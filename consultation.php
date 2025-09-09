<?php
require_once "config.php"; 
require_once "lib/functions.php"; 
require_login();
$u = $_SESSION['user'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Consultation — CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css"/>
<style>
  .two-col { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media(max-width: 800px){ .two-col{ grid-template-columns: 1fr; } }
  .muted { opacity: 0.7; }
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
  .stat-card { background: #f8f9fa; padding: 12px; border-radius: 4px; text-align: center; }
  .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
  .manage-actions { margin-bottom: 16px; }
</style>
</head>
<body>
<div class="header">
  <div class="brand">CampusGrid</div>
  <div><?=htmlspecialchars($u['name'])." (".htmlspecialchars($u['role']).")";?> — <a href="dashboard.php">Dashboard</a></div>
</div>
<div class="container">
<?php if(isset($_GET['forbidden'])){ echo '<div class="notice-success" style="background:#fde2e2;color:#5f2120;border-color:#f5c2c7;"><strong>Notice:</strong> Only students can book consultation slots.</div>'; } ?>

<?php
if ($u['role'] === 'lecturer') {
  $st = $pdo->prepare("SELECT id FROM lecturers WHERE user_id = ?");
  $st->execute([$u['id']]);
  $lect = $st->fetch();
  if (!$lect) { echo "<p>No lecturer profile found.</p>"; exit; }
  $lect_id = intval($lect['id']);

  // Get lecturer stats
  $stats = get_lecturer_consultation_stats($pdo, $lect_id);

  $st = $pdo->prepare("SELECT id, day_of_week, start_time, end_time, capacity FROM consultation_slots WHERE lecturer_id = ? ORDER BY FIELD(day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), start_time");
  $st->execute([$lect_id]);
  $slots = $st->fetchAll(PDO::FETCH_ASSOC);

  $dow_map = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];
  $occ_available = []; $occ_booked = [];
  date_default_timezone_set('Asia/Dhaka');
  $now = new DateTime();

  foreach ($slots as $s) {
    $sid = intval($s['id']); $cap = intval($s['capacity']); $dow = $s['day_of_week'];
    $target = isset($dow_map[$dow]) ? $dow_map[$dow] : 1;
    $start_hm = substr($s['start_time'],0,5);
    $end_hm   = substr($s['end_time'],0,5);
    for ($i=0; $i<14; $i++) {
      $d = (clone $now)->modify("+$i day");
      if (intval($d->format('w')) === $target) {
        $date = $d->format('Y-m-d');
        $start_dt = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$start_hm);
        $end_dt   = DateTime::createFromFormat('Y-m-d H:i', $date.' '.$end_hm);
        if (!$start_dt || !$end_dt) continue;
        $start_s = $start_dt->format('Y-m-d H:i:s'); $end_s = $end_dt->format('Y-m-d H:i:s');
        $stc = $pdo->prepare("SELECT COUNT(*) AS c FROM bookings WHERE slot_id = ? AND status IN ('pending','approved') AND start_dt < ? AND end_dt > ?");
        $stc->execute([$sid, $end_s, $start_s]); $used = intval(($stc->fetch())['c']);
        if ($used < $cap) {
          $occ_available[] = ['date'=>$date, 'dow'=>$dow, 'time'=>$start_dt->format('H:i').'-'.$end_dt->format('H:i'), 'cap'=>$cap, 'used'=>$used];
        }
        $stb = $pdo->prepare("SELECT b.id, b.purpose, b.start_dt, b.end_dt, b.status, u.name AS student_name FROM bookings b JOIN users u ON b.booker_id = u.id WHERE b.slot_id = ? AND b.status IN ('pending','approved') AND b.start_dt < ? AND b.end_dt > ? ORDER BY b.start_dt DESC");
        $stb->execute([$sid, $end_s, $start_s]);
        foreach ($stb->fetchAll(PDO::FETCH_ASSOC) as $r) {
          $occ_booked[] = ['booking_id'=>$r['id'],'student'=>$r['student_name'],'purpose'=>$r['purpose'],'start_dt'=>$r['start_dt'],'end_dt'=>$r['end_dt'],'status'=>$r['status']];
        }
      }
    }
  }
  usort($occ_available, function($a,$b){ return strcmp($a['date'].$a['time'], $b['date'].$b['time']); });
  usort($occ_booked, function($a,$b){ return strcmp($b['start_dt'], $a['start_dt']); });
  ?>
  
  <!-- Lecturer Dashboard with Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-number"><?=$stats['total_slots']?></div>
      <div>Total Slots</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?=$stats['monthly_bookings']?></div>
      <div>This Month</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?=$stats['pending_bookings']?></div>
      <div>Pending</div>
    </div>
  </div>

  <div class="manage-actions">
    <a href="manage_slots.php" class="btn">Manage Consultation Slots</a>
  </div>
  
  <div class="two-col">
    <div class="card">
      <h2>Upcoming Occurrences (Next 14 days)</h2>
      <?php if (empty($occ_available)) { echo "<p class='muted'>No available capacity in the next 14 days.</p>"; } else { ?>
      <table class="table">
        <tr><th>Date</th><th>Day</th><th>Time</th><th>Cap</th><th>Booked</th><th>Remaining</th></tr>
        <?php foreach ($occ_available as $o) { ?>
          <tr><td><?=htmlspecialchars($o['date'])?></td><td><?=htmlspecialchars($o['dow'])?></td><td><?=htmlspecialchars($o['time'])?></td><td><?=intval($o['cap'])?></td><td><?=intval($o['used'])?></td><td><?=intval($o['cap'] - $o['used'])?></td></tr>
        <?php } ?>
      </table>
      <?php } ?>
    </div>
    <div class="card">
      <h2>Booked Occurrences (Pending/Approved)</h2>
      <?php if (empty($occ_booked)) { echo "<p class='muted'>No bookings yet.</p>"; } else { ?>
      <table class="table">
        <tr><th>Student</th><th>Purpose</th><th>Start</th><th>End</th>