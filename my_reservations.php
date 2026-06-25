<?php
require_once 'auth.php';
require_once 'DbConnector.php';
include 'header.php';
requireLogin();

$db      = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');
$error   = '';
$success = '';

// CANCEL reservation — trigger trg_after_reservation_update restores seats
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (verifyCsrf()) {
        $rid = (int)$_POST['reservation_id'];
        $uid = (int)$_SESSION['user_id'];

        // Make sure this reservation belongs to this user
        $check = $db->prepare('SELECT id FROM reservations WHERE id = :r AND user_id = :u AND status = "confirmed"');
        $check->execute([':r' => $rid, ':u' => $uid]);

        if ($check->fetch()) {
            $stmt = $db->prepare('UPDATE reservations SET status = "cancelled" WHERE id = :id');
            $stmt->execute([':id' => $rid]);
            // Trigger trg_after_reservation_update fires automatically
            refreshCsrf();
            $success = 'Reservation cancelled. Seats have been released.';
        } else {
            $error = 'Reservation not found or already cancelled.';
        }
    }
}

// READ via stored procedure
$uid   = (int)$_SESSION['user_id'];
$stmt  = $db->prepare('CALL sp_user_reservations(:uid)');
$stmt->execute([':uid' => $uid]);
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="page-title">My Bookings</div>
    <p class="page-subtitle"><?php echo count($reservations); ?> reservation(s) found</p>

    <?php if ($error):   ?><div class="alert alert-error"  ><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Movie</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Seats</th>
                        <th>Total Paid</th>
                        <th>Status</th>
                        <th>Booked On</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $i => $r): ?>
                    <tr style="animation:fadeUp .3s ease <?php echo $i*.04; ?>s both">
                        <td style="color:var(--muted)"><?php echo (int)$r['reservation_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['movie_title']); ?></strong>
                            <span style="display:block; font-size:.75rem; color:var(--muted)"><?php echo htmlspecialchars($r['genre']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($r['screening_date']); ?></td>
                        <td><?php echo substr($r['screening_time'],0,5); ?></td>
                        <td><?php echo htmlspecialchars($r['room_name']); ?></td>
                        <td><?php echo (int)$r['seats']; ?></td>
                        <td style="color:var(--accent); font-weight:500">€<?php echo number_format((float)$r['total_paid'],2); ?></td>
                        <td>
                            <span class="status status-<?php echo htmlspecialchars($r['status']); ?>">
                                <?php echo htmlspecialchars($r['status']); ?>
                            </span>
                        </td>
                        <td style="font-size:.8rem; color:var(--muted)"><?php echo htmlspecialchars(substr($r['reserved_at'],0,10)); ?></td>
                        <td>
                            <?php if ($r['status'] === 'confirmed'): ?>
                            <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                                <input type="hidden" name="action"         value="cancel">
                                <input type="hidden" name="reservation_id" value="<?php echo (int)$r['reservation_id']; ?>">
                                <input type="hidden" name="token"          value="<?php echo htmlspecialchars(csrf()); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="10" style="text-align:center; color:var(--muted); padding:30px">No bookings yet. <a href="screenings.php" style="color:var(--accent)">Browse screenings</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
