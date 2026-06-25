<?php
require_once 'auth.php';
require_once 'DbConnector.php';
include 'header.php';
requireRole('admin', 'moderator');

$db      = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');
$success = '';

// Admin force-cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (isModerator() && verifyCsrf()) {
        $rid = (int)$_POST['reservation_id'];
        $db->prepare('UPDATE reservations SET status = "cancelled" WHERE id = :id')
           ->execute([':id' => $rid]);
        refreshCsrf();
        $success = 'Reservation cancelled.';
    }
}

// READ — all reservations with complex JOIN
$filter = $_GET['status'] ?? 'all';
$where  = $filter === 'confirmed' ? 'AND res.status = "confirmed"'
        : ($filter === 'cancelled' ? 'AND res.status = "cancelled"' : '');

$reservations = $db->query("
    SELECT res.id, res.seats, res.status, res.reserved_at,
           u.username, u.email,
           m.title AS movie_title,
           s.screening_date, s.screening_time, s.price,
           r.name  AS room_name,
           (res.seats * s.price) AS total
    FROM reservations res
    JOIN users     u  ON res.user_id     = u.id
    JOIN screenings s ON res.screening_id = s.id
    JOIN movies    m  ON s.movie_id      = m.id
    JOIN rooms     r  ON s.room_id       = r.id
    WHERE res.deleted_at IS NULL {$where}
    ORDER BY res.reserved_at DESC
    LIMIT 100
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reservations — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="page-title">Manage Reservations</div>
    <p class="page-subtitle">All bookings — <?php echo count($reservations); ?> result(s)</p>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- Filter -->
    <div style="display:flex; gap:8px; margin-bottom:20px">
        <?php foreach (['all','confirmed','cancelled'] as $f): ?>
        <a href="?status=<?php echo $f; ?>"
           class="btn btn-outline btn-sm <?php echo $filter === $f ? 'active' : ''; ?>"
           style="<?php echo $filter === $f ? 'border-color:var(--accent); color:var(--accent)' : ''; ?>">
            <?php echo ucfirst($f); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Movie</th>
                        <th>Date</th>
                        <th>Room</th>
                        <th>Seats</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Booked On</th>
                        <?php if (isModerator()): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $i => $res): ?>
                    <tr style="animation:fadeUp .3s ease <?php echo $i*.03; ?>s both">
                        <td style="color:var(--muted)"><?php echo (int)$res['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($res['username']); ?></strong>
                            <span style="display:block; font-size:.75rem; color:var(--muted)"><?php echo htmlspecialchars($res['email']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($res['movie_title']); ?></td>
                        <td><?php echo htmlspecialchars($res['screening_date']); ?> <?php echo substr($res['screening_time'],0,5); ?></td>
                        <td><?php echo htmlspecialchars($res['room_name']); ?></td>
                        <td><?php echo (int)$res['seats']; ?></td>
                        <td style="color:var(--accent)">€<?php echo number_format((float)$res['total'],2); ?></td>
                        <td><span class="status status-<?php echo $res['status']; ?>"><?php echo $res['status']; ?></span></td>
                        <td style="font-size:.8rem; color:var(--muted)"><?php echo substr($res['reserved_at'],0,10); ?></td>
                        <?php if (isModerator()): ?>
                        <td>
                            <?php if ($res['status'] === 'confirmed'): ?>
                            <form method="POST">
                                <input type="hidden" name="action"         value="cancel">
                                <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                <input type="hidden" name="token"          value="<?php echo htmlspecialchars(csrf()); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:30px">No reservations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
