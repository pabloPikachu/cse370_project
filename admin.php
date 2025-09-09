<?php
require_once "config.php";
require_once "lib/functions.php";
require_login();

$u = $_SESSION['user'];
if ($u['role'] !== 'admin') { echo "Admins only."; exit; }

// Render pending bookings list (facilities + consultations)
function render_admin_list(PDO $pdo){
  $sql = "
    SELECT 
      b.*,
      req.name AS requester_name,
      f.name  AS facility_name,
      cs.day_of_week, cs.start_time, cs.end_time,
      ulect.name AS lecturer_name
    FROM bookings b
    JOIN users req ON b.booker_id = req.id
    LEFT JOIN facilities f ON b.facility_id = f.id
    LEFT JOIN consultation_slots cs ON b.slot_id = cs.id
    LEFT JOIN lecturers l ON cs.lecturer_id = l.id
    LEFT JOIN users ulect ON l.user_id = ulect.id
    WHERE b.status = 'pending'
    ORDER BY b.start_dt ASC
  ";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  ob_start(); ?>
  <div class="card">
    <h2>Pending Approval Requests</h2>
    <?php if (empty($rows)) { ?>
      <p class="muted">No pending requests.</p>
    <?php } else { ?>
    <table class="table">
      <tr>
        <th>ID</th>
        <th>Requester</th>
        <th>Type</th>
        <th>Resource</th>
        <th>From</th>
        <th>To</th>
        <th>Actions</th>
      </tr>
      <?php foreach($rows as $b){
        $isFacility = !empty($b['facility_id']);
        $type = $isFacility ? 'Facility' : 'Consultation';
        if ($isFacility) {
          $resource = $b['facility_name'] ?: 'Facility';
        } else {
          // Lecturer Name + (DOW HH:MM-HH:MM)
          $dow = $b['day_of_week'] ?: '';
          $st  = $b['start_time'] ? substr($b['start_time'], 0, 5) : '';
          $et  = $b['end_time']   ? substr($b['end_time'],   0, 5) : '';
          $lec = $b['lecturer_name'] ?: 'Lecturer';
          $tim = trim($dow . ' ' . $st . '-' . $et);
          $resource = trim($lec . ($tim ? " ($tim)" : ''));
        }
        ?>
        <tr>
          <td>#<?=intval($b['id'])?></td>
          <td><?=htmlspecialchars($b['requester_name'])?></td>
          <td><?=htmlspecialchars($type)?></td>
          <td><?=htmlspecialchars($resource)?></td>
          <td><?=htmlspecialchars($b['start_dt'])?></td>
          <td><?=htmlspecialchars($b['end_dt'])?></td>
          <td>
            <form method="post" class="admin-action" style="display:inline">
              <input type="hidden" name="booking_id" value="<?=intval($b['id'])?>"/>
              <button class="btn" name="action" value="approve" type="submit">Approve</button>
            </form>
            <form method="post" class="admin-action" style="display:inline">
              <input type="hidden" name="booking_id" value="<?=intval($b['id'])?>"/>
              <button class="btn" name="action" value="reject" type="submit" style="background:#ff7681">Reject</button>
            </form>
          </td>
        </tr>
      <?php } ?>
    </table>
    <?php } ?>
  </div>
  <?php
  return ob_get_clean();
}

// Handle approve/reject (AJAX or normal POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $booking_id = intval($_POST['booking_id'] ?? 0);
  $action     = $_POST['action'] ?? '';
  $st = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
  $st->execute([$booking_id]);
  $b = $st->fetch(PDO::FETCH_ASSOC);

  if ($b && in_array($action, ['approve','reject'], true)) {
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    add_history($pdo, $booking_id, $b['status'], $new_status);
    $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$new_status, $booking_id]);
    // Notify requester
    notify($pdo, $u['id'], $b['booker_id'], "Your booking #{$booking_id} has been {$new_status}.", $booking_id);

    if (isset($_POST['ajax'])) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>true,'status'=>$new_status,'id'=>$booking_id]);
      exit;
    }
    header("Location: admin.php?done=1");
    exit;
  } else {
    if (isset($_POST['ajax'])) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>'Booking not found']);
      exit;
    }
    header("Location: admin.php?error=notfound");
    exit;
  }
}

// AJAX list refresh
if (isset($_GET['ajax'])) {
  echo render_admin_list($pdo);
  exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Approve Requests — CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css"/>
<style>.muted{opacity:.75}</style>
</head>
<body>
<div class="header">
  <div class="brand">CampusGrid</div>
  <div><?=htmlspecialchars($u['name'])." (Admin)";?> — <a href="dashboard.php">Dashboard</a></div>
</div>

<div class="container">
  <div id="adminList">
    <?=render_admin_list($pdo)?>
  </div>
</div>

<script src="assets/toast.js"></script>
<script>
// Robust AJAX approve/reject + polling (ensures `action` is sent)
(function(){
  function reloadList(){
    fetch('admin.php?ajax=1', {cache:'no-store'})
      .then(r=>r.text())
      .then(html=>{
        var el = document.getElementById('adminList');
        if(el) el.innerHTML = html;
      }).catch(()=>{});
  }
  setInterval(reloadList, 5000);

  // Fallback tracker in case e.submitter is not supported
  var lastActionValue = null;
  document.addEventListener('click', function(ev){
    var btn = ev.target.closest('button[type="submit"][name="action"]');
    if(btn){ lastActionValue = btn.value; }
  }, true);

  document.addEventListener('submit', function(e){
    var form = e.target;
    if(!(form.classList && form.classList.contains('admin-action'))) return;
    e.preventDefault();

    var fd = new FormData(form);

    // Ensure `action` is included (approve/reject)
    if (e.submitter && e.submitter.name === 'action') {
      fd.append('action', e.submitter.value);
    } else if (!fd.has('action') && lastActionValue) {
      fd.append('action', lastActionValue);
      lastActionValue = null;
    }

    fd.append('ajax','1');

    fetch('admin.php', {method:'POST', body: fd})
      .then(r=>r.json())
      .then(j=>{
        if(j && j.ok){
          // Determine message based on user role
          <?php if ($u['role'] === 'admin'): ?>
            showToast('Success');
          <?php else: ?>
            showToast('Booking #' + j.id + ' ' + j.status + '.');
          <?php endif; ?>
          reloadList();
        }else{
          showToast('Action failed.');
        }
      })
      .catch(()=>showToast('Network error.'));
  });

  reloadList();
})();
</script>
</body>
</html>
