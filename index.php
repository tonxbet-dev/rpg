<?php
ob_start(); 
session_start();

// --- DB CONFIG ---
$db_host = 'localhost';
$db_user = 'h193663_manager'; 
$db_pass = 'karina95!';    
$db_name = 'h193663_rpg';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$conn->set_charset("utf8");

require_once 'stats.php';

// ... (Authentication logic remains the same) ...
if (isset($_POST['login'])) {
    $user = $conn->real_escape_string($_POST['username']);
    $res = $conn->query("SELECT * FROM users WHERE username = '$user'");
    if ($row = $res->fetch_assoc()) {
        if (password_verify($_POST['password'], $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            header("Location: index.php"); exit;
        }
    }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

$currentUser = null;
$baseStats = [];
$statDefinitions = [];
if (isset($_SESSION['user_id'])) {
    $statsSnapshot = buildUserStatsSnapshot($_SESSION['user_id']);
    $currentUser = $statsSnapshot['user'];
    $baseStats = $statsSnapshot['base'];
    $statDefinitions = getStatDefinitions();
    $currentUser = array_merge($currentUser, $statsSnapshot['derived']);
    $currentUser['total_str'] = $statsSnapshot['derived']['total_str'];
    $currentUser['total_def'] = $statsSnapshot['derived']['total_def'];
    $currentUser['total_max_hp'] = $statsSnapshot['derived']['total_max_hp'];
    syncUserDerivedStats($currentUser['id'], $statsSnapshot['derived'], (int)$currentUser['hp']);
}

$page = $_GET['page'] ?? 'home';

// --- ROUTING ---
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>RPG Old School</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>window.onload = function() { lucide.createIcons(); };</script>
</head>
<body>

<div class="container">
    <!-- Header code ... -->
    <div class="header">
        <div class="logo">⚔️ MEDIEVAL RPG</div>
        <?php if ($currentUser): ?>
            <div class="user-status">
                <?= $currentUser['username'] ?> [Lvl <?= $currentUser['level'] ?>] 
                <span style="color:#e74c3c;">❤️ <?= $currentUser['hp'] ?>/<?= $currentUser['total_max_hp'] ?></span>
                <span style="color:var(--color-gold);">💰 <?= $currentUser['money'] ?></span>
                <a href="?logout=1" style="color:#bdc3c7; margin-left:15px; font-size:12px;">(Выход)</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="game-layout">
        <div class="sidebar-left">
            <?php if ($currentUser): ?>
                <a href="?page=home" class="menu-btn"><i data-lucide="info"></i> Инфо</a>
                <a href="?page=player" class="menu-btn"><i data-lucide="package"></i> Герой</a>
                <a href="?page=battle" class="menu-btn"><i data-lucide="swords"></i> Поединок</a>
                <a href="?page=tournament" class="menu-btn"><i data-lucide="flag"></i> Турнир</a>
                <a href="?page=shop" class="menu-btn"><i data-lucide="shopping-bag"></i> Магазин</a>
            <?php endif; ?>
        </div>

        <div class="main-content">
            <?php if (!$currentUser): ?>
                <!-- Login form ... -->
                <div class="auth-box" style="text-align:center; margin-top:50px;">
                    <h3>Вход</h3>
                    <form method="post"><input type="text" name="username"><br><input type="password" name="password"><br><button type="submit" name="login">Войти</button></form>
                </div>
            <?php elseif ($page == 'home'): ?>
                <h2>Центральная Площадь</h2>
            <?php elseif ($page == 'player'): ?>
                <?php include 'player.php'; ?>
            <?php elseif ($page == 'battle'): ?>
                <?php include 'battle.php'; ?>
            <?php elseif ($page == 'tournament'): ?>
                <?php include 'tournament.php'; ?>
            <?php elseif ($page == 'shop'): ?>
                <?php include 'shop.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-right"></div>
    </div>
</div>
</body>
</html>