<?php
/**
 * Страница игрока: Профиль + Инвентарь (Кукла)
 * Подключается внутри index.php
 */

// --- ЛОГИКА ИНВЕНТАРЯ ---
if (isset($_GET['action']) && isset($_GET['inv_id'])) {
    $invId = (int)$_GET['inv_id'];
    if ($_GET['action'] == 'equip') {
        $sqlSlot = "SELECT i.slot FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.id = $invId";
        $itemRow = $conn->query($sqlSlot)->fetch_assoc();
        if ($itemRow) {
            $slot = $itemRow['slot'];
            $conn->query("UPDATE inventory inv JOIN items i ON inv.item_id = i.id SET inv.is_equipped = 0 WHERE inv.user_id = {$currentUser['id']} AND i.slot = '$slot'");
            $conn->query("UPDATE inventory SET is_equipped = 1 WHERE id = $invId AND user_id = {$currentUser['id']}");
        }
    } elseif ($_GET['action'] == 'unequip') {
        $conn->query("UPDATE inventory SET is_equipped = 0 WHERE id = $invId AND user_id = {$currentUser['id']}");
    }
    header("Location: index.php?page=player"); exit;
}

function renderSlot($userId, $slotName, $iconName, $title) {
    global $conn;
    $res = $conn->query("SELECT i.*, inv.id as inv_id FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = $userId AND inv.is_equipped = 1 AND i.slot = '$slotName' LIMIT 1");
    $item = $res->fetch_assoc();
    $filledClass = $item ? 'filled' : '';
    echo "<div class='inv-slot $filledClass' title='$title'>";
    if ($item) {
        echo "<i data-lucide='$iconName'></i>"; 
        echo "<a href='?page=player&action=unequip&inv_id={$item['inv_id']}' class='action-btn'>x</a>";
    } else {
        echo "<i data-lucide='$iconName'></i>"; 
    }
    echo "</div>";
}
?>

