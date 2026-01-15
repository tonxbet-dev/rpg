<?php
/**
 * BATTLE SYSTEM 1x1 (PvE / PvP)
 * С исправленным таймером и пагинацией
 */

$turnDuration = 15; // Время на ход в секундах

// Инициализация боя
if (!isset($_SESSION['battle'])) {
    $botMaxHp = 100;
    $_SESSION['battle'] = [
        'id' => uniqid(),
        'turn' => 1,
        'active' => true,
        'log' => [],
        // Устанавливаем время окончания ПЕРВОГО хода
        'turn_deadline' => time() + $turnDuration, 
        'enemy' => [
            'name' => 'Разбойник',
            'level' => 1,
            'hp' => $botMaxHp,
            'max_hp' => $botMaxHp,
            'str' => 10,
            'def' => 5,
            'avatar' => '' 
        ]
    ];
    $conn->query("UPDATE users SET hp = {$currentUser['total_max_hp']} WHERE id = {$currentUser['id']}");
    $currentUser['hp'] = $currentUser['total_max_hp'];
}

$battle = &$_SESSION['battle'];
$enemy = &$battle['enemy'];

// Расчет оставшегося времени
$secondsLeft = $battle['turn_deadline'] - time();
if ($secondsLeft < 0) $secondsLeft = 0;

// --- ОБРАБОТКА ХОДА ---
if (isset($_POST['process_turn']) && $battle['active']) {
    
    // Проверяем, не истекло ли время на сервере
    $isTimeout = ($secondsLeft <= 0) || (isset($_POST['timed_out']) && $_POST['timed_out'] == '1');
    
    // 1. Выбор зон игрока
    if ($isTimeout) {
        $playerAtk = 0; 
        $playerDef = 0; 
        $logHeader = "<span style='color:#7f8c8d'>⌛ Время истекло! Пропуск хода.</span>";
    } else {
        $playerAtk = $_POST['attack_zone'] ?? rand(1, 3);
        $playerDef = $_POST['def_zone'] ?? rand(1, 3);
        $logHeader = "";
    }

    // 2. Выбор зон бота
    $botAtk = rand(1, 3);
    $botDef = rand(1, 3);

    $zonesName = [0=>'воздух', 1=>'голову', 2=>'корпус', 3=>'ноги'];
    $turnContent = "";

    // Логика ударов (как раньше)
    if ($playerAtk > 0) {
        if ($playerAtk == $botDef) {
            $turnContent .= "<div class='log-line'>⚔️ Вы ударили в <b>{$zonesName[$playerAtk]}</b>, но <span class='log-block'>блок</span>.</div>";
        } else {
            $dmg = max(1, ($currentUser['total_str'] * 2) - $enemy['def'] + rand(-2, 2));
            if (rand(1, 100) <= 10) { $dmg = floor($dmg * 1.5); $turnContent .= "<div class='log-line'>⚔️ <span class='log-crit'>КРИТ!</span> Вы пробили <b>{$zonesName[$playerAtk]}</b> на <span class='log-dmg'>-$dmg</span>.</div>"; }
            else { $turnContent .= "<div class='log-line'>⚔️ Вы ударили в <b>{$zonesName[$playerAtk]}</b> на <span class='log-dmg'>-$dmg</span>.</div>"; }
            $enemy['hp'] -= $dmg;
        }
    }

    if ($botAtk == $playerDef && $playerDef > 0) {
        $turnContent .= "<div class='log-line'>🛡️ Враг ударил в <b>{$zonesName[$botAtk]}</b>, но <span class='log-block'>Вы поставили блок</span>.</div>";
    } else {
        $botDmg = max(1, ($enemy['str'] * 2) - $currentUser['total_def'] + rand(-1, 1));
        $currentUser['hp'] -= $botDmg;
        $conn->query("UPDATE users SET hp = {$currentUser['hp']} WHERE id = {$currentUser['id']}");
        $turnContent .= "<div class='log-line'>🩸 Враг ударил в <b>{$zonesName[$botAtk]}</b> на <span class='log-dmg'>-$botDmg</span>.</div>";
    }

    $fullLogEntry = "<div class='log-entry-wrapper'><div class='log-turn-title'>Раунд {$battle['turn']}</div>$logHeader $turnContent";

    // Финал боя
    if ($enemy['hp'] <= 0) {
        $enemy['hp'] = 0;
        $battle['active'] = false;
        $fullLogEntry .= "<div style='color:green; font-weight:bold; margin-top:5px; font-size:16px;'>🏆 ПОБЕДА!</div></div>";
        $conn->query("UPDATE users SET exp = exp + 10, money = money + 5 WHERE id = {$currentUser['id']}");
    } elseif ($currentUser['hp'] <= 0) {
        $currentUser['hp'] = 0;
        $battle['active'] = false;
        $fullLogEntry .= "<div style='color:red; font-weight:bold; margin-top:5px; font-size:16px;'>☠️ ВЫ ПРОИГРАЛИ!</div></div>";
    } else {
        $fullLogEntry .= "</div>";
    }

    array_unshift($battle['log'], $fullLogEntry);
    $battle['turn']++;
    
    // СБРОС ТАЙМЕРА ДЛЯ СЛЕДУЮЩЕГО ХОДА
    if ($battle['active']) {
        $battle['turn_deadline'] = time() + $turnDuration;
    }
    
    header("Location: index.php?page=battle&log_page=1"); exit;
}

// Сброс
if (isset($_POST['reset_battle'])) {
    unset($_SESSION['battle']);
    header("Location: index.php?page=battle"); exit;
}

// Пагинация
$currentPage = isset($_GET['log_page']) ? (int)$_GET['log_page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$totalTurns = count($battle['log']);
$logIndex = $currentPage - 1; 
$currentLogEntry = isset($battle['log'][$logIndex]) ? $battle['log'][$logIndex] : "<div style='color:#999'>Бой начинается...</div>";
?>

<script>
    // Получаем время с сервера PHP
    let timeLeft = <?= $secondsLeft ?>; 
    let timerInterval;
    let isTurnCommitted = false;

    function startTimer() {
        const timerEl = document.getElementById('battle-timer-display');
        if (!timerEl) return;
        
        // Сразу обновляем текст при загрузке
        updateTimerDisplay(timerEl);

        timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                document.getElementById('timed_out_input').value = '1';
                // Автоматически отправляем форму при истечении времени
                if (!isTurnCommitted) commitTurn(); 
            } else {
                timeLeft--;
                updateTimerDisplay(timerEl);
            }
        }, 1000);
    }

    function updateTimerDisplay(el) {
        el.innerText = timeLeft;
        if(timeLeft <= 5) el.style.color = 'red';
        else el.style.color = '#f1c40f';
    }

    function commitTurn() {
        if (isTurnCommitted) return; 
        isTurnCommitted = true;
        document.getElementById('controls-area').style.display = 'none';
        document.getElementById('waiting-area').style.display = 'block';
        setTimeout(() => { document.getElementById('battle-form').submit(); }, 300);
    }

    window.onload = function() {
        if(document.getElementById('battle-timer-display') && <?= $battle['active'] ? 'true' : 'false' ?>) {
            startTimer();
            const form = document.getElementById('battle-form');
            if(form) form.addEventListener('submit', (e) => { e.preventDefault(); commitTurn(); });
        }
    };
</script>

<div class="battle-arena-wrapper">
    <div class="inv-header">
        <div>⚔️ <b>Поединок</b> (Раунд <?= $battle['turn'] ?>)</div>
        <div><a href="?page=home" style="color:#f1c40f">Выйти</a></div>
    </div>

    <!-- БОЙЦЫ -->
    <div class="battle-fighters-row">
        <!-- ИГРОК -->
        <div class="fighter-card">
            <div class="fighter-header"><?= $currentUser['username'] ?> [<?= $currentUser['level'] ?>]</div>
            <div class="fighter-avatar-display"><i data-lucide="user" size="64" color="#555"></i></div>
            <div class="battle-hp-container">
                <div class="battle-hp-fill" style="width: <?= ($currentUser['hp'] / $currentUser['total_max_hp']) * 100 ?>%"></div>
                <div class="battle-hp-text"><?= $currentUser['hp'] ?> / <?= $currentUser['total_max_hp'] ?></div>
            </div>
            <div class="fighter-stats-mini">
                <div>Сила: <?= $currentUser['total_str'] ?></div>
                <div>Защита: <?= $currentUser['total_def'] ?></div>
            </div>
        </div>

        <!-- ПРОТИВНИК -->
        <div class="fighter-card">
            <div class="fighter-header" style="background-color:#7a2e2e;"><?= $enemy['name'] ?> [<?= $enemy['level'] ?>]</div>
            <div class="fighter-avatar-display"><i data-lucide="user-x" size="64" color="#555"></i></div>
            <div class="battle-hp-container">
                <div class="battle-hp-fill" style="width: <?= ($enemy['hp'] / $enemy['max_hp']) * 100 ?>%; background:linear-gradient(to right, #8e44ad, #9b59b6);"></div>
                <div class="battle-hp-text"><?= $enemy['hp'] ?> / <?= $enemy['max_hp'] ?></div>
            </div>
            <div class="fighter-stats-mini">
                <div>Сила: <?= $enemy['str'] ?></div>
                <div>Защита: <?= $enemy['def'] ?></div>
            </div>
        </div>
    </div>

    <!-- УПРАВЛЕНИЕ -->
    <div class="battle-controls-bottom">
        <?php if ($battle['active']): ?>
            <div style="margin-bottom:5px; font-size:12px; color:#aaa;">Время на ход:</div>
            <div id="battle-timer-display" class="battle-timer"><?= $secondsLeft ?></div>

            <form method="post" id="battle-form" style="width:100%">
                <input type="hidden" name="process_turn" value="1">
                <input type="hidden" name="timed_out" id="timed_out_input" value="0">

                <div id="controls-area">
                    <div class="zone-selector">
                        <div class="zone-column">
                            <h4>🎯 Атака</h4>
                            <label class="zone-option"><input type="radio" name="attack_zone" value="1" checked> Голова</label>
                            <label class="zone-option"><input type="radio" name="attack_zone" value="2"> Корпус</label>
                            <label class="zone-option"><input type="radio" name="attack_zone" value="3"> Ноги</label>
                        </div>
                        <div class="zone-column">
                            <h4>🛡️ Блок</h4>
                            <label class="zone-option"><input type="radio" name="def_zone" value="1" checked> Голова</label>
                            <label class="zone-option"><input type="radio" name="def_zone" value="2"> Корпус</label>
                            <label class="zone-option"><input type="radio" name="def_zone" value="3"> Ноги</label>
                        </div>
                    </div>
                    <div style="text-align:center;">
                        <button type="submit" class="battle-btn">⚔️ СДЕЛАТЬ ХОД</button>
                    </div>
                </div>

                <div id="waiting-area" style="display:none; text-align:center; padding:10px;">
                    <i data-lucide="hourglass" class="spin"></i> Обработка хода...
                </div>
            </form>
        <?php else: ?>
            <div style="text-align:center;">
                <h3>Бой завершен</h3>
                <form method="post">
                    <button type="submit" name="reset_battle" class="battle-btn" style="background:#3498db; color:white;">В лобби</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- ЛОГ -->
    <div class="battle-log-container">
        <?= $currentLogEntry ?>
        <?php if ($totalTurns > 1): ?>
            <div class="log-pagination">
                Ход: 
                <?php for ($i = 1; $i <= min(5, $totalTurns); $i++): $activeClass = ($i == $currentPage) ? 'active' : ''; ?>
                    <a href="?page=battle&log_page=<?= $i ?>" class="log-page-link <?= $activeClass ?>"><?= $totalTurns - $i + 1 ?></a> 
                <?php endfor; ?>
                <span style="margin-left:10px;">(Всего: <?= $totalTurns ?>)</span>
            </div>
        <?php endif; ?>
    </div>
</div>