<?php
require_once 'auth.php';
require_once 'DbConnector.php';
include 'header.php';
requireLogin();

$db = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');

// Dashboard stats via stored procedure (admin only)
$stats = [];
if (isAdmin()) {
    $statsStmt = $db->query('CALL sp_dashboard_stats()');
    $stats = $statsStmt->fetch() ?: [];
    $statsStmt->closeCursor();
}

// Latest screenings (complex JOIN query)
$screenings = $db->query('
    SELECT s.id, s.screening_date, s.screening_time, s.price, s.seats_available,
           m.title AS movie_title, m.genre, m.duration,
           r.name  AS room_name,
           COUNT(res.id) AS reservation_count
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms  r ON s.room_id  = r.id
    LEFT JOIN reservations res ON res.screening_id = s.id AND res.status = "confirmed"
    WHERE s.deleted_at IS NULL
      AND s.screening_date >= CURDATE()
    GROUP BY s.id
    ORDER BY s.screening_date, s.screening_time
    LIMIT 8
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="page-title">Dashboard</div>
    <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?> ✦</p>

    <?php if (isAdmin() && $stats): ?>
    <div class="stats-grid">
        <div class="stat-card" style="animation-delay:.05s">
            <div class="stat-value"><?php echo (int)$stats['total_movies']; ?></div>
            <div class="stat-label">Movies</div>
        </div>
        <div class="stat-card" style="animation-delay:.10s">
            <div class="stat-value"><?php echo (int)$stats['total_screenings']; ?></div>
            <div class="stat-label">Screenings</div>
        </div>
        <div class="stat-card" style="animation-delay:.15s">
            <div class="stat-value"><?php echo (int)$stats['total_reservations']; ?></div>
            <div class="stat-label">Reservations</div>
        </div>
        <div class="stat-card" style="animation-delay:.20s">
            <div class="stat-value"><?php echo (int)$stats['total_users']; ?></div>
            <div class="stat-label">Users</div>
        </div>
        <div class="stat-card" style="animation-delay:.25s">
            <div class="stat-value">€<?php echo number_format((float)$stats['total_revenue'], 0); ?></div>
            <div class="stat-label">Revenue</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="section-header">
        <div class="section-title">Upcoming Screenings</div>
        <a href="screenings.php" class="btn btn-outline btn-sm">View All</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>Genre</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Seats Left</th>
                        <th>Price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($screenings)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--muted); padding:30px">No upcoming screenings.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($screenings as $i => $s): ?>
                        <tr style="animation:fadeUp .3s ease <?php echo $i * 0.04; ?>s both">
                            <td><strong><?php echo htmlspecialchars($s['movie_title']); ?></strong></td>
                            <td><span style="color:var(--muted); font-size:.82rem"><?php echo htmlspecialchars($s['genre']); ?></span></td>
                            <td><?php echo htmlspecialchars($s['screening_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($s['screening_time'], 0, 5)); ?></td>
                            <td><?php echo htmlspecialchars($s['room_name']); ?></td>
                            <td>
                                <?php
                                $left = (int)$s['seats_available'];
                                $color = $left > 10 ? 'var(--success)' : ($left > 0 ? 'var(--gold)' : 'var(--danger)');
                                ?>
                                <span style="color:<?php echo $color; ?>; font-weight:500"><?php echo $left; ?></span>
                            </td>
                            <td>€<?php echo number_format((float)$s['price'], 2); ?></td>
                            <td>
                                <?php if ($left > 0): ?>
                                    <a href="reserve.php?screening_id=<?php echo (int)$s['id']; ?>" class="btn btn-primary btn-sm">Book</a>
                                <?php else: ?>
                                    <span style="color:var(--danger); font-size:.78rem">Full</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