<!-- ИНТЕРФЕЙС ПРОФИЛЯ -->
<div class="char-inventory-wrapper">
    <!-- Header -->
    <div class="inv-header">
        <div style="display:flex; gap:10px;">
            <span style="font-weight:bold;"><?= $currentUser['username'] ?></span>
            <span>[<?= $currentUser['level'] ?>]</span>
        </div>
        <div style="font-size:11px;">
            <span>❤️ <?= $currentUser['hp'] ?>/<?= $currentUser['total_max_hp'] ?></span>
            <span style="margin-left:10px; color:#3498db;">💧 100%</span>
        </div>
        <div><a href="?page=shop" style="text-decoration:underline; color:#f1c40f;">В Магазин &raquo;</a></div>
    </div>

    <!-- Основной контейнер куклы -->
    <div class="doll-container">
        
        <div class="doll-layout-grid">

            <!-- 0. LEFT STATS COLUMN -->
            <div class="doll-stats-col-left">
                <div style="text-align:center; font-weight:bold; margin-bottom:5px; border-bottom:1px solid #5c4a3a;">Характеристики</div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="heart" color="red"></i> Здоровье</span><span class="doll-stat-val"><?= $currentUser['total_max_hp'] ?></span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="biceps-flexed" color="red"></i> Сила</span><span class="doll-stat-val"><?= $currentUser['total_str'] ?></span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="zap" color="#e67e22"></i> Ловкость</span><span class="doll-stat-val">15</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="activity" color="green"></i> Выносливость</span><span class="doll-stat-val">50</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="fox" color="purple"></i> Хитрость</span><span class="doll-stat-val">25</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="eye" color="blue"></i> Внимательность</span><span class="doll-stat-val">10</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="smile" color="orange"></i> Харизма</span><span class="doll-stat-val">5</span></div>
                <div style="margin-top:auto; padding-top:5px; border-top:1px solid #5c4a3a;">
                    <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="shield" color="blue"></i> Броня</span><span class="doll-stat-val"><?= $currentUser['total_def'] ?></span></div>
                    <div class="doll-stat-row"><span class="doll-stat-label">Урон</span><span class="doll-stat-val">12-18</span></div>
                </div>
            </div>

            <!-- 1. TOP ROW SPLIT -->
            <!-- 1a. Top Left Corner -->
            <div class="doll-top-left">
                <?php renderSlot($currentUser['id'], 'earrings', 'ear', 'Серьги'); ?>
            </div>
            
            <!-- 1b. Top Center (4 slots) -->
            <div class="doll-top-center">
                <?php renderSlot($currentUser['id'], 'necklace', 'gem', 'Ожерелье'); ?>
                <?php renderSlot($currentUser['id'], 'helmet', 'crown', 'Шлем'); ?>
                <?php renderSlot($currentUser['id'], 'amulet', 'sun', 'Амулет'); ?>
                <div class="inv-slot" title="Доп. слот"><i data-lucide="sparkles"></i></div>
            </div>
            
            <!-- 1c. Top Right Corner -->
            <div class="doll-top-right">
                <div class="inv-slot" title="Доп. слот"><i data-lucide="sparkles"></i></div>
            </div>

            <!-- 2. LEFT COLUMN (5 slots) -->
            <div class="doll-left-col">
                <?php renderSlot($currentUser['id'], 'weapon', 'sword', 'Оружие'); ?>
                <div class="inv-slot" title="Наручи"><i data-lucide="circle-dashed"></i></div>
                <div class="inv-slot" title="Кольцо"><i data-lucide="circle-dot"></i></div>
                <div class="inv-slot" title="Кольцо"><i data-lucide="circle-dot"></i></div>
                <div class="inv-slot" title="Доп. слот лево"><i data-lucide="circle-dot"></i></div>
            </div>

            <!-- 3. CENTER AVATAR -->
            <div class="char-avatar-box">
                <i data-lucide="user" size="80" color="#8b7d6b" style="opacity:0.5;"></i>
                <div class="char-info-overlay">
                    <b><?= $currentUser['username'] ?></b><br>
                    Уровень: <?= $currentUser['level'] ?>
                </div>
            </div>

            <!-- 4. RIGHT COLUMN (5 slots) -->
            <div class="doll-right-col">
                <?php renderSlot($currentUser['id'], 'armor', 'shirt', 'Броня'); ?>
                <div class="inv-slot" title="Перчатки"><i data-lucide="hand"></i></div>
                <div class="inv-slot" title="Плащ"><i data-lucide="wind"></i></div>
                <div class="inv-slot" title="Кольцо"><i data-lucide="circle-dot"></i></div>
                <div class="inv-slot" title="Доп. слот право"><i data-lucide="circle-dot"></i></div>
            </div>

            <!-- 5. RIGHT STATS COLUMN -->
            <div class="doll-stats-col-right">
                <div style="text-align:center; font-weight:bold; margin-bottom:5px; border-bottom:1px solid #5c4a3a;">Статистика</div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="trophy" color="green"></i> Побед</span><span class="doll-stat-val">149</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label"><i data-lucide="skull" color="red"></i> Поражений</span><span class="doll-stat-val">17</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label">Ничьих</span><span class="doll-stat-val">0</span></div>
                <div style="margin-top:10px; border-top:1px dashed #999;"></div>
                <div class="doll-stat-row"><span class="doll-stat-label">Опыт</span><span class="doll-stat-val">121k</span></div>
                <div class="doll-stat-row"><span class="doll-stat-label">Доблесть</span><span class="doll-stat-val">500</span></div>
            </div>

            <!-- 6. BOTTOM ROW SPLIT -->
            <!-- 6a. Bottom Left Corner -->
            <div class="doll-btm-left">
                <div class="inv-slot" title="Пояс"><i data-lucide="minus"></i></div>
            </div>
            
            <!-- 6b. Bottom Center (4 slots) -->
            <div class="doll-btm-center">
                <div class="inv-slot" title="Поножи"><i data-lucide="columns-2"></i></div>
                <?php renderSlot($currentUser['id'], 'boots', 'footprints', 'Сапоги'); ?>
                <div class="inv-slot" title="Слот"><i data-lucide="sparkles"></i></div>
                <div class="inv-slot" title="Слот"><i data-lucide="sparkles"></i></div>
            </div>
            
            <!-- 6c. Bottom Right Corner -->
            <div class="doll-btm-right">
                <div class="inv-slot" title="Слот"><i data-lucide="sparkles"></i></div>
            </div>

        </div>

        <div class="inv-actions">
             <button class="inv-action-btn">Снять всё</button>
             <button class="inv-action-btn">Сохранить</button>
        </div>
    </div>

    <!-- РЮКЗАК -->
    <div class="backpack-area">
        <div style="font-size:12px; font-weight:bold; margin-bottom:5px;">🎒 Рюкзак</div>
        <div class="backpack-grid">
            <?php
            $sql = "SELECT inv.id as inv_id, i.* FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = {$currentUser['id']} AND inv.is_equipped = 0";
            $res = $conn->query($sql);
            if ($res->num_rows > 0):
                while($item = $res->fetch_assoc()):
            ?>
                <a href="?page=player&action=equip&inv_id=<?= $item['inv_id'] ?>" class="backpack-slot" title="<?= $item['name'] ?>">
                    <?php 
                    $ico = 'package';
                    if($item['slot']=='weapon') $ico='sword';
                    elseif($item['slot']=='armor') $ico='shirt';
                    elseif($item['slot']=='helmet') $ico='crown';
                    elseif($item['slot']=='boots') $ico='footprints';
                    echo "<i data-lucide='$ico'></i>";
                    ?>
                </a>
            <?php endwhile; else: ?>
                <span style="color:#777; font-size:11px; padding:5px;">Пусто</span>
            <?php endif; ?>
        </div>
    </div>
</div>