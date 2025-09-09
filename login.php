<?php
require_once "config.php";
require_once "lib/functions.php";
$err = "";
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = $_POST['email']; $password = $_POST['password'];
  $u = find_user_by_email($pdo, $email);
  if ($u && password_ok($password, $u['password'])) {
    $_SESSION['user']=$u;
    header("Location: dashboard.php"); exit();
  } else { $err = "Invalid credentials"; }
}
?>
<!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Login — CampusGrid</title><link rel="stylesheet" href="assets/styles.css"/></head>
<body>
<div class="header"><div class="brand">CampusGrid</div></div>
<div class="container">
  <div class="card" style="max-width:420px;margin:auto">
    <h2>Login</h2>
    <?php if($err) echo "<p class='small'>$err</p>"; ?>
    <form method="post">
      <label>Email</label><input type="email" name="email" required/>
      <label>Password</label><input type="password" name="password" required/>
      <button type="submit" style="margin-top:12px">Login</button>
    </form>
    <p class="small" style="margin-top:8px">Demo: admin@campusgrid.edu / admin123 • lect1@bracu.ac.bd / lect123 • stud1@bracu.ac.bd / stud123</p>
  </div>
</div>
</body></html>