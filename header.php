<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<header>
    <div class="logo">CINEMA<span>X</span></div>
    <link rel="stylesheet" href="style.css">

    <nav>   
        <a href="index.php"    class="<?php echo $currentPage === 'index'    ? 'active' : ''; ?>">Home</a>
        <a href="movies.php"   class="<?php echo $currentPage === 'movies'   ? 'active' : ''; ?>">Movies</a>
        <a href="screenings.php" class="<?php echo $currentPage === 'screenings' ? 'active' : ''; ?>">Screenings</a>
        <a href="my_reservations.php" class="<?php echo $currentPage === 'my_reservations' ? 'active' : ''; ?>">My Bookings</a>
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','moderator'])): ?>
            <a href="manage_reservations.php" class="<?php echo $currentPage === 'manage_reservations' ? 'active' : ''; ?>">Manage</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="users.php" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>">Users</a>
        <?php endif; ?>
    </nav>

    <div class="header-right">
        <?php if (isset($_SESSION['username'])): ?>
            <span style="font-size:.82rem; color:var(--muted)"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <?php if (isset($_SESSION['role'])): ?>
                <span class="badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
        <?php endif; ?>
    </div>
</header>
