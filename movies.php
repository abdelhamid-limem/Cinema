<?php
require_once 'auth.php';
require_once 'DbConnector.php';
include 'header.php';
requireLogin();

$db      = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');
$error   = '';
$success = '';

// CREATE — admin/moderator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (isModerator() && verifyCsrf()) {
        $title       = trim($_POST['title']       ?? '');
        $genre       = trim($_POST['genre']       ?? '');
        $duration    = (int)($_POST['duration']   ?? 0);
        $description = trim($_POST['description'] ?? '');
        $year        = (int)($_POST['release_year'] ?? date('Y'));

        if ($title && $genre && $duration > 0) {
            $stmt = $db->prepare(
                'INSERT INTO movies (title, genre, duration, description, release_year)
                 VALUES (:title, :genre, :duration, :description, :year)'
            );
            $stmt->execute([
                ':title'       => $title,
                ':genre'       => $genre,
                ':duration'    => $duration,
                ':description' => $description,
                ':year'        => $year,
            ]);
            refreshCsrf();
            $success = 'Movie added successfully.';
        } else {
            $error = 'Title, genre and duration are required.';
        }
    }
}

// UPDATE — admin/moderator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (isModerator() && verifyCsrf()) {
        $id          = (int)$_POST['movie_id'];
        $title       = trim($_POST['title']       ?? '');
        $genre       = trim($_POST['genre']       ?? '');
        $duration    = (int)($_POST['duration']   ?? 0);
        $description = trim($_POST['description'] ?? '');
        $year        = (int)($_POST['release_year'] ?? date('Y'));

        $stmt = $db->prepare(
            'UPDATE movies SET title=:t, genre=:g, duration=:d, description=:desc, release_year=:y
             WHERE id=:id AND deleted_at IS NULL'
        );
        $stmt->execute([':t'=>$title,':g'=>$genre,':d'=>$duration,':desc'=>$description,':y'=>$year,':id'=>$id]);
        refreshCsrf();
        $success = 'Movie updated.';
    }
}

// DELETE (soft) — admin only → trigger cascades screenings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (isAdmin() && verifyCsrf()) {
        $id   = (int)$_POST['movie_id'];
        $stmt = $db->prepare('UPDATE movies SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
        refreshCsrf();
        $success = 'Movie deleted (screenings removed by trigger).';
    }
}

// READ — all movies with screening count (complex query)
$movies = $db->query('
    SELECT m.*,
           COUNT(DISTINCT s.id)   AS screening_count,
           COUNT(DISTINCT res.id) AS reservation_count
    FROM movies m
    LEFT JOIN screenings   s   ON s.movie_id = m.id   AND s.deleted_at IS NULL
    LEFT JOIN reservations res ON res.screening_id = s.id AND res.status = "confirmed"
    WHERE m.deleted_at IS NULL
    GROUP BY m.id
    ORDER BY m.title
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Movies — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="page-title">Movies</div>
    <p class="page-subtitle">Full catalogue — <?php echo count($movies); ?> title(s)</p>

    <?php if ($error):   ?><div class="alert alert-error"  ><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (isModerator()): ?>
    <!-- ADD MOVIE FORM -->
    <div class="card" style="margin-bottom:28px">
        <div class="section-title" style="margin-bottom:20px; font-size:1.2rem">Add New Movie</div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="token"  value="<?php echo htmlspecialchars(csrf()); ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Movie title" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Genre</label>
                    <input type="text" name="genre" class="form-control" placeholder="Sci-Fi, Action…">
                </div>
                <div class="form-group">
                    <label class="form-label">Duration (min)</label>
                    <input type="number" name="duration" class="form-control" placeholder="120" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Release Year</label>
                    <input type="number" name="release_year" class="form-control" value="<?php echo date('Y'); ?>" min="1900">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Short synopsis…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Movie</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- MOVIE TABLE -->
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Genre</th>
                        <th>Duration</th>
                        <th>Year</th>
                        <th>Screenings</th>
                        <th>Reservations</th>
                        <?php if (isModerator()): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $i => $m): ?>
                    <tr style="animation:fadeUp .3s ease <?php echo $i*.04; ?>s both">
                        <td style="color:var(--muted)"><?php echo (int)$m['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($m['title']); ?></strong></td>
                        <td style="color:var(--muted)"><?php echo htmlspecialchars($m['genre']); ?></td>
                        <td><?php echo (int)$m['duration']; ?> min</td>
                        <td><?php echo htmlspecialchars($m['release_year']); ?></td>
                        <td><?php echo (int)$m['screening_count']; ?></td>
                        <td><?php echo (int)$m['reservation_count']; ?></td>
                        <?php if (isModerator()): ?>
                        <td>
                            <div style="display:flex; gap:6px; flex-wrap:wrap">
                                <!-- EDIT form (inline) -->
                                <button class="btn btn-outline btn-sm"
                                        onclick="toggleEdit(<?php echo (int)$m['id']; ?>)">Edit</button>
                                <?php if (isAdmin()): ?>
                                <form method="POST" onsubmit="return confirm('Delete this movie and all its screenings?')">
                                    <input type="hidden" name="action"   value="delete">
                                    <input type="hidden" name="movie_id" value="<?php echo (int)$m['id']; ?>">
                                    <input type="hidden" name="token"    value="<?php echo htmlspecialchars(csrf()); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <!-- EDIT INLINE FORM (hidden) -->
                            <div id="edit-<?php echo (int)$m['id']; ?>" style="display:none; margin-top:12px">
                                <form method="POST">
                                    <input type="hidden" name="action"   value="update">
                                    <input type="hidden" name="movie_id" value="<?php echo (int)$m['id']; ?>">
                                    <input type="hidden" name="token"    value="<?php echo htmlspecialchars(csrf()); ?>">
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px">
                                        <input type="text"   name="title"        class="form-control" value="<?php echo htmlspecialchars($m['title']); ?>" required>
                                        <input type="text"   name="genre"        class="form-control" value="<?php echo htmlspecialchars($m['genre']); ?>">
                                        <input type="number" name="duration"     class="form-control" value="<?php echo (int)$m['duration']; ?>" min="1">
                                        <input type="number" name="release_year" class="form-control" value="<?php echo (int)$m['release_year']; ?>">
                                    </div>
                                    <textarea name="description" class="form-control" style="margin-bottom:8px"><?php echo htmlspecialchars($m['description']); ?></textarea>
                                    <button type="submit" class="btn btn-success btn-sm">Save</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleEdit(<?php echo (int)$m['id']; ?>)">Cancel</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($movies)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--muted); padding:30px">No movies yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<script>
function toggleEdit(id) {
    const el = document.getElementById('edit-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
