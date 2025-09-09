<?php
require_once "config.php"; require_login();
$u = $_SESSION['user'];
$st = $pdo->prepare("SELECT * FROM notifications WHERE receiver_id = ? ORDER BY created_at DESC");
$st->execute([$u['id']]);
$rows = $st->fetchAll();
?>
<!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Notifications &amp; History — CampusGrid</title><link rel="stylesheet" href="assets/styles.css"/></head>
<body>
<div class="header"><div class="brand">CampusGrid</div><a class="btn" href="dashboard.php">Back</a></div>
<div class="container">
  <div class="card">
    <h2>Your Notifications</h2>
    <table class="table">
      <tr><th>When</th><th>Message</th><th>Booking</th></tr>
      <?php foreach($rows as $n){ ?>
      <tr>
        <td><?=htmlspecialchars($n['created_at'])?></td>
        <td><?=htmlspecialchars($n['message'])?></td>
        <td><?=htmlspecialchars($n['booking_id'])?></td>
      </tr>
      <?php } ?>
    </table>
  </div>
</div>
</body></html>