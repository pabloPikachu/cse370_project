<?php
require_once "config.php"; require_once "lib/functions.php"; require_login();
$u = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_id'])) {
  $bid = intval($_POST['cancel_id']);
  $st = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND booker_id = ?");
  $st->execute([$bid, $u['id']]);
  $b = $st->fetch(PDO::FETCH_ASSOC);
  if ($b && $b['status']==='pending') {
    add_history($pdo, $bid, $b['status'], 'cancelled');
    $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id = ?")->execute([$bid]);
    foreach (get_admins($pdo) as $a) { notify($pdo, $u['id'], $a['id'], "Booking #{$bid} was cancelled by requester.", $bid); }
    if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
    header("Location: my_bookings.php?cancel=1"); exit;
  } else {
    if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Not cancellable']); exit; }
  }
}

$sql = "SELECT b.*, f.name AS facility_name, u2.name AS lecturer_name, cs.day_of_week, cs.start_time AS cs_start, cs.end_time AS cs_end
        FROM bookings b
        LEFT JOIN facilities f ON b.facility_id = f.id
        LEFT JOIN consultation_slots cs ON b.slot_id = cs.id
        LEFT JOIN lecturers l ON cs.lecturer_id = l.id
        LEFT JOIN users u2 ON l.user_id = u2.id
        WHERE b.booker_id = ? ORDER BY b.start_dt DESC";
$st = $pdo->prepare($sql); $st->execute([$u['id']]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function render_table($rows){
  ob_start(); ?>
  <table class="table">
    <tr><th>ID</th><th>Type</th><th>Resource</th><th>Purpose</th><th>Start</th><th>End</th><th>Status</th><th>Action</th></tr>
    <?php foreach($rows as $b){
      $isFacility = !empty($b['facility_id']);
      $type = $isFacility ? 'Facility' : 'Consultation';
      $resource = $isFacility ? ($b['facility_name']?:'Facility')
                 : (($b['lecturer_name']?:'Lecturer')." (".$b['day_of_week']." ".$b['cs_start']."-".$b['cs_end'].")"); ?>
      <tr>
        <td>#<?=intval($b['id'])?></td>
        <td><?=htmlspecialchars($type)?></td>
        <td><?=htmlspecialchars($resource)?></td>
        <td><?=htmlspecialchars($b['purpose'])?></td>
        <td><?=htmlspecialchars($b['start_dt'])?></td>
        <td><?=htmlspecialchars($b['end_dt'])?></td>
        <td><span class="badge"><?=htmlspecialchars($b['status'])?></span></td>
        <td><?php if($b['status']==='pending'){ ?>
          <form method="post" class="cancel-form" style="display:inline">
            <input type="hidden" name="cancel_id" value="<?=intval($b['id'])?>"/>
            <button class="btn" type="submit" title="Cancel booking">Cancel</button>
          </form>
        <?php } else { ?><span class="muted">—</span><?php } ?></td>
      </tr>
    <?php } ?>
  </table>
  <?php return ob_get_clean();
}

if (isset($_GET['ajax'])) { echo render_table($rows); exit; }
?>
<!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>My Bookings — CampusGrid</title><link rel="stylesheet" href="assets/styles.css"/>
<style>.muted{opacity:.7}.filter{display:flex;gap:10px;margin-bottom:10px;align-items:center}</style>
</head><body>
<div class="header"><div class="brand">CampusGrid</div><div><?=htmlspecialchars($u['name'])." (".htmlspecialchars($u['role']).")";?> — <a href="dashboard.php">Dashboard</a></div></div>
<div class="container">
  <?php if(isset($_GET['cancel'])){ ?><div class="notice-success"><strong>Cancelled:</strong> Your booking was cancelled.</div><?php } ?>
  <div class="card">
    <h2>My Bookings</h2>
    <div class="filter">
      <label>Status</label>
      <select id="statusFilter" class="input"><option value="">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></select>
      <input id="searchBox" class="input" placeholder="Search resource/purpose"/>
    </div>
    <div id="bookingsTable"><?=render_table($rows)?></div>
  </div>
</div>
<script src="assets/toast.js"></script>
<script src="assets/my_bookings.js"></script>
</body></html>
