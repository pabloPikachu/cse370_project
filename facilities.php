<?php
require_once "config.php"; require_once "lib/functions.php"; require_login();
$u = $_SESSION['user'];
$type = isset($_GET['type']) ? $_GET['type'] : "";
if ($type) {
  $st = $pdo->prepare("SELECT * FROM facilities WHERE type = ?");
  $st->execute([$type]);
  $rows = $st->fetchAll();
} else {
  $rows = $pdo->query("SELECT * FROM facilities")->fetchAll();
}
?>
<!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Facilities — CampusGrid</title><link rel="stylesheet" href="assets/styles.css"/></head>
<body>
<div class="header"><div class="brand">CampusGrid</div><a class="btn" href="dashboard.php">Back</a></div>
<div class="container">
  <div class="card">
    <h2>Facilities</h2>
    <form method="get" style="margin:10px 0">
      <label>Filter by type</label>
      <select name="type" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="classroom" <?php if($type==='classroom') echo 'selected'; ?>>Classroom</option>
        <option value="lab" <?php if($type==='lab') echo 'selected'; ?>>Lab</option>
        <option value="hall" <?php if($type==='hall') echo 'selected'; ?>>Hall</option>
        <option value="field" <?php if($type==='field') echo 'selected'; ?>>Field</option>
        <option value="auditorium" <?php if($type==='auditorium') echo 'selected'; ?>>Auditorium</option>
        <option value="multipurpose" <?php if($type==='multipurpose') echo 'selected'; ?>>Multipurpose</option>
      </select>
    </form>
    <table class="table">
      <tr><th>Name</th><th>Type</th><th>Capacity</th><th>Code</th><th>Action</th></tr>
      <?php foreach($rows as $r){ ?>
      <tr>
        <td><?=htmlspecialchars($r['name'])?></td>
        <td><span class="badge"><?=htmlspecialchars($r['type'])?></span></td>
        <td><?=htmlspecialchars($r['capacity'])?></td>
        <td><?=htmlspecialchars($r['code'])?></td>
        <td><a class="btn" href="make_booking.php?facility_id=<?=$r['id']?>">Book</a></td>
      </tr>
      <?php } ?>
    </table>
  </div>
</div>
</body></html>