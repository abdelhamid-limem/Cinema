<?php
require_once 'auth.php';
require_once 'DbConnector.php';
requireLogin();

$db      = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');
$error   = '';
$success = '';

$screeningId = (int)($_GET['screening_id'] ?? $_POST['screening_id'] ?? 0);

// CREATE reservation — trigger handles seat decrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserve') {
    if (verifyCsrf()) {
        $seats = max(1, (int)($_POST['seats'] ?? 1));
        $sid   = (int)$_POST['screening_id'];
        $uid   = (int)$_SESSION['user_id'];

        // Check available seats
        $check = $db->prepare('SELECT seats_available, price FROM screenings WHERE id = :id AND deleted_at IS NULL');
        $check->execute([':id' => $sid]);
        $sc = $check->fetch();

        if (!$sc) {
            $error = 'Screening not found.';
        } elseif ($sc['seats_available'] < $seats) {
            $error = 'Not enough seats available (only ' . (int)$sc['seats_available'] . ' left).';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO reservations (user_id, screening_id, seats) VALUES (:u, :s, :n)'
            );
            $stmt->execute([':u' => $uid, ':s' => $sid, ':n' => $seats]);
            // Trigger trg_after_reservation_insert fires automatically here
            refreshCsrf();
            $success = "Booking confirmed! {$seats} seat(s) reserved.";
        }
    }
}

// Fetch screening details
$screening = null;
if ($screeningId) {
    $stmt = $db->prepare('
        SELECT s.*, m.title AS movie_title, m.genre, m.duration, m.description,
               r.name AS room_name, r.capacity
        FROM screenings s
        JOIN movies m ON s.movie_id = m.id
        JOIN rooms  r ON s.room_id  = r.id
        WHERE s.id = :id AND s.deleted_at IS NULL
    ');
    $stmt->execute([':id' => $screeningId]);
    $screening = $stmt->fetch();
}

if (!$screening) {
    header('Location: screenings.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container-sm" style="max-width:600px">

    <a href="screenings.php" style="color:var(--muted); font-size:.85rem; text-decoration:none; display:block; margin-bottom:20px">← Back to Screenings</a>

    <div class="page-title"><?php echo htmlspecialchars($screening['movie_title']); ?></div>
    <p class="page-subtitle"><?php echo htmlspecialchars($screening['genre']); ?> · <?php echo (int)$screening['duration']; ?> min</p>

    <?php if ($error):   ?><div class="alert alert-error"  ><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:20px">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
            <div>
                <div class="form-label">Date</div>
                <div style="font-size:1.1rem; font-weight:500"><?php echo htmlspecialchars($screening['screening_date']); ?></div>
            </div>
            <div>
                <div class="form-label">Time</div>
                <div style="font-size:1.1rem; font-weight:500"><?php echo substr($screening['screening_time'],0,5); ?></div>
            </div>
            <div>
                <div class="form-label">Room</div>
                <div><?php echo htmlspecialchars($screening['room_name']); ?></div>
            </div>
            <div>
                <div class="form-label">Seats Available</div>
                <div style="color:var(--success); font-weight:500"><?php echo (int)$screening['seats_available']; ?></div>
            </div>
            <div>
                <div class="form-label">Price per Seat</div>
                <div style="color:var(--accent); font-weight:600; font-size:1.2rem">€<?php echo number_format((float)$screening['price'],2); ?></div>
            </div>
        </div>
        <?php if ($screening['description']): ?>
        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border); color:var(--muted); font-size:.88rem; line-height:1.7">
            <?php echo htmlspecialchars($screening['description']); ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ((int)$screening['seats_available'] > 0): ?>
    <div class="card">
        <div class="section-title" style="font-size:1.1rem; margin-bottom:18px">Complete Your Booking</div>
        <form method="POST">
            <input type="hidden" name="action"       value="reserve">
            <input type="hidden" name="screening_id" value="<?php echo (int)$screening['id']; ?>">
            <input type="hidden" name="token"        value="<?php echo htmlspecialchars(csrf()); ?>">
            <div class="form-group">
                <label class="form-label">Number of Seats</label>
                <input type="number" name="seats" class="form-control" value="1" min="1"
                       max="<?php echo min(10, (int)$screening['seats_available']); ?>" id="seats-input">
            </div>
            <div style="margin-bottom:18px; font-size:.9rem; color:var(--muted)">
                Total: <span id="total" style="color:var(--accent); font-weight:600">€<?php echo number_format((float)$screening['price'],2); ?></span>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">
                Confirm Booking
            </button>
        </form>
    </div>
    <?php else: ?>
        <div class="alert alert-error">This screening is fully booked.</div>
    <?php endif; ?>

</div>
<script>
const price = <?php echo (float)$screening['price']; ?>;
document.getElementById('seats-input')?.addEventListener('input', function() {
    const total = (parseInt(this.value) || 1) * price;
    document.getElementById('total').textContent = '€' + total.toFixed(2);
});
</script>
</body>
</html>
