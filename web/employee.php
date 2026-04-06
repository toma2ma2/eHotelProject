<?php
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_staff();
require __DIR__ . '/header.php';

$emps = $pdo->query("
    SELECT employee_id, first_name || ' ' || last_name AS nm, hotel_id
    FROM employee ORDER BY employee_id
")->fetchAll();

$allActiveBookings = $pdo->query("
    SELECT b.booking_id, b.hotel_id, h.chain_name, h.area, b.room_number,
           b.booking_date, b.start_date, b.end_date, b.status,
           c.customer_id, c.first_name, c.last_name
    FROM booking b
    JOIN hotel h ON h.hotel_id = b.hotel_id
    JOIN customer c ON c.customer_id = b.customer_id
    WHERE b.status = 'active' AND b.end_date >= CURRENT_DATE
    ORDER BY b.start_date ASC, h.chain_name
")->fetchAll();

$empId = (int)($_GET['employee_id'] ?? ($emps[0]['employee_id'] ?? 0));
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rent_from_booking') {
    $bid = (int)($_POST['booking_id'] ?? 0);
    $eid = (int)($_POST['employee_id'] ?? 0);
    try {
        $bk = $pdo->prepare("SELECT * FROM booking WHERE booking_id = :b AND status = 'active'");
        $bk->execute(['b' => $bid]);
        $b = $bk->fetch();
        if (!$b) {
            throw new RuntimeException('No active booking.');
        }
        $pdo->prepare("
            INSERT INTO renting (booking_id, customer_id, employee_id, hotel_id, room_number, start_date, end_date)
            VALUES (:bid, :cid, :eid, :hid, :rn, :sd, :ed)
        ")->execute([
            'bid' => $b['booking_id'],
            'cid' => $b['customer_id'],
            'eid' => $eid,
            'hid' => $b['hotel_id'],
            'rn' => $b['room_number'],
            'sd' => $b['start_date'],
            'ed' => $b['end_date'],
        ]);
        $msg = 'Checked in.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'walkin') {
    $eid = (int)($_POST['employee_id'] ?? 0);
    $cid = (int)($_POST['customer_id'] ?? 0);
    $hid = (int)($_POST['hotel_id'] ?? 0);
    $rn = trim($_POST['room_number'] ?? '');
    $sd = $_POST['start_date'] ?? '';
    $ed = $_POST['end_date'] ?? '';
    try {
        $pdo->prepare("
            INSERT INTO renting (booking_id, customer_id, employee_id, hotel_id, room_number, start_date, end_date)
            VALUES (NULL, :cid, :eid, :hid, :rn, :sd, :ed)
        ")->execute([
            'cid' => $cid,
            'eid' => $eid,
            'hid' => $hid,
            'rn' => $rn,
            'sd' => $sd,
            'ed' => $ed,
        ]);
        $msg = 'Saved.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $rid = (int)($_POST['renting_id'] ?? 0);
    $amt = (float)($_POST['amount'] ?? 0);
    try {
        $pdo->prepare("INSERT INTO customer_payment (renting_id, amount) VALUES (:r, :a)")
            ->execute(['r' => $rid, 'a' => $amt]);
        $msg = 'OK.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

$hotelForEmp = null;
foreach ($emps as $e) {
    if ((int)$e['employee_id'] === $empId) {
        $hotelForEmp = (int)$e['hotel_id'];
        break;
    }
}

$bookings = [];
if ($hotelForEmp) {
    $st = $pdo->prepare("
        SELECT b.booking_id, b.customer_id, b.room_number, b.start_date, b.end_date, c.first_name, c.last_name
        FROM booking b
        JOIN customer c ON c.customer_id = b.customer_id
        WHERE b.hotel_id = :h AND b.status = 'active'
        ORDER BY b.start_date
    ");
    $st->execute(['h' => $hotelForEmp]);
    $bookings = $st->fetchAll();
}

$rentings = $pdo->query("
    SELECT renting_id, hotel_id, room_number, start_date, end_date
    FROM renting ORDER BY renting_id DESC LIMIT 15
")->fetchAll();

$customers = $pdo->query("SELECT customer_id, first_name || ' ' || last_name AS nm FROM customer ORDER BY 1")->fetchAll();
$rooms = [];
if ($hotelForEmp) {
    $st = $pdo->prepare("SELECT room_number FROM room WHERE hotel_id = :h ORDER BY room_number");
    $st->execute(['h' => $hotelForEmp]);
    $rooms = $st->fetchAll(PDO::FETCH_COLUMN);
}
?>
<h1>Front desk</h1>
<p>Check people in, walk-ins, payments.</p>
<?php if ($msg): ?><p class="ok"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

<h2>Upcoming stays (all hotels)</h2>
<p>Bookings that are still active and not finished yet.</p>
<?php if (!$allActiveBookings): ?>
    <p>None.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Brand</th><th>Area</th><th>Hotel</th><th>Room</th>
            <th>Booked</th><th>Stay</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($allActiveBookings as $b): ?>
        <tr>
            <td><?= (int)$b['booking_id'] ?></td>
            <td><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?> (#<?= (int)$b['customer_id'] ?>)</td>
            <td><?= htmlspecialchars($b['chain_name']) ?></td>
            <td><?= htmlspecialchars($b['area']) ?></td>
            <td><?= (int)$b['hotel_id'] ?></td>
            <td><?= htmlspecialchars($b['room_number']) ?></td>
            <td><?= htmlspecialchars($b['booking_date']) ?></td>
            <td><?= htmlspecialchars($b['start_date']) ?> → <?= htmlspecialchars($b['end_date']) ?></td>
            <td><?= htmlspecialchars($b['status']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<form method="get" style="margin-bottom:1rem">
    <fieldset>
        <legend>Working as</legend>
        <label>Person
            <select name="employee_id" onchange="this.form.submit()">
                <?php foreach ($emps as $e): ?>
                    <option value="<?= (int)$e['employee_id'] ?>" <?= (int)$e['employee_id'] === $empId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nm']) ?> (hotel <?= (int)$e['hotel_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </fieldset>
</form>

<h2>Your hotel — bookings</h2>
<p>Check-in starts the stay (renting).</p>
<?php if (!$bookings): ?>
    <p>None.</p>
<?php else: ?>
<table>
    <tr><th>ID</th><th>Name</th><th>Room</th><th>Dates</th><th></th></tr>
    <?php foreach ($bookings as $b): ?>
        <tr>
            <td><?= (int)$b['booking_id'] ?></td>
            <td><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></td>
            <td><?= htmlspecialchars($b['room_number']) ?></td>
            <td><?= htmlspecialchars($b['start_date']) ?> → <?= htmlspecialchars($b['end_date']) ?></td>
            <td>
                <form method="post" style="margin:0">
                    <input type="hidden" name="action" value="rent_from_booking">
                    <input type="hidden" name="employee_id" value="<?= $empId ?>">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                    <button type="submit">Check in</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Walk-in (no booking)</h2>
<form method="post">
    <input type="hidden" name="action" value="walkin">
    <input type="hidden" name="employee_id" value="<?= $empId ?>">
    <fieldset>
        <legend>New stay</legend>
    <p><label>Customer
        <select name="customer_id">
            <?php foreach ($customers as $c): ?>
                <option value="<?= (int)$c['customer_id'] ?>"><?= htmlspecialchars($c['nm']) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Hotel # <input type="number" name="hotel_id" value="<?= $hotelForEmp ?: '' ?>" required></label></p>
    <p><label>Room
        <select name="room_number">
            <?php foreach ($rooms as $rn): ?>
                <option value="<?= htmlspecialchars($rn) ?>"><?= htmlspecialchars($rn) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Start <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label></p>
    <p><label>End <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required></label></p>
    <p><button type="submit">Save</button></p>
    </fieldset>
</form>

<h2>Payment</h2>
<form method="post">
    <input type="hidden" name="action" value="pay">
    <fieldset>
        <legend>Add payment</legend>
    <p><label>Renting #
        <select name="renting_id">
            <?php foreach ($rentings as $r): ?>
                <option value="<?= (int)$r['renting_id'] ?>"><?= (int)$r['renting_id'] ?> — room <?= htmlspecialchars($r['room_number']) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Amount <input type="number" step="0.01" name="amount" required></label></p>
    <p><button type="submit">Pay</button></p>
    </fieldset>
</form>
<?php require __DIR__ . '/footer.php'; ?>
