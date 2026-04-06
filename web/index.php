<?php
require __DIR__ . '/config.php';

if (isset($_GET['clear'])) {
    unset($_SESSION['app_role']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['set_role'] ?? '') === '1') {
    $r = $_POST['role'] ?? 'customer';
    $_SESSION['app_role'] = ($r === 'employee') ? 'employee' : 'customer';
    if ($_SESSION['app_role'] === 'employee') {
        header('Location: employee.php');
    } else {
        header('Location: customer_home.php');
    }
    exit;
}

require __DIR__ . '/header.php';
$role = $_SESSION['app_role'] ?? null;
?>
<h1>Hotel booking</h1>

<?php if ($role): ?>
    <p>Logged in as: <strong><?= $role === 'employee' ? 'staff' : 'customer' ?></strong></p>
    <ul>
        <?php if ($role === 'customer'): ?>
            <li><a href="customer_home.php">Home</a></li>
        <?php else: ?>
            <li><a href="employee.php">Front desk</a></li>
            <li><a href="manage.php">Edit hotels / rooms / people</a></li>
        <?php endif; ?>
        <li><a href="views.php">Reports</a></li>
    </ul>
    <p><a href="index.php?clear=1">Log out / switch</a></p>
<?php else: ?>
    <p>Pick one (you can change later):</p>
    <form method="post" class="role-form">
        <input type="hidden" name="set_role" value="1">
        <fieldset>
            <legend>Who are you?</legend>
            <p class="radio-row">
                <label class="radio-label">
                    <input type="radio" name="role" value="customer" checked>
                    <strong>Customer</strong> — search rooms, make bookings
                </label>
            </p>
            <p class="radio-row">
                <label class="radio-label">
                    <input type="radio" name="role" value="employee">
                    <strong>Staff</strong> — check-ins, rentings, payments
                </label>
            </p>
        </fieldset>
        <p><button type="submit">Continue</button></p>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
