<?php
require __DIR__ . '/config.php';
require __DIR__ . '/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<p class="err">Go to Search first.</p>';
    require __DIR__ . '/footer.php';
    exit;
}

$hotelId = (int)($_POST['hotel_id'] ?? 0);
$roomNumber = trim($_POST['room_number'] ?? '');
$start = $_POST['start_date'] ?? '';
$end = $_POST['end_date'] ?? '';
$first = trim($_POST['first_name'] ?? '');
$last = trim($_POST['last_name'] ?? '');

if ($first === '' || $last === '') {
    echo '<p class="err">Fill in first and last name on Search.</p>';
    require __DIR__ . '/footer.php';
    exit;
}

try {
    $pdo->beginTransaction();

    $find = $pdo->prepare('
        SELECT customer_id FROM customer
        WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(:fn))
          AND LOWER(TRIM(last_name)) = LOWER(TRIM(:ln))
        LIMIT 1
    ');
    $find->execute(['fn' => $first, 'ln' => $last]);
    $cid = $find->fetchColumn();

    if (!$cid) {
        $idNum = 'WEB-' . bin2hex(random_bytes(8));
        $insC = $pdo->prepare('
            INSERT INTO customer (first_name, last_name, street_address, city, ID_type, ID_number)
            VALUES (:fn, :ln, :st, :ci, :it, :in)
            RETURNING customer_id
        ');
        $insC->execute([
            'fn' => $first,
            'ln' => $last,
            'st' => '—',
            'ci' => '—',
            'it' => 'web',
            'in' => $idNum,
        ]);
        $cid = (int)$insC->fetchColumn();
    } else {
        $cid = (int)$cid;
    }

    $ins = $pdo->prepare("
        INSERT INTO booking (customer_id, hotel_id, room_number, booking_date, start_date, end_date, status)
        VALUES (:cid, :hid, :rn, CURRENT_DATE, :sd, :ed, 'active')
    ");
    $ins->execute([
        'cid' => $cid,
        'hid' => $hotelId,
        'rn' => $roomNumber,
        'sd' => $start,
        'ed' => $end,
    ]);

    $pdo->commit();
    echo '<p class="ok">Booking saved for ' . htmlspecialchars($first . ' ' . $last) . '.</p>';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '<p class="err">Could not book: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
<p><a href="search.php">Back to search</a></p>
<?php require __DIR__ . '/footer.php'; ?>
