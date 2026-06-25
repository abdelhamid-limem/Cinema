<?php
require_once 'auth.php';
require_once 'DbConnector.php';
include 'header.php';
requireRole('admin');

$db      = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');
$error   = '';
$success = '';

// UPDATE role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_role') {
    if (verifyCsrf()) {
        $uid  = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? '';
        if (in_array($role, ['admin','moderator','client']) && $uid !== (int)$_SESSION['user_id']) {
            $db->prepare('UPDATE users SET role = :r WHERE id = :id')->execute([':r'=>$role,':id'=>$uid]);
            refreshCsrf();
            $success = 'Role updated.';
        }
    }
}

// SOFT DELETE user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCsrf()) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)$_SESSION['user_id']) {
            $db->prepare('UPDATE users SET deleted_at = NOW() WHERE id = :id')->execute([':id'=>$uid]);
            refreshCsrf();
            $success = 'User removed.';
        } else {
            $error = 'You cannot delete yourself.';
        }
    }
}

// READ — users with reservation count (complex query)
$users = $db->query('
    SELECT u.id, u.username, u.email, u.role, u.created_at,
           COUNT(DISTINCT res.id)                                          AS total_reservations,
           COALESCE(SUM(CASE WHEN res.status="confirmed" THEN res.seats * s.price ELSE 0 END), 0) AS total_spent
    FROM users u
    LEFT JOIN reservations res ON res.user_id = u.id
    LEFT JOIN screenings   s   ON res.screening_id = s.id
    WHERE u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY u.created_at DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="page-title">Users</div>
    <p class="page-subtitle"><?php echo count($users); ?> registered user(s)</p>

    <?php if ($error):   ?><div class="alert alert-error"  ><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Reservations</th>
                        <th>Total Spent</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr style="animation:fadeUp .3s ease <?php echo $i*.04; ?>s both">
                        <td style="color:var(--muted)"><?php echo (int)$u['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span style="font-size:.72rem; color:var(--muted)"> (you)</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted); font-size:.85rem"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="status status-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                        <td><?php echo (int)$u['total_reservations']; ?></td>
                        <td style="color:var(--accent)">€<?php echo number_format((float)$u['total_spent'],2); ?></td>
                        <td style="font-size:.8rem; color:var(--muted)"><?php echo substr($u['created_at'],0,10); ?></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center">
                                <!-- Change role -->
                                <form method="POST" style="display:flex; gap:6px; align-items:center">
                                    <input type="hidden" name="action"  value="update_role">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <input type="hidden" name="token"   value="<?php echo htmlspecialchars(csrf()); ?>">
                                    <select name="role" class="form-control" style="width:110px; padding:5px 8px; font-size:.8rem">
                                        <?php foreach (['client','moderator','admin'] as $r): ?>
                                            <option value="<?php echo $r; ?>" <?php echo $u['role']===$r?'selected':''; ?>><?php echo ucfirst($r); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline btn-sm">Set</button>
                                </form>
                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Delete this user?')">
                                    <input type="hidden" name="action"  value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <input type="hidden" name="token"   value="<?php echo htmlspecialchars(csrf()); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                            <?php else: ?>
                                <span style="color:var(--muted); font-size:.8rem">—</span>
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
