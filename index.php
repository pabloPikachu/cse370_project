<?php
require_once "config.php";
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CampusGrid</title>
<link rel="stylesheet" href="assets/styles.css">
<style>
  body {
    margin: 0;
    padding: 0;
    background: #0d1b2a;
    color: #fff;
    font-family: 'Segoe UI', system-ui, -apple-system, Roboto, Arial, sans-serif;
  }
  .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    background: #0b1622;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  .brand {
    font-size: 1.5rem;
    font-weight: 700;
  }
  .btn {
    background: #2d6cdf;
    color: #fff;
    padding: 8px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: transform .05s ease;
  }
  .btn:active { transform: translateY(1px); }
  .hero {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 64px);
    text-align: center;
    padding: 24px;
  }
  .hero h1 {
    font-size: clamp(2.5rem, 6vw, 4.5rem);
    line-height: 1.1;
    letter-spacing: .5px;
    margin: 0 0 18px 0;
  }
  .hero p {
    font-size: clamp(1.25rem, 3vw, 2.25rem);
    opacity: .9;
    margin: 0;
  }
</style>
</head>
<body>
  <div class="header">
    <div class="brand">CampusGrid</div>
    <a class="btn" href="login.php">Login</a>
  </div>

  <div class="hero">
    <h1>WELCOME to CampusGrid!</h1>
    <p>Access your campus utilities; ONE FOR ALL!!</p>
  </div>
</body>
</html>
