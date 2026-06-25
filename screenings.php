<?php
require_once 'auth.php';
require_once 'DbConnector.php';
include 'header.php';
requireLogin();

$db      = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');
$error   = '';
$success = '';

// CREATE — admin/moderator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (isModerator() && verifyCsrf()) {
        $movie_id = (int)$_POST['movie_id'];
        $room_id  = (int)$_POST['room_id'];
        $date     = $_POST['screening_date'] ?? '';
        $time     = $_POST['screening_time'] ?? '';
        $price    = (float)$_POST['price'];

        // Get room capacity
        $cap = $db->prepare('SELECT capacity FROM rooms WHERE id = :id');
        $cap->execute([':id' => $room_id]);
        $room = $cap->fetch();

        if ($movie_id && $room_id && $date && $time && $price > 0 && $room) {
            $stmt = $db->prepare(
                'INSERT INTO screenings (movie_id, room_id, screening_date, screening_time, price, seats_available)
                 VALUES (:m, :r, :d, :t, :p, :seats)'
            );
            $stmt->execute([':m'=>$movie_id,':r'=>$room_id,':d'=>$date,':t'=>$time,':p'=>$price,':seats'=>$room['capacity']]);
            refreshCsrf();
            $success = 'Screening added.';
        } else {
            $error = 'All fields are required.';
        }
    }
}

// DELETE (soft) — admin only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (isAdmin() && verifyCsrf()) {
        $id = (int)$_POST['screening_id'];
        $db->prepare('UPDATE screenings SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $id]);
        refreshCsrf();
        $success = 'Screening removed.';
    }
}

// UPDATE price — moderator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (isModerator() && verifyCsrf()) {
        $id    = (int)$_POST['screening_id'];
        $price = (float)$_POST['price'];
        $db->prepare('UPDATE screenings SET price = :p WHERE id = :id')->execute([':p'=>$price, ':id'=>$id]);
        refreshCsrf();
        $success = 'Price updated.';
    }
}

// READ — screenings with JOIN (complex query using stored procedure alternative)
$screenings = $db->query('
    SELECT s.*,
           m.title AS movie_title, m.genre,
           r.name  AS room_name,  r.capacity,
           (r.capacity - s.seats_available)        AS seats_taken,
           COUNT(res.id)                             AS confirmed_reservations
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms  r ON s.room_id  = r.id
    LEFT JOIN reservations res ON res.screening_id = s.id AND res.status = "confirmed"
    WHERE s.deleted_at IS NULL
    GROUP BY s.id
    ORDER BY s.screening_date DESC, s.screening_time
')->fetchAll();

$movies = $db->query('SELECT id, title FROM movies WHERE deleted_at IS NULL ORDER BY title')->fetchAll();
$rooms  = $db->query('SELECT id, name, capacity FROM rooms WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Screenings — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="page-title">Screenings</div>

    <p class="page-subtitle">Schedule — <?php echo count($screenings); ?> screening(s)</p>

    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (isModerator()): ?>
    <div class="card" style="margin-bottom:28px">
        <div class="section-title" style="margin-bottom:20px; font-size:1.2rem">Add Screening</div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="token"  value="<?php echo htmlspecialchars(csrf()); ?>">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px">
                <div class="form-group">
                    <label class="form-label">Movie</label>
                    <select name="movie_id" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach ($movies as $mv): ?>
                            <option value="<?php echo (int)$mv['id']; ?>"><?php echo htmlspecialchars($mv['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Room</label>
                    <select name="room_id" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach ($rooms as $rm): ?>
                            <option value="<?php echo (int)$rm['id']; ?>"><?php echo htmlspecialchars($rm['name']); ?> (<?php echo (int)$rm['capacity']; ?> seats)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="screening_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time</label>
                    <input type="time" name="screening_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (€)</label>
                    <input type="number" name="price" class="form-control" step="0.50" min="0" value="9.50" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:4px">Add Screening</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Seats Left</th>
                        <th>Taken</th>
                        <th>Price</th>
                        <th>Bookings</th>
                        <?php if (isModerator()): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($screenings as $i => $s): ?>
                    <tr style="animation:fadeUp .3s ease <?php echo $i*.04; ?>s both">
                        <td><strong><?php echo htmlspecialchars($s['movie_title']); ?></strong>
                            <span style="display:block; font-size:.75rem; color:var(--muted)"><?php echo htmlspecialchars($s['genre']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($s['screening_date']); ?></td>
                        <td><?php echo substr($s['screening_time'],0,5); ?></td>
                        <td><?php echo htmlspecialchars($s['room_name']); ?></td>
                        <td>
                            <?php $left = (int)$s['seats_available']; $c = $left > 10 ? 'var(--success)' : ($left > 0 ? 'var(--gold)' : 'var(--danger)'); ?>
                            <span style="color:<?php echo $c ?>; font-weight:500"><?php echo $left; ?></span>
                        </td>
                        <td><?php echo (int)$s['seats_taken']; ?></td>
                        <td>€<?php echo number_format((float)$s['price'],2); ?></td>
                        <td><?php echo (int)$s['confirmed_reservations']; ?></td>
                        <?php if (isModerator()): ?>
                        <td>
                            <div style="display:flex; gap:6px">
                                <button class="btn btn-outline btn-sm" onclick="togglePrice(<?php echo (int)$s['id']; ?>)">Price</button>
                                <?php if (isAdmin()): ?>
                                <form method="POST" onsubmit="return confirm('Remove this screening?')">
                                    <input type="hidden" name="action"       value="delete">
                                    <input type="hidden" name="screening_id" value="<?php echo (int)$s['id']; ?>">
                                    <input type="hidden" name="token"        value="<?php echo htmlspecialchars(csrf()); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <div id="price-<?php echo (int)$s['id']; ?>" style="display:none; margin-top:8px">
                                <form method="POST" style="display:flex; gap:6px; align-items:center">
                                    <input type="hidden" name="action"       value="update">
                                    <input type="hidden" name="screening_id" value="<?php echo (int)$s['id']; ?>">
                                    <input type="hidden" name="token"        value="<?php echo htmlspecialchars(csrf()); ?>">
                                    <input type="number" name="price" class="form-control" style="width:90px" step="0.50" value="<?php echo (float)$s['price']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Save</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($screenings)): ?>
                        <tr><td colspan="9" style="text-align:center; color:var(--muted); padding:30px">No screenings.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<script>
function togglePrice(id) {
    const el = document.getElementById('price-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
