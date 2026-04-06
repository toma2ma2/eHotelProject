<?php
require __DIR__ . '/config.php';
require __DIR__ . '/header.php';

$chains = $pdo->query("SELECT chain_name FROM hotel_chain ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$areas = $pdo->query("SELECT DISTINCT area FROM hotel ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$caps = $pdo->query("SELECT DISTINCT room_capacity FROM room ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);

$start = $_GET['start_date'] ?? date('Y-m-d', strtotime('+1 day'));
$end = $_GET['end_date'] ?? date('Y-m-d', strtotime('+3 days'));
$chain = $_GET['chain_name'] ?? '';
$area = $_GET['area'] ?? '';
$cap = $_GET['room_capacity'] ?? '';
$rating = $_GET['hotel_rating'] ?? '';
$minRooms = $_GET['number_rooms'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$minRoomsP = ($minRooms === '') ? null : (int)$minRooms;
$maxPriceP = ($maxPrice === '') ? null : (float)$maxPrice;

$bookFirst = trim($_GET['book_first_name'] ?? '');
$bookLast = trim($_GET['book_last_name'] ?? '');

$rows = [];
if (isset($_GET['go'])) {
    $sql = "
        SELECT r.hotel_id, r.room_number, r.price, r.room_capacity, r.view_type,
               h.chain_name, h.area, h.hotel_rating, h.number_rooms
        FROM room r
        JOIN hotel h ON h.hotel_id = r.hotel_id
        WHERE r.has_problems IS FALSE
          AND (:chain = '' OR h.chain_name = :chain)
          AND (:area = '' OR h.area = :area)
          AND (:cap = '' OR r.room_capacity = :cap)
          AND (:rating = '' OR h.hotel_rating::text = :rating)
          AND NOT EXISTS (
              SELECT 1 FROM booking b
              WHERE b.hotel_id = r.hotel_id AND b.room_number = r.room_number
                AND b.status = 'active'
                AND b.start_date < :end::date AND b.end_date > :start::date
          )
          AND NOT EXISTS (
              SELECT 1 FROM renting x
              WHERE x.hotel_id = r.hotel_id AND x.room_number = r.room_number
                AND x.start_date < :end::date AND x.end_date > :start::date
          )
    ";
    $params = [
        'chain' => $chain,
        'area' => $area,
        'cap' => $cap,
        'rating' => $rating,
        'start' => $start,
        'end' => $end,
    ];
    if ($minRoomsP !== null) {
        $sql .= ' AND h.number_rooms >= :minrooms';
        $params['minrooms'] = $minRoomsP;
    }
    if ($maxPriceP !== null) {
        $sql .= ' AND r.price <= :maxprice';
        $params['maxprice'] = $maxPriceP;
    }
    $sql .= ' ORDER BY h.chain_name, h.hotel_id, r.room_number';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
}
?>
<h1>Search rooms</h1>
<?php if (($_SESSION['app_role'] ?? '') === 'customer'): ?>
    <p><a href="my_bookings.php">Bookings list</a></p>
<?php endif; ?>
<p>Put your name below, search, then book a room from the table.</p>
<form method="get" id="roomSearch">
    <input type="hidden" name="go" value="1">
    <p><label>Start date <input type="date" name="start_date" value="<?= htmlspecialchars($start) ?>" required></label></p>
    <p><label>End date <input type="date" name="end_date" value="<?= htmlspecialchars($end) ?>" required></label></p>
    <p><label>Brand
        <select name="chain_name">
            <option value="">(any)</option>
            <?php foreach ($chains as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $c === $chain ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Area
        <select name="area">
            <option value="">(any)</option>
            <?php foreach ($areas as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $a === $area ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Room capacity
        <select name="room_capacity">
            <option value="">(any)</option>
            <?php foreach ($caps as $x): ?>
                <option value="<?= htmlspecialchars($x) ?>" <?= $x === $cap ? 'selected' : '' ?>><?= htmlspecialchars($x) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Hotel category (stars)
        <select name="hotel_rating">
            <option value="">(any)</option>
            <?php for ($s = 1; $s <= 5; $s++): ?>
                <option value="<?= $s ?>" <?= (string)$rating === (string)$s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endfor; ?>
        </select>
    </label></p>
    <p><label>Min rooms in hotel <input type="number" name="number_rooms" min="0" value="<?= htmlspecialchars($minRooms) ?>" placeholder="any"></label></p>
    <p><label>Max room price <input type="number" step="0.01" name="max_price" value="<?= htmlspecialchars($maxPrice) ?>" placeholder="any"></label></p>
    <fieldset>
        <legend>Name on the booking</legend>
        <p><label>First <input type="text" name="book_first_name" value="<?= htmlspecialchars($bookFirst) ?>" autocomplete="given-name"></label></p>
        <p><label>Last <input type="text" name="book_last_name" value="<?= htmlspecialchars($bookLast) ?>" autocomplete="family-name"></label></p>
    </fieldset>
    <p><button type="submit">Search</button></p>
</form>
<script>
(function () {
    var f = document.getElementById('roomSearch');
    if (!f) return;
    f.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.name || t.name === 'go') return;
        if (t.name === 'book_first_name' || t.name === 'book_last_name') return;
        f.submit();
    });
})();
</script>

<?php if (isset($_GET['go'])): ?>
<h2>Results</h2>
<?php if (!$rows): ?>
    <p>No rooms match.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Brand</th><th>Area</th><th>Stars</th><th># rooms</th>
            <th>Room</th><th>Size</th><th>Price</th><th>Book</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['chain_name']) ?></td>
            <td><?= htmlspecialchars($r['area']) ?></td>
            <td><?= (int)$r['hotel_rating'] ?></td>
            <td><?= (int)$r['number_rooms'] ?></td>
            <td><?= htmlspecialchars($r['hotel_id']) ?> / <?= htmlspecialchars($r['room_number']) ?></td>
            <td><?= htmlspecialchars($r['room_capacity']) ?></td>
            <td><?= htmlspecialchars($r['price']) ?></td>
            <td>
                <form method="post" action="book.php" style="margin:0">
                    <input type="hidden" name="hotel_id" value="<?= (int)$r['hotel_id'] ?>">
                    <input type="hidden" name="room_number" value="<?= htmlspecialchars($r['room_number']) ?>">
                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                    <input type="hidden" name="first_name" value="<?= htmlspecialchars($bookFirst) ?>">
                    <input type="hidden" name="last_name" value="<?= htmlspecialchars($bookLast) ?>">
                    <button type="submit" <?= ($bookFirst === '' || $bookLast === '') ? 'disabled title="Enter your name above first"' : '' ?>>Book</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
