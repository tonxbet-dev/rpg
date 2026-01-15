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

if (isset($_POST['upgrade_stat'])) {
    $statKey = $_POST['stat_key'] ?? '';
    $statAmount = $_POST['stat_amount'] ?? 1;
    $result = applyStatUpgrade($currentUser['id'], $statKey, $statAmount);
    $_SESSION['stat_upgrade_message'] = $result['message'];
    $_SESSION['stat_upgrade_success'] = $result['success'] ? 1 : 0;
    header("Location: index.php?page=player"); exit;
}

$statUpgradeMessage = $_SESSION['stat_upgrade_message'] ?? null;
$statUpgradeSuccess = $_SESSION['stat_upgrade_success'] ?? null;
unset($_SESSION['stat_upgrade_message'], $_SESSION['stat_upgrade_success']);

if (empty($statDefinitions)) {
    $statDefinitions = getStatDefinitions();
}
if (empty($baseStats)) {
    $baseStats = getBaseStatsFromUser($currentUser);
}

function renderSlot($userId, $slotName, $iconName, $title, $slotNumber) {
    global $conn;
    $res = $conn->query("SELECT i.*, inv.id as inv_id FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = $userId AND inv.is_equipped = 1 AND i.slot = '$slotName' LIMIT 1");
    $item = $res->fetch_assoc();
    $filledClass = $item ? 'filled' : '';
    echo "<div class='inv-slot $filledClass' title='$title'>";
    echo "<span class='slot-number'>$slotNumber</span>";
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
<div class="char-inventory-wrapper player-profile">
    <div class="profile-topbar">
        <div class="profile-level-badge">
            <div class="profile-level-number"><?= $currentUser['level'] ?></div>
            <div class="profile-level-label">уровень</div>
        </div>
        <div class="profile-bars">
            <div class="profile-bar">
                <div class="profile-bar-title">Жизни</div>
                <div class="profile-bar-track">
                    <div class="profile-bar-fill" style="width: <?= min(100, ($currentUser['hp'] / max(1, $currentUser['total_max_hp'])) * 100) ?>%"></div>
                </div>
                <div class="profile-bar-value"><?= $currentUser['hp'] ?>/<?= $currentUser['total_max_hp'] ?></div>
            </div>
        </div>
        <div class="profile-currency">
            <div class="currency-chip">💰 <?= $currentUser['money'] ?></div>
            <div class="currency-chip">⭐ <?= $currentUser['exp'] ?></div>
        </div>
    </div>

    <?php if ($statUpgradeMessage): ?>
        <div class="stat-upgrade-message <?= $statUpgradeSuccess ? 'success' : 'error' ?>">
            <?= htmlspecialchars($statUpgradeMessage) ?>
        </div>
    <?php endif; ?>

    <div class="profile-title">ГЕРОЙ</div>

    <div class="doll-container profile-doll">
        <div class="doll-layout-grid">
            <div class="doll-stats-col-left">
                <div class="stats-title">Характеристики</div>
                <?php foreach ($statDefinitions as $statKey => $statData): ?>
                    <div class="stat-row">
                        <span class="stat-label">
                            <i data-lucide="<?= $statData['icon'] ?>"></i>
                            <?= $statData['label'] ?>
                        </span>
                        <span class="stat-value"><?= $baseStats[$statKey] ?></span>
                        <button class="stat-plus-btn"
                            type="button"
                            data-stat-key="<?= $statKey ?>"
                            data-stat-label="<?= htmlspecialchars($statData['label'], ENT_QUOTES) ?>"
                            data-stat-desc="<?= htmlspecialchars($statData['description'], ENT_QUOTES) ?>"
                            data-stat-current="<?= $baseStats[$statKey] ?>"
                            data-stat-multiplier="<?= $statData['cost_multiplier'] ?>">
                            +
                        </button>
                    </div>
                <?php endforeach; ?>
                <div class="stats-divider"></div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="swords"></i> Урон</span>
                    <span class="stat-value"><?= $currentUser['damage_min'] ?>-<?= $currentUser['damage_max'] ?></span>
                </div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="shield"></i> Броня</span>
                    <span class="stat-value"><?= $currentUser['armor'] ?></span>
                </div>
            </div>

            <div class="doll-top-left">
                <?php renderSlot($currentUser['id'], 'earrings', 'ear', 'Серьги', 1); ?>
            </div>
            
            <div class="doll-top-center">
                <?php renderSlot($currentUser['id'], 'necklace', 'gem', 'Ожерелье', 2); ?>
                <?php renderSlot($currentUser['id'], 'helmet', 'crown', 'Шлем', 3); ?>
                <?php renderSlot($currentUser['id'], 'amulet', 'sun', 'Амулет', 4); ?>
                <div class="inv-slot" title="Доп. слот"><span class="slot-number">5</span><i data-lucide="sparkles"></i></div>
            </div>
            
            <div class="doll-top-right">
                <div class="inv-slot" title="Доп. слот"><span class="slot-number">6</span><i data-lucide="sparkles"></i></div>
            </div>

            <div class="doll-left-col">
                <?php renderSlot($currentUser['id'], 'weapon', 'sword', 'Оружие', 7); ?>
                <div class="inv-slot" title="Наручи"><span class="slot-number">8</span><i data-lucide="circle-dashed"></i></div>
                <div class="inv-slot" title="Кольцо"><span class="slot-number">9</span><i data-lucide="circle-dot"></i></div>
                <div class="inv-slot" title="Кольцо"><span class="slot-number">10</span><i data-lucide="circle-dot"></i></div>
                <div class="inv-slot" title="Доп. слот лево"><span class="slot-number">11</span><i data-lucide="circle-dot"></i></div>
            </div>

            <div class="char-avatar-box">
                <i data-lucide="user" size="90" color="#8dd7ff" style="opacity:0.65;"></i>
                <div class="char-info-overlay">
                    <b><?= $currentUser['username'] ?></b><br>
                    Уровень: <?= $currentUser['level'] ?>
                </div>
            </div>

            <div class="doll-right-col">
                <?php renderSlot($currentUser['id'], 'armor', 'shirt', 'Броня', 12); ?>
                <div class="inv-slot" title="Перчатки"><span class="slot-number">13</span><i data-lucide="hand"></i></div>
                <div class="inv-slot" title="Плащ"><span class="slot-number">14</span><i data-lucide="wind"></i></div>
                <div class="inv-slot" title="Кольцо"><span class="slot-number">15</span><i data-lucide="circle-dot"></i></div>
                <div class="inv-slot" title="Доп. слот право"><span class="slot-number">16</span><i data-lucide="circle-dot"></i></div>
            </div>

            <div class="doll-stats-col-right">
                <div class="stats-title">Статистика</div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="trophy"></i> Побед</span>
                    <span class="stat-value"><?= (int)($currentUser['wins'] ?? 0) ?></span>
                </div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="skull"></i> Поражений</span>
                    <span class="stat-value"><?= (int)($currentUser['losses'] ?? 0) ?></span>
                </div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="minus"></i> Ничьих</span>
                    <span class="stat-value"><?= (int)($currentUser['draws'] ?? 0) ?></span>
                </div>
                <div class="stats-divider"></div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="zap"></i> Шанс удара</span>
                    <span class="stat-value"><?= $currentUser['hit_chance'] ?>%</span>
                </div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="sparkles"></i> Крит</span>
                    <span class="stat-value"><?= $currentUser['crit_chance'] ?>%</span>
                </div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="eye"></i> Уклонение</span>
                    <span class="stat-value"><?= $currentUser['dodge_chance'] ?>%</span>
                </div>
                <div class="stat-row compact">
                    <span class="stat-label"><i data-lucide="shopping-bag"></i> Бонус золота</span>
                    <span class="stat-value"><?= $currentUser['gold_bonus'] ?>%</span>
                </div>
            </div>

            <div class="doll-btm-left">
                <div class="inv-slot" title="Пояс"><span class="slot-number">17</span><i data-lucide="minus"></i></div>
            </div>
            
            <div class="doll-btm-center">
                <div class="inv-slot" title="Поножи"><span class="slot-number">18</span><i data-lucide="columns-2"></i></div>
                <?php renderSlot($currentUser['id'], 'boots', 'footprints', 'Сапоги', 19); ?>
                <div class="inv-slot" title="Слот"><span class="slot-number">20</span><i data-lucide="sparkles"></i></div>
                <div class="inv-slot" title="Слот"><span class="slot-number">21</span><i data-lucide="sparkles"></i></div>
            </div>
            
            <div class="doll-btm-right">
                <div class="inv-slot" title="Слот"><span class="slot-number">22</span><i data-lucide="sparkles"></i></div>
            </div>
        </div>

        <div class="inv-actions">
             <button class="inv-action-btn">Снять всё</button>
             <button class="inv-action-btn">Сохранить</button>
        </div>
    </div>

    <div class="backpack-area">
        <div class="backpack-title">🎒 Рюкзак</div>
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
                <span class="backpack-empty">Пусто</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="stat-upgrade-modal" id="stat-upgrade-modal" aria-hidden="true">
    <div class="stat-upgrade-overlay" data-modal-close></div>
    <div class="stat-upgrade-card" role="dialog" aria-modal="true">
        <button class="stat-upgrade-close" type="button" data-modal-close>×</button>
        <div class="stat-upgrade-title" id="stat-modal-title"></div>
        <div class="stat-upgrade-desc" id="stat-modal-desc"></div>
        <div class="stat-upgrade-current">Текущее значение: <span id="stat-modal-current"></span></div>
        <div class="stat-upgrade-controls">
            <button type="button" class="stat-qty-btn" data-qty="-1">-</button>
            <span class="stat-qty-value" id="stat-modal-qty">1</span>
            <button type="button" class="stat-qty-btn" data-qty="1">+</button>
        </div>
        <div class="stat-upgrade-cost">Стоимость: <span id="stat-modal-cost"></span> 💰</div>
        <form method="post" class="stat-upgrade-form">
            <input type="hidden" name="upgrade_stat" value="1">
            <input type="hidden" name="stat_key" id="stat-modal-key">
            <input type="hidden" name="stat_amount" id="stat-modal-amount">
            <button type="submit" class="stat-upgrade-confirm">Увеличить</button>
        </form>
    </div>
</div>

<script>
    const STAT_COST_BASE = <?= STAT_COST_BASE ?>;
    const STAT_COST_GROWTH = <?= STAT_COST_GROWTH ?>;
    const modal = document.getElementById('stat-upgrade-modal');
    const modalTitle = document.getElementById('stat-modal-title');
    const modalDesc = document.getElementById('stat-modal-desc');
    const modalCurrent = document.getElementById('stat-modal-current');
    const modalQty = document.getElementById('stat-modal-qty');
    const modalCost = document.getElementById('stat-modal-cost');
    const modalKey = document.getElementById('stat-modal-key');
    const modalAmount = document.getElementById('stat-modal-amount');
    let modalBaseValue = 1;
    let modalMultiplier = 1;

    function calculateUpgradeCost(currentValue, amount, multiplier) {
        let total = 0;
        for (let i = 0; i < amount; i++) {
            total += STAT_COST_BASE + (currentValue + i) * STAT_COST_GROWTH;
        }
        return Math.ceil(total * multiplier);
    }

    function updateModalCost(amount) {
        const cost = calculateUpgradeCost(modalBaseValue, amount, modalMultiplier);
        modalQty.textContent = amount;
        modalAmount.value = amount;
        modalCost.textContent = cost;
    }

    function openModal(button) {
        modalBaseValue = parseInt(button.dataset.statCurrent, 10) || 1;
        modalMultiplier = parseFloat(button.dataset.statMultiplier) || 1;
        modalTitle.textContent = button.dataset.statLabel;
        modalDesc.textContent = button.dataset.statDesc;
        modalCurrent.textContent = modalBaseValue;
        modalKey.value = button.dataset.statKey;
        updateModalCost(1);
        modal.classList.add('open');
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('.stat-plus-btn').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn));
    });

    document.querySelectorAll('[data-modal-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.querySelectorAll('.stat-qty-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            let amount = parseInt(modalAmount.value, 10) || 1;
            amount += parseInt(btn.dataset.qty, 10);
            if (amount < 1) amount = 1;
            if (amount > 50) amount = 50;
            updateModalCost(amount);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });
</script>