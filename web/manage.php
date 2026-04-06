<?php
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_staff();
require __DIR__ . '/header.php';

$tab = $_POST['t'] ?? $_GET['t'] ?? 'customer';

$editCustomer = ($tab === 'customer' && isset($_GET['edit_customer'])) ? (int)$_GET['edit_customer'] : 0;
$editHotel = ($tab === 'hotel' && isset($_GET['edit_hotel'])) ? (int)$_GET['edit_hotel'] : 0;
$editRoomH = ($tab === 'room' && isset($_GET['edit_room_h'])) ? (int)$_GET['edit_room_h'] : 0;
$editRoomN = ($tab === 'room' && isset($_GET['edit_room_n'])) ? trim((string)$_GET['edit_room_n']) : '';
$editEmployee = ($tab === 'employee' && isset($_GET['edit_employee'])) ? (int)$_GET['edit_employee'] : 0;

$syncChainHotelCount = static function (PDO $pdo, string $chainName): void {
    $pdo->prepare('
        UPDATE hotel_chain SET number_hotels = (SELECT COUNT(*)::integer FROM hotel h WHERE h.chain_name = :c)
        WHERE chain_name = :c
    ')->execute(['c' => $chainName]);
};

$note = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'add_customer') {
            $pdo->prepare("
                INSERT INTO customer (first_name, last_name, street_address, city, ID_type, ID_number)
                VALUES (:fn, :ln, :st, :ci, :it, :in)
            ")->execute([
                'fn' => trim($_POST['first_name'] ?? ''),
                'ln' => trim($_POST['last_name'] ?? ''),
                'st' => trim($_POST['street_address'] ?? ''),
                'ci' => trim($_POST['city'] ?? ''),
                'it' => trim($_POST['ID_type'] ?? ''),
                'in' => trim($_POST['ID_number'] ?? ''),
            ]);
            $note = 'Customer added.';
        }
        if ($act === 'del_customer') {
            $cid = (int)$_POST['id'];
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO renting_archive (old_renting_id, chain_name, hotel_id, room_number, first_name, last_name, booking_id, start_date, end_date)
                    SELECT r.renting_id, h.chain_name, r.hotel_id, r.room_number, c.first_name, c.last_name, r.booking_id, r.start_date, r.end_date
                    FROM renting r
                    JOIN customer c ON c.customer_id = r.customer_id
                    JOIN hotel h ON h.hotel_id = r.hotel_id
                    WHERE r.customer_id = :id
                ")->execute(['id' => $cid]);
                $pdo->prepare("DELETE FROM renting WHERE customer_id = :id")->execute(['id' => $cid]);
                $pdo->prepare("
                    INSERT INTO booking_archive (old_booking_id, chain_name, hotel_id, room_number, first_name, last_name, booking_date, start_date, end_date)
                    SELECT b.booking_id, h.chain_name, b.hotel_id, b.room_number, c.first_name, c.last_name, b.booking_date, b.start_date, b.end_date
                    FROM booking b
                    JOIN customer c ON c.customer_id = b.customer_id
                    JOIN hotel h ON h.hotel_id = b.hotel_id
                    WHERE b.customer_id = :id
                ")->execute(['id' => $cid]);
                $pdo->prepare("DELETE FROM booking WHERE customer_id = :id")->execute(['id' => $cid]);
                $pdo->prepare("DELETE FROM customer WHERE customer_id = :id")->execute(['id' => $cid]);
                $pdo->commit();
                $note = 'Customer removed (bookings/rentings archived).';
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        if ($act === 'add_hotel') {
            $cn = $_POST['chain_name'] ?? '';
            $pdo->prepare("
                INSERT INTO hotel (chain_name, hotel_rating, number_rooms, street_number, street_name, area, contact_email)
                VALUES (:cn, :hr, 0, :sn, :sname, :ar, :em)
            ")->execute([
                'cn' => $cn,
                'hr' => (int)($_POST['hotel_rating'] ?? 3),
                'sn' => trim($_POST['street_number'] ?? ''),
                'sname' => trim($_POST['street_name'] ?? ''),
                'ar' => trim($_POST['area'] ?? ''),
                'em' => trim($_POST['contact_email'] ?? ''),
            ]);
            $syncChainHotelCount($pdo, $cn);
            $note = 'Hotel added.';
        }
        if ($act === 'del_hotel') {
            $hid = (int)$_POST['id'];
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('SELECT chain_name FROM hotel WHERE hotel_id = :id');
                $st->execute(['id' => $hid]);
                $chainName = $st->fetchColumn();
                if (!$chainName) {
                    throw new RuntimeException('Hotel not found.');
                }
                $pdo->prepare("
                    INSERT INTO renting_archive (old_renting_id, chain_name, hotel_id, room_number, first_name, last_name, booking_id, start_date, end_date)
                    SELECT r.renting_id, h.chain_name, r.hotel_id, r.room_number, c.first_name, c.last_name, r.booking_id, r.start_date, r.end_date
                    FROM renting r
                    JOIN customer c ON c.customer_id = r.customer_id
                    JOIN hotel h ON h.hotel_id = r.hotel_id
                    WHERE r.hotel_id = :hid
                ")->execute(['hid' => $hid]);
                $pdo->prepare('DELETE FROM renting WHERE hotel_id = :hid')->execute(['hid' => $hid]);
                $pdo->prepare("
                    INSERT INTO booking_archive (old_booking_id, chain_name, hotel_id, room_number, first_name, last_name, booking_date, start_date, end_date)
                    SELECT b.booking_id, h.chain_name, b.hotel_id, b.room_number, c.first_name, c.last_name, b.booking_date, b.start_date, b.end_date
                    FROM booking b
                    JOIN customer c ON c.customer_id = b.customer_id
                    JOIN hotel h ON h.hotel_id = b.hotel_id
                    WHERE b.hotel_id = :hid
                ")->execute(['hid' => $hid]);
                $pdo->prepare('DELETE FROM booking WHERE hotel_id = :hid')->execute(['hid' => $hid]);
                $pdo->prepare('DELETE FROM hotel WHERE hotel_id = :hid')->execute(['hid' => $hid]);
                $syncChainHotelCount($pdo, (string)$chainName);
                $pdo->commit();
                $note = 'Hotel removed (bookings/rentings archived).';
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        if ($act === 'del_chain') {
            $cn = trim($_POST['chain_name'] ?? '');
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO renting_archive (old_renting_id, chain_name, hotel_id, room_number, first_name, last_name, booking_id, start_date, end_date)
                    SELECT r.renting_id, h.chain_name, r.hotel_id, r.room_number, c.first_name, c.last_name, r.booking_id, r.start_date, r.end_date
                    FROM renting r
                    JOIN customer c ON c.customer_id = r.customer_id
                    JOIN hotel h ON h.hotel_id = r.hotel_id
                    WHERE h.chain_name = :cn
                ")->execute(['cn' => $cn]);
                $pdo->prepare('DELETE FROM renting WHERE hotel_id IN (SELECT hotel_id FROM hotel WHERE chain_name = :cn)')->execute(['cn' => $cn]);
                $pdo->prepare("
                    INSERT INTO booking_archive (old_booking_id, chain_name, hotel_id, room_number, first_name, last_name, booking_date, start_date, end_date)
                    SELECT b.booking_id, h.chain_name, b.hotel_id, b.room_number, c.first_name, c.last_name, b.booking_date, b.start_date, b.end_date
                    FROM booking b
                    JOIN customer c ON c.customer_id = b.customer_id
                    JOIN hotel h ON h.hotel_id = b.hotel_id
                    WHERE h.chain_name = :cn
                ")->execute(['cn' => $cn]);
                $pdo->prepare('DELETE FROM booking WHERE hotel_id IN (SELECT hotel_id FROM hotel WHERE chain_name = :cn)')->execute(['cn' => $cn]);
                $pdo->prepare('DELETE FROM hotel WHERE chain_name = :cn')->execute(['cn' => $cn]);
                $pdo->prepare('DELETE FROM hotel_chain WHERE chain_name = :cn')->execute(['cn' => $cn]);
                $pdo->commit();
                $note = 'Brand deleted.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        if ($act === 'add_room') {
            $pdo->prepare("
                INSERT INTO room (hotel_id, room_number, price, TV, air_condition, fridge, room_capacity, view_type, extendable, has_problems)
                VALUES (:hid, :rn, :pr, :tv, :ac, :fr, :cap, :vt, :ex, :hp)
            ")->execute([
                'hid' => (int)$_POST['hotel_id'],
                'rn' => trim($_POST['room_number'] ?? ''),
                'pr' => (float)$_POST['price'],
                'tv' => isset($_POST['TV']),
                'ac' => isset($_POST['air_condition']),
                'fr' => isset($_POST['fridge']),
                'cap' => $_POST['room_capacity'] ?? 'double',
                'vt' => $_POST['view_type'] ?? 'city',
                'ex' => isset($_POST['extendable']),
                'hp' => isset($_POST['has_problems']),
            ]);
            $hid = (int)$_POST['hotel_id'];
            $pdo->prepare("UPDATE hotel SET number_rooms = (SELECT COUNT(*)::integer FROM room WHERE hotel_id = :h) WHERE hotel_id = :h")
                ->execute(['h' => $hid]);
            $note = 'Room added.';
        }
        if ($act === 'del_room') {
            $hid = (int)$_POST['hotel_id'];
            $rn = trim($_POST['room_number'] ?? '');
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('SELECT chain_name FROM hotel WHERE hotel_id = :id');
                $st->execute(['id' => $hid]);
                $chainName = $st->fetchColumn();
                if (!$chainName) {
                    throw new RuntimeException('Hotel not found.');
                }
                $pdo->prepare("
                    INSERT INTO renting_archive (old_renting_id, chain_name, hotel_id, room_number, first_name, last_name, booking_id, start_date, end_date)
                    SELECT r.renting_id, h.chain_name, r.hotel_id, r.room_number, c.first_name, c.last_name, r.booking_id, r.start_date, r.end_date
                    FROM renting r
                    JOIN customer c ON c.customer_id = r.customer_id
                    JOIN hotel h ON h.hotel_id = r.hotel_id
                    WHERE r.hotel_id = :hid AND r.room_number = :rn
                ")->execute(['hid' => $hid, 'rn' => $rn]);
                $pdo->prepare('DELETE FROM renting WHERE hotel_id = :hid AND room_number = :rn')->execute(['hid' => $hid, 'rn' => $rn]);
                $pdo->prepare("
                    INSERT INTO booking_archive (old_booking_id, chain_name, hotel_id, room_number, first_name, last_name, booking_date, start_date, end_date)
                    SELECT b.booking_id, h.chain_name, b.hotel_id, b.room_number, c.first_name, c.last_name, b.booking_date, b.start_date, b.end_date
                    FROM booking b
                    JOIN customer c ON c.customer_id = b.customer_id
                    JOIN hotel h ON h.hotel_id = b.hotel_id
                    WHERE b.hotel_id = :hid AND b.room_number = :rn
                ")->execute(['hid' => $hid, 'rn' => $rn]);
                $pdo->prepare('DELETE FROM booking WHERE hotel_id = :hid AND room_number = :rn')->execute(['hid' => $hid, 'rn' => $rn]);
                $pdo->prepare('DELETE FROM room WHERE hotel_id = :hid AND room_number = :rn')->execute(['hid' => $hid, 'rn' => $rn]);
                $pdo->prepare("UPDATE hotel SET number_rooms = (SELECT COUNT(*)::integer FROM room WHERE hotel_id = :h) WHERE hotel_id = :h")
                    ->execute(['h' => $hid]);
                $pdo->commit();
                $note = 'Room removed (bookings/rentings archived).';
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        if ($act === 'add_employee') {
            $st = $pdo->prepare("
                INSERT INTO employee (hotel_ID, first_name, last_name, address, ssn_sin, is_manager)
                VALUES (:hid, :fn, :ln, :ad, :ss, :mgr)
                RETURNING employee_id
            ");
            $st->execute([
                'hid' => (int)$_POST['hotel_id'],
                'fn' => trim($_POST['first_name'] ?? ''),
                'ln' => trim($_POST['last_name'] ?? ''),
                'ad' => trim($_POST['address'] ?? ''),
                'ss' => trim($_POST['ssn_sin'] ?? ''),
                'mgr' => isset($_POST['is_manager']),
            ]);
            $eid = (int)$st->fetchColumn();
            if (trim($_POST['role_name'] ?? '') !== '') {
                $pdo->prepare("INSERT INTO employee_role (employee_ID, role_name) VALUES (:e, :rn)")
                    ->execute(['e' => $eid, 'rn' => trim($_POST['role_name'])]);
            }
            $note = 'Staff added.';
        }
        if ($act === 'del_employee') {
            $pdo->prepare("DELETE FROM employee WHERE employee_id = :id")->execute(['id' => (int)$_POST['id']]);
            $note = 'Removed.';
        }
        if ($act === 'update_customer') {
            $pdo->prepare("
                UPDATE customer
                SET first_name = :fn, last_name = :ln, street_address = :st, city = :ci,
                    id_type = :it, id_number = :in
                WHERE customer_id = :id
            ")->execute([
                'fn' => trim($_POST['first_name'] ?? ''),
                'ln' => trim($_POST['last_name'] ?? ''),
                'st' => trim($_POST['street_address'] ?? ''),
                'ci' => trim($_POST['city'] ?? ''),
                'it' => trim($_POST['ID_type'] ?? ''),
                'in' => trim($_POST['ID_number'] ?? ''),
                'id' => (int)$_POST['id'],
            ]);
            $note = 'Customer updated.';
        }
        if ($act === 'update_hotel') {
            $hid = (int)$_POST['id'];
            $st = $pdo->prepare('SELECT chain_name FROM hotel WHERE hotel_id = :id');
            $st->execute(['id' => $hid]);
            $oldChain = $st->fetchColumn();
            $newChain = $_POST['chain_name'] ?? '';
            $pdo->prepare("
                UPDATE hotel SET chain_name = :cn, hotel_rating = :hr, street_number = :sn,
                    street_name = :sname, area = :ar, contact_email = :em
                WHERE hotel_id = :id
            ")->execute([
                'cn' => $newChain,
                'hr' => (int)($_POST['hotel_rating'] ?? 3),
                'sn' => trim($_POST['street_number'] ?? ''),
                'sname' => trim($_POST['street_name'] ?? ''),
                'ar' => trim($_POST['area'] ?? ''),
                'em' => trim($_POST['contact_email'] ?? ''),
                'id' => $hid,
            ]);
            if ($oldChain && $newChain && (string)$oldChain !== (string)$newChain) {
                $syncChainHotelCount($pdo, (string)$oldChain);
                $syncChainHotelCount($pdo, $newChain);
            }
            $note = 'Hotel updated.';
        }
        if ($act === 'update_room') {
            $hid = (int)$_POST['hotel_id'];
            $rn = trim($_POST['room_number'] ?? '');
            $pdo->prepare("
                UPDATE room SET price = :pr, TV = :tv, air_condition = :ac, fridge = :fr,
                    room_capacity = :cap, view_type = :vt, extendable = :ex, has_problems = :hp
                WHERE hotel_id = :hid AND room_number = :rn
            ")->execute([
                'pr' => (float)$_POST['price'],
                'tv' => isset($_POST['TV']),
                'ac' => isset($_POST['air_condition']),
                'fr' => isset($_POST['fridge']),
                'cap' => $_POST['room_capacity'] ?? 'double',
                'vt' => $_POST['view_type'] ?? 'city',
                'ex' => isset($_POST['extendable']),
                'hp' => isset($_POST['has_problems']),
                'hid' => $hid,
                'rn' => $rn,
            ]);
            $note = 'Room updated.';
        }
        if ($act === 'update_employee') {
            $eid = (int)$_POST['id'];
            $pdo->prepare("
                UPDATE employee SET hotel_id = :hid, first_name = :fn, last_name = :ln,
                    address = :ad, ssn_sin = :ss, is_manager = :mgr
                WHERE employee_id = :id
            ")->execute([
                'hid' => (int)$_POST['hotel_id'],
                'fn' => trim($_POST['first_name'] ?? ''),
                'ln' => trim($_POST['last_name'] ?? ''),
                'ad' => trim($_POST['address'] ?? ''),
                'ss' => trim($_POST['ssn_sin'] ?? ''),
                'mgr' => isset($_POST['is_manager']),
                'id' => $eid,
            ]);
            $pdo->prepare('DELETE FROM employee_role WHERE employee_id = :e')->execute(['e' => $eid]);
            $roles = trim($_POST['roles'] ?? '');
            if ($roles !== '') {
                foreach (array_map('trim', explode(',', $roles)) as $role) {
                    if ($role === '') {
                        continue;
                    }
                    $pdo->prepare('INSERT INTO employee_role (employee_id, role_name) VALUES (:e, :rn)')
                        ->execute(['e' => $eid, 'rn' => $role]);
                }
            }
            $note = 'Saved.';
        }
    } catch (Throwable $e) {
        $note = 'Error: ' . $e->getMessage();
    }
}

$chains = $pdo->query("SELECT chain_name FROM hotel_chain ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$hotels = $pdo->query("SELECT hotel_id, chain_name, area FROM hotel ORDER BY hotel_id")->fetchAll();

$editCustomerRow = null;
if ($editCustomer > 0) {
    $st = $pdo->prepare('SELECT * FROM customer WHERE customer_id = ?');
    $st->execute([$editCustomer]);
    $editCustomerRow = $st->fetch() ?: null;
}
$editHotelRow = null;
if ($editHotel > 0) {
    $st = $pdo->prepare('SELECT * FROM hotel WHERE hotel_id = ?');
    $st->execute([$editHotel]);
    $editHotelRow = $st->fetch() ?: null;
}
$editRoomRow = null;
if ($editRoomH > 0 && $editRoomN !== '') {
    $st = $pdo->prepare('SELECT r.*, h.area FROM room r JOIN hotel h ON h.hotel_id = r.hotel_id WHERE r.hotel_id = ? AND r.room_number = ?');
    $st->execute([$editRoomH, $editRoomN]);
    $editRoomRow = $st->fetch() ?: null;
}
$pgBool = static function ($v): bool {
    return $v === true || $v === 't' || $v === 1 || $v === '1';
};

$editEmployeeRow = null;
$editEmployeeRoles = '';
if ($editEmployee > 0) {
    $st = $pdo->prepare('SELECT * FROM employee WHERE employee_id = ?');
    $st->execute([$editEmployee]);
    $editEmployeeRow = $st->fetch() ?: null;
    if ($editEmployeeRow) {
        $st = $pdo->prepare('SELECT role_name FROM employee_role WHERE employee_id = ? ORDER BY role_name');
        $st->execute([$editEmployee]);
        $editEmployeeRoles = implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
?>
<h1>Edit data</h1>
<p><?= htmlspecialchars($note) ?></p>
<p>
    <a href="manage.php?t=customer" <?= $tab === 'customer' ? 'style="font-weight:bold"' : '' ?>>Customers</a> |
    <a href="manage.php?t=chain" <?= $tab === 'chain' ? 'style="font-weight:bold"' : '' ?>>Brands</a> |
    <a href="manage.php?t=hotel" <?= $tab === 'hotel' ? 'style="font-weight:bold"' : '' ?>>Hotels</a> |
    <a href="manage.php?t=room" <?= $tab === 'room' ? 'style="font-weight:bold"' : '' ?>>Rooms</a> |
    <a href="manage.php?t=employee" <?= $tab === 'employee' ? 'style="font-weight:bold"' : '' ?>>Staff</a>
</p>

<?php if ($tab === 'customer'): ?>
<h2>Customers</h2>
<?php if ($editCustomerRow): ?>
<h3>Edit customer #<?= (int)$editCustomerRow['customer_id'] ?></h3>
<form method="post">
    <input type="hidden" name="t" value="customer">
    <input type="hidden" name="act" value="update_customer">
    <input type="hidden" name="id" value="<?= (int)$editCustomerRow['customer_id'] ?>">
    <p><input name="first_name" value="<?= htmlspecialchars($editCustomerRow['first_name']) ?>" required></p>
    <p><input name="last_name" value="<?= htmlspecialchars($editCustomerRow['last_name']) ?>" required></p>
    <p><input name="street_address" value="<?= htmlspecialchars($editCustomerRow['street_address']) ?>" required></p>
    <p><input name="city" value="<?= htmlspecialchars($editCustomerRow['city']) ?>" required></p>
    <p><input name="ID_type" value="<?= htmlspecialchars($editCustomerRow['id_type']) ?>" required></p>
    <p><input name="ID_number" value="<?= htmlspecialchars($editCustomerRow['id_number']) ?>" required></p>
    <p><button type="submit">Save</button> <a href="manage.php?t=customer">Cancel</a></p>
</form>
<hr>
<?php endif; ?>
<table>
    <tr><th>id</th><th>name</th><th>city</th><th>ID</th><th></th></tr>
    <?php foreach ($pdo->query("SELECT * FROM customer ORDER BY customer_id") as $row): ?>
        <tr>
            <td><?= (int)$row['customer_id'] ?></td>
            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['city']) ?></td>
            <td><?= htmlspecialchars($row['id_type'] . ' ' . $row['id_number']) ?></td>
            <td>
                <a href="manage.php?t=customer&amp;edit_customer=<?= (int)$row['customer_id'] ?>">Edit</a>
                <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="act" value="del_customer">
                    <input type="hidden" name="id" value="<?= (int)$row['customer_id'] ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<h3>Add customer</h3>
<form method="post">
    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
    <input type="hidden" name="act" value="add_customer">
    <p><input name="first_name" placeholder="first name" required></p>
    <p><input name="last_name" placeholder="last name" required></p>
    <p><input name="street_address" placeholder="street" required></p>
    <p><input name="city" placeholder="city" required></p>
    <p><input name="ID_type" placeholder="ID type" value="SIN" required></p>
    <p><input name="ID_number" placeholder="ID number" required></p>
    <p><button type="submit">Add</button></p>
</form>

<?php elseif ($tab === 'chain'): ?>
<h2>Brands</h2>
<p>Each brand has a head office and a list of hotels. Deleting a brand deletes its hotels too (old data is archived first).</p>
<table>
    <tr><th>name</th><th>office address</th><th># hotels</th><th></th></tr>
    <?php foreach ($pdo->query("SELECT * FROM hotel_chain ORDER BY chain_name") as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['chain_name']) ?></td>
            <td><?= htmlspecialchars($row['address_central_offices']) ?></td>
            <td><?= (int)$row['number_hotels'] ?></td>
            <td>
                <form method="post" style="margin:0" onsubmit="return confirm('Delete this brand and all its hotels?');">
                    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="act" value="del_chain">
                    <input type="hidden" name="chain_name" value="<?= htmlspecialchars($row['chain_name']) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php elseif ($tab === 'hotel'): ?>
<h2>Hotels</h2>
<?php if ($editHotelRow): ?>
<h3>Edit hotel #<?= (int)$editHotelRow['hotel_id'] ?></h3>
<form method="post">
    <input type="hidden" name="t" value="hotel">
    <input type="hidden" name="act" value="update_hotel">
    <input type="hidden" name="id" value="<?= (int)$editHotelRow['hotel_id'] ?>">
    <p><select name="chain_name"><?php foreach ($chains as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $c === $editHotelRow['chain_name'] ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
    <?php endforeach; ?></select></p>
    <p><input name="street_number" value="<?= htmlspecialchars($editHotelRow['street_number']) ?>" required></p>
    <p><input name="street_name" value="<?= htmlspecialchars($editHotelRow['street_name']) ?>" required></p>
    <p><input name="area" value="<?= htmlspecialchars($editHotelRow['area']) ?>" required></p>
    <p><input name="contact_email" value="<?= htmlspecialchars($editHotelRow['contact_email']) ?>" required></p>
    <p><label>Stars <input type="number" name="hotel_rating" min="1" max="5" value="<?= (int)$editHotelRow['hotel_rating'] ?>"></label></p>
    <p><button type="submit">Save</button> <a href="manage.php?t=hotel">Cancel</a></p>
</form>
<hr>
<?php endif; ?>
<table>
    <tr><th>id</th><th>brand</th><th>area</th><th>stars</th><th></th></tr>
    <?php foreach ($pdo->query("SELECT * FROM hotel ORDER BY hotel_id") as $row): ?>
        <tr>
            <td><?= (int)$row['hotel_id'] ?></td>
            <td><?= htmlspecialchars($row['chain_name']) ?></td>
            <td><?= htmlspecialchars($row['area']) ?></td>
            <td><?= (int)$row['hotel_rating'] ?></td>
            <td>
                <a href="manage.php?t=hotel&amp;edit_hotel=<?= (int)$row['hotel_id'] ?>">Edit</a>
                <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="act" value="del_hotel">
                    <input type="hidden" name="id" value="<?= (int)$row['hotel_id'] ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<h3>Add hotel</h3>
<form method="post">
    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
    <input type="hidden" name="act" value="add_hotel">
    <p><select name="chain_name"><?php foreach ($chains as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></p>
    <p><input name="street_number" placeholder="street number" required></p>
    <p><input name="street_name" placeholder="street name" required></p>
    <p><input name="area" placeholder="area" required></p>
    <p><input name="contact_email" placeholder="email" required></p>
    <p><label>Stars <input type="number" name="hotel_rating" min="1" max="5" value="3"></label></p>
    <p><button type="submit">Add</button></p>
</form>

<?php elseif ($tab === 'room'): ?>
<h2>Rooms</h2>
<?php if ($editRoomRow): ?>
<h3>Edit room <?= (int)$editRoomRow['hotel_id'] ?> / <?= htmlspecialchars($editRoomRow['room_number']) ?></h3>
<form method="post">
    <input type="hidden" name="t" value="room">
    <input type="hidden" name="act" value="update_room">
    <input type="hidden" name="hotel_id" value="<?= (int)$editRoomRow['hotel_id'] ?>">
    <input type="hidden" name="room_number" value="<?= htmlspecialchars($editRoomRow['room_number']) ?>">
    <p><input name="price" type="number" step="0.01" value="<?= htmlspecialchars($editRoomRow['price']) ?>" required></p>
    <p><label><input type="checkbox" name="TV" <?= $pgBool($editRoomRow['tv'] ?? false) ? 'checked' : '' ?>> TV</label>
       <label><input type="checkbox" name="air_condition" <?= $pgBool($editRoomRow['air_condition'] ?? false) ? 'checked' : '' ?>> air_condition</label>
       <label><input type="checkbox" name="fridge" <?= $pgBool($editRoomRow['fridge'] ?? false) ? 'checked' : '' ?>> fridge</label></p>
    <p><select name="room_capacity"><?php foreach (['single','double','twin','suite','family'] as $x): ?>
        <option value="<?= $x ?>" <?= $x === $editRoomRow['room_capacity'] ? 'selected' : '' ?>><?= $x ?></option>
    <?php endforeach; ?></select></p>
    <p><select name="view_type"><?php foreach (['sea','mountain','city','garden'] as $x): ?>
        <option value="<?= $x ?>" <?= $x === $editRoomRow['view_type'] ? 'selected' : '' ?>><?= $x ?></option>
    <?php endforeach; ?></select></p>
    <p><label><input type="checkbox" name="extendable" <?= $pgBool($editRoomRow['extendable'] ?? false) ? 'checked' : '' ?>> extendable</label>
       <label><input type="checkbox" name="has_problems" <?= $pgBool($editRoomRow['has_problems'] ?? false) ? 'checked' : '' ?>> has_problems</label></p>
    <p><button type="submit">Save</button> <a href="manage.php?t=room">Cancel</a></p>
</form>
<hr>
<?php endif; ?>
<table>
    <tr><th>hotel</th><th>room</th><th>price</th><th>capacity</th><th></th></tr>
    <?php foreach ($pdo->query("SELECT r.*, h.area FROM room r JOIN hotel h ON h.hotel_id = r.hotel_id ORDER BY r.hotel_id, r.room_number") as $row): ?>
        <tr>
            <td><?= (int)$row['hotel_id'] ?> (<?= htmlspecialchars($row['area']) ?>)</td>
            <td><?= htmlspecialchars($row['room_number']) ?></td>
            <td><?= htmlspecialchars($row['price']) ?></td>
            <td><?= htmlspecialchars($row['room_capacity']) ?></td>
            <td>
                <a href="manage.php?t=room&amp;edit_room_h=<?= (int)$row['hotel_id'] ?>&amp;edit_room_n=<?= urlencode($row['room_number']) ?>">Edit</a>
                <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="act" value="del_room">
                    <input type="hidden" name="hotel_id" value="<?= (int)$row['hotel_id'] ?>">
                    <input type="hidden" name="room_number" value="<?= htmlspecialchars($row['room_number']) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<h3>Add room</h3>
<form method="post">
    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
    <input type="hidden" name="act" value="add_room">
    <p><select name="hotel_id"><?php foreach ($hotels as $h): ?><option value="<?= (int)$h['hotel_id'] ?>"><?= (int)$h['hotel_id'] ?> — <?= htmlspecialchars($h['chain_name']) ?> / <?= htmlspecialchars($h['area']) ?></option><?php endforeach; ?></select></p>
    <p><input name="room_number" placeholder="room number" required></p>
    <p><input name="price" type="number" step="0.01" placeholder="price" required></p>
    <p><label><input type="checkbox" name="TV" checked> TV</label>
       <label><input type="checkbox" name="air_condition" checked> air_condition</label>
       <label><input type="checkbox" name="fridge"> fridge</label></p>
    <p><select name="room_capacity"><?php foreach (['single','double','twin','suite','family'] as $x): ?><option><?= $x ?></option><?php endforeach; ?></select></p>
    <p><select name="view_type"><?php foreach (['sea','mountain','city','garden'] as $x): ?><option><?= $x ?></option><?php endforeach; ?></select></p>
    <p><label><input type="checkbox" name="extendable"> extendable</label>
       <label><input type="checkbox" name="has_problems"> has_problems</label></p>
    <p><button type="submit">Add</button></p>
</form>

<?php else: ?>
<h2>Staff</h2>
<?php if ($editEmployeeRow): ?>
<h3>Edit #<?= (int)$editEmployeeRow['employee_id'] ?></h3>
<form method="post">
    <input type="hidden" name="t" value="employee">
    <input type="hidden" name="act" value="update_employee">
    <input type="hidden" name="id" value="<?= (int)$editEmployeeRow['employee_id'] ?>">
    <p><select name="hotel_id"><?php foreach ($hotels as $h): ?>
        <option value="<?= (int)$h['hotel_id'] ?>" <?= (int)$h['hotel_id'] === (int)$editEmployeeRow['hotel_id'] ? 'selected' : '' ?>><?= (int)$h['hotel_id'] ?> — <?= htmlspecialchars($h['area']) ?></option>
    <?php endforeach; ?></select></p>
    <p><input name="first_name" value="<?= htmlspecialchars($editEmployeeRow['first_name']) ?>" required></p>
    <p><input name="last_name" value="<?= htmlspecialchars($editEmployeeRow['last_name']) ?>" required></p>
    <p><input name="address" value="<?= htmlspecialchars($editEmployeeRow['address']) ?>" required></p>
    <p><input name="ssn_sin" value="<?= htmlspecialchars($editEmployeeRow['ssn_sin']) ?>" required></p>
    <p><label><input type="checkbox" name="is_manager" <?= $pgBool($editEmployeeRow['is_manager'] ?? false) ? 'checked' : '' ?>> runs this hotel</label></p>
    <p><input name="roles" value="<?= htmlspecialchars($editEmployeeRoles) ?>" placeholder="other jobs, comma separated (optional)"></p>
    <p><button type="submit">Save</button> <a href="manage.php?t=employee">Cancel</a></p>
</form>
<hr>
<?php endif; ?>
<table>
    <tr><th>id</th><th>name</th><th>hotel</th><th>boss</th><th></th></tr>
    <?php foreach ($pdo->query("SELECT * FROM employee ORDER BY employee_id") as $row): ?>
        <tr>
            <td><?= (int)$row['employee_id'] ?></td>
            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
            <td><?= (int)$row['hotel_id'] ?></td>
            <td><?= $row['is_manager'] ? 'yes' : '' ?></td>
            <td>
                <a href="manage.php?t=employee&amp;edit_employee=<?= (int)$row['employee_id'] ?>">Edit</a>
                <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="act" value="del_employee">
                    <input type="hidden" name="id" value="<?= (int)$row['employee_id'] ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<h3>Add staff</h3>
<form method="post">
    <input type="hidden" name="t" value="<?= htmlspecialchars($tab) ?>">
    <input type="hidden" name="act" value="add_employee">
    <p><select name="hotel_id"><?php foreach ($hotels as $h): ?><option value="<?= (int)$h['hotel_id'] ?>"><?= (int)$h['hotel_id'] ?></option><?php endforeach; ?></select></p>
    <p><input name="first_name" required></p>
    <p><input name="last_name" required></p>
    <p><input name="address" required></p>
    <p><input name="ssn_sin" placeholder="unique SSN/SIN" required></p>
    <p><label><input type="checkbox" name="is_manager"> runs this hotel</label></p>
    <p><input name="role_name" placeholder="one extra job title (optional)"></p>
    <p><button type="submit">Add</button></p>
</form>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
