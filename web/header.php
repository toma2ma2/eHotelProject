<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$__role = $_SESSION['app_role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hotel booking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
    <a href="index.php">Home</a>
<?php if ($__role === 'customer'): ?>
    <a href="customer_home.php">Home</a>
    <a href="search.php">Search</a>
    <a href="my_bookings.php">Bookings</a>
    <a href="views.php">Reports</a>
<?php elseif ($__role === 'employee'): ?>
    <a href="employee.php">Front desk</a>
    <a href="manage.php">Edit data</a>
    <a href="views.php">Reports</a>
<?php else: ?>
    <a href="search.php">Search</a>
    <a href="views.php">Reports</a>
<?php endif; ?>
    <a href="index.php?clear=1" class="nav-switch">Switch</a>
</nav>
<hr>
