<?php
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_customer();
require __DIR__ . '/header.php';
?>
<h1>Home</h1>
<p>Search rooms, see bookings, open reports.</p>
<ul>
    <li><a href="search.php">Search rooms</a></li>
    <li><a href="my_bookings.php">All bookings</a></li>
    <li><a href="views.php">Reports</a></li>
</ul>
<?php require __DIR__ . '/footer.php'; ?>
