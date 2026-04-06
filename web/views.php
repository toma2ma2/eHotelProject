<?php
require __DIR__ . '/config.php';
require __DIR__ . '/header.php';

$a = $pdo->query("SELECT * FROM v_rooms_available_per_area ORDER BY area")->fetchAll();
$b = $pdo->query("SELECT * FROM v_hotel_room_capacity ORDER BY chain_name, hotel_id")->fetchAll();
?>
<h1>Reports</h1>
<p>From the database views.</p>
<h2>Free rooms by area</h2>
<table>
    <tr><th>area</th><th>available_rooms</th></tr>
    <?php foreach ($a as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['area']) ?></td>
            <td><?= htmlspecialchars($row['available_rooms']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Beds per hotel</h2>
<table>
    <tr><th>hotel</th><th>brand</th><th>area</th><th>beds</th><th>rooms</th></tr>
    <?php foreach ($b as $row): ?>
        <tr>
            <td><?= (int)$row['hotel_id'] ?></td>
            <td><?= htmlspecialchars($row['chain_name']) ?></td>
            <td><?= htmlspecialchars($row['area']) ?></td>
            <td><?= htmlspecialchars($row['total_capacity_units']) ?></td>
            <td><?= htmlspecialchars($row['room_count']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/footer.php'; ?>
