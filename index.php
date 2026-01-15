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

// --- FUNCTIONS ---
function getUser($id) {
    global $conn;
    return $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();
}

function calculateStats($userId) {
    global $conn;
    $user = getUser($userId);
    $totalStr = $user['strength'];
    $totalDef = $user['defense'];
    $totalHpMax = $user['max_hp']; 

    $res = $conn->query("SELECT i.* FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = $userId AND inv.is_equipped = 1");
    while($item = $res->fetch_assoc()) {
        $totalStr += $item['stat_str'];
        $totalDef += $item['stat_def'];
        $totalHpMax += $item['stat_hp'];
    }
    return ['str' => $totalStr, 'def' => $totalDef, 'max_hp' => $totalHpMax];
}

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
if (isset($_SESSION['user_id'])) {
    $currentUser = getUser($_SESSION['user_id']);
    $stats = calculateStats($_SESSION['user_id']);
    $currentUser['total_str'] = $stats['str'];
    $currentUser['total_def'] = $stats['def'];
    $currentUser['total_max_hp'] = $stats['max_hp'];
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
                <!-- Shop code... -->
            <?php endif; ?>
        </div>
        
        <div class="sidebar-right"></div>
    </div>
</div>
</body>
</html>