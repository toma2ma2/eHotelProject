<?php
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_customer();
require __DIR__ . '/header.php';

$st = $pdo->query("
    SELECT b.booking_id, b.booking_date, b.start_date, b.end_date, b.status,
           b.hotel_id, b.room_number, h.chain_name, h.area,
           c.first_name, c.last_name
    FROM booking b
    JOIN hotel h ON h.hotel_id = b.hotel_id
    JOIN customer c ON c.customer_id = b.customer_id
    ORDER BY b.start_date DESC, b.booking_id DESC
");
$rows = $st->fetchAll();
?>
<h1>Bookings</h1>
<p>Every booking, newest first.</p>

<?php if (!$rows): ?>
    <p>No bookings on file.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Booking #</th>
            <th>Status</th>
            <th>Brand</th>
            <th>Area</th>
            <th>Hotel</th>
            <th>Room</th>
            <th>Booked on</th>
            <th>Stay</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name'])) ?></td>
            <td><?= (int)$r['booking_id'] ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td><?= htmlspecialchars($r['chain_name']) ?></td>
            <td><?= htmlspecialchars($r['area']) ?></td>
            <td><?= (int)$r['hotel_id'] ?></td>
            <td><?= htmlspecialchars($r['room_number']) ?></td>
            <td><?= htmlspecialchars($r['booking_date']) ?></td>
            <td><?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
