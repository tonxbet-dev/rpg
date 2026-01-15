<?php
/**
 * BATTLE SYSTEM 1x1 (PvE / PvP)
 * С исправленным таймером и пагинацией
 */

$turnDuration = 15; // Время на ход в секундах
require_once 'battle_phrases.php';

$zonesLabel = [1 => 'Голова', 2 => 'Корпус', 3 => 'Живот', 4 => 'Пах', 5 => 'Ноги'];
$zonesAccusative = [1 => 'Голову', 2 => 'Корпус', 3 => 'Живот', 4 => 'Пах', 5 => 'Ноги'];

function formatZoneList($zones, $names) {
    $labels = [];
    foreach ($zones as $zone) {
        if (isset($names[$zone])) {
            $labels[] = $names[$zone];
        }
    }
    $count = count($labels);
    if ($count === 0) return 'ничего';
    if ($count === 1) return $labels[0];
    if ($count === 2) return $labels[0] . ' и ' . $labels[1];
    return implode(', ', array_slice($labels, 0, -1)) . ' и ' . $labels[$count - 1];
}

function normalizeDefenseZones($input, $zoneCount = 5, $required = 2) {
    $zones = [];
    if (is_array($input)) {
        foreach ($input as $zone) {
            $zone = (int)$zone;
            if ($zone >= 1 && $zone <= $zoneCount) {
                $zones[] = $zone;
            }
        }
    }
    $zones = array_values(array_unique($zones));
    if (count($zones) > $required) {
        $zones = array_slice($zones, 0, $required);
    }
    while (count($zones) < $required) {
        $candidate = rand(1, $zoneCount);
        if (!in_array($candidate, $zones, true)) {
            $zones[] = $candidate;
        }
    }
    return $zones;
}

function calculateSelfDamage($attacker) {
    $min = max(1, (int)floor($attacker['damage_min'] * 0.4));
    $max = max($min, (int)floor($attacker['damage_max'] * 0.6));
    return rand($min, $max);
}

function getEffectiveHitChance($attacker, $defender) {
    $chance = (int)$attacker['hit_chance'] - (int)$defender['dodge_chance'];
    return max(5, min(95, $chance));
}

function getEffectiveCritChance($attacker, $defender) {
    $chance = (int)$attacker['crit_chance'] - (int)$defender['crit_resist'];
    return max(0, min(60, $chance));
}

function calculateBattleDamage($attacker, $defender) {
    $raw = rand((int)$attacker['damage_min'], (int)$attacker['damage_max']);
    $mitigation = (int)floor((int)$defender['total_def'] * 0.6);
    return max(1, $raw - $mitigation);
}

// Инициализация боя
if (!isset($_SESSION['battle'])) {
    $botLevel = max(1, (int)$currentUser['level']);
    $botStats = buildBotCombatStats($botLevel, 'brutal');
    $_SESSION['battle'] = [
        'id' => uniqid(),
        'turn' => 1,
        'active' => true,
        'log' => [],
        // Устанавливаем время окончания ПЕРВОГО хода
        'turn_deadline' => time() + $turnDuration, 
        'enemy' => [
            'name' => 'Разбойник',
            'level' => $botLevel,
            'hp' => $botStats['total_max_hp'],
            'max_hp' => $botStats['total_max_hp'],
            'str' => $botStats['total_str'],
            'def' => $botStats['total_def'],
            'total_def' => $botStats['total_def'],
            'damage_min' => $botStats['damage_min'],
            'damage_max' => $botStats['damage_max'],
            'hit_chance' => $botStats['hit_chance'],
            'crit_chance' => $botStats['crit_chance'],
            'crit_resist' => $botStats['crit_resist'],
            'dodge_chance' => $botStats['dodge_chance'],
            'gold_bonus' => $botStats['gold_bonus'],
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
        $playerDefZones = [];
        $logHeader = "<div class='log-line'><span style='color:#7f8c8d'>⌛ Время истекло! Пропуск хода.</span></div>";
    } else {
        $playerAtk = $_POST['attack_zone'] ?? rand(1, 5);
        $playerAtk = max(1, min(5, (int)$playerAtk));
        $playerDefZones = normalizeDefenseZones($_POST['def_zones'] ?? [], 5, 2);
        $logHeader = "";
    }

    // 2. Выбор зон бота
    $botAtk = rand(1, 5);
    $botDefZones = normalizeDefenseZones([], 5, 2);

    $zonesName = [0=>'воздух'] + $zonesAccusative;
    $turnContent = "";

    // Логика ударов
    $botDefZonesLabel = formatZoneList($botDefZones, $zonesAccusative);
    $playerDefZonesLabel = formatZoneList($playerDefZones, $zonesAccusative);

    if ($playerAtk > 0) {
        $context = [
            'attacker' => 'Вы',
            'defender' => $enemy['name'],
            'zone' => $zonesName[$playerAtk],
            'defZones' => $botDefZonesLabel
        ];
        if (in_array($playerAtk, $botDefZones, true)) {
            $turnContent .= "<div class='log-line'>" . getBattlePhrase('defense', 'block', $context) . "</div>";
        } else {
            if (rand(1, 100) <= 4) {
                $selfDmg = calculateSelfDamage($currentUser);
                $currentUser['hp'] = max(0, $currentUser['hp'] - $selfDmg);
                $conn->query("UPDATE users SET hp = {$currentUser['hp']} WHERE id = {$currentUser['id']}");
                $context['selfDamage'] = "<span class='log-dmg'>-$selfDmg</span>";
                $turnContent .= "<div class='log-line'>" . getBattlePhrase('attack', 'self', $context) . "</div>";
            } else {
                $hitChance = getEffectiveHitChance($currentUser, $enemy);
                if (rand(1, 100) > $hitChance) {
                    $turnContent .= "<div class='log-line'>" . getBattlePhrase('defense', 'dodge', $context) . "</div>";
                } else {
                    $defRoll = rand(1, 100);
                    $defType = $defRoll <= 12 ? 'panic' : ($defRoll <= 32 ? 'dance' : 'fail');
                    $turnContent .= "<div class='log-line'>" . getBattlePhrase('defense', $defType, $context) . "</div>";

                    $dmg = calculateBattleDamage($currentUser, $enemy);
                    $glance = rand(1, 100) <= 12;
                    if ($glance) {
                        $dmg = max(1, (int)floor($dmg * 0.6));
                    }

                    $critChance = getEffectiveCritChance($currentUser, $enemy);
                    $isCrit = (!$glance && rand(1, 100) <= $critChance);
                    if ($isCrit) {
                        $dmg = (int)floor($dmg * 1.5);
                        $context['damage'] = "<span class='log-dmg'>-$dmg</span>";
                        $turnContent .= "<div class='log-line'>" . getBattlePhrase('attack', 'crit', $context) . "</div>";
                    } else {
                        $context['damage'] = "<span class='log-dmg'>-$dmg</span>";
                        $turnContent .= "<div class='log-line'>" . getBattlePhrase('attack', $glance ? 'glance' : 'hit', $context) . "</div>";
                    }
                    $enemy['hp'] -= $dmg;
                }
            }
        }
    }

    $context = [
        'attacker' => $enemy['name'],
        'defender' => $currentUser['username'],
        'zone' => $zonesName[$botAtk],
        'defZones' => $playerDefZonesLabel
    ];

    if (!empty($playerDefZones) && in_array($botAtk, $playerDefZones, true)) {
        $turnContent .= "<div class='log-line'>" . getBattlePhrase('defense', 'block', $context) . "</div>";
    } else {
        if (rand(1, 100) <= 4) {
            $selfDmg = calculateSelfDamage($enemy);
            $enemy['hp'] = max(0, $enemy['hp'] - $selfDmg);
            $context['selfDamage'] = "<span class='log-dmg'>-$selfDmg</span>";
            $turnContent .= "<div class='log-line'>" . getBattlePhrase('attack', 'self', $context) . "</div>";
        } else {
            $hitChance = getEffectiveHitChance($enemy, $currentUser);
            if (rand(1, 100) > $hitChance) {
                $turnContent .= "<div class='log-line'>" . getBattlePhrase('defense', 'dodge', $context) . "</div>";
            } else {
                $defRoll = rand(1, 100);
                $defType = $defRoll <= 12 ? 'panic' : ($defRoll <= 32 ? 'dance' : 'fail');
                $turnContent .= "<div class='log-line'>" . getBattlePhrase('defense', $defType, $context) . "</div>";

                $botDmg = calculateBattleDamage($enemy, $currentUser);
                $glance = rand(1, 100) <= 12;
                if ($glance) {
                    $botDmg = max(1, (int)floor($botDmg * 0.6));
                }
                $critChance = getEffectiveCritChance($enemy, $currentUser);
                $isCrit = (!$glance && rand(1, 100) <= $critChance);
                if ($isCrit) {
                    $botDmg = (int)floor($botDmg * 1.5);
                    $context['damage'] = "<span class='log-dmg'>-$botDmg</span>";
                    $turnContent .= "<div class='log-line'>" . getBattlePhrase('attack', 'crit', $context) . "</div>";
                } else {
                    $context['damage'] = "<span class='log-dmg'>-$botDmg</span>";
                    $turnContent .= "<div class='log-line'>" . getBattlePhrase('attack', $glance ? 'glance' : 'hit', $context) . "</div>";
                }
                $currentUser['hp'] = max(0, $currentUser['hp'] - $botDmg);
                $conn->query("UPDATE users SET hp = {$currentUser['hp']} WHERE id = {$currentUser['id']}");
            }
        }
    }

    $fullLogEntry = "<div class='log-entry-wrapper'><div class='log-turn-title'>Раунд {$battle['turn']}</div>$logHeader $turnContent";

    // Финал боя
    if ($enemy['hp'] <= 0 && $currentUser['hp'] <= 0) {
        $enemy['hp'] = 0;
        $currentUser['hp'] = 0;
        $battle['active'] = false;
        $fullLogEntry .= "<div style='color:#f1c40f; font-weight:bold; margin-top:5px; font-size:16px;'>⚖️ НИЧЬЯ!</div></div>";
        $conn->query("UPDATE users SET draws = draws + 1 WHERE id = {$currentUser['id']}");
    } elseif ($enemy['hp'] <= 0) {
        $enemy['hp'] = 0;
        $battle['active'] = false;
        $moneyGain = 5 + (int)floor(5 * ($currentUser['gold_bonus'] / 100));
        $fullLogEntry .= "<div style='color:green; font-weight:bold; margin-top:5px; font-size:16px;'>🏆 ПОБЕДА!</div></div>";
        $conn->query("UPDATE users SET exp = exp + 10, money = money + $moneyGain, wins = wins + 1 WHERE id = {$currentUser['id']}");
    } elseif ($currentUser['hp'] <= 0) {
        $currentUser['hp'] = 0;
        $battle['active'] = false;
        $fullLogEntry .= "<div style='color:red; font-weight:bold; margin-top:5px; font-size:16px;'>☠️ ВЫ ПРОИГРАЛИ!</div></div>";
        $conn->query("UPDATE users SET losses = losses + 1 WHERE id = {$currentUser['id']}");
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
        const defBoxes = document.querySelectorAll('input[name="def_zones[]"]');
        defBoxes.forEach((box) => {
            box.addEventListener('change', (event) => {
                const checked = Array.from(defBoxes).filter((item) => item.checked);
                if (checked.length > 2) {
                    event.target.checked = false;
                }
            });
        });
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
                <div>Урон: <?= $currentUser['damage_min'] ?>-<?= $currentUser['damage_max'] ?></div>
                <div>Броня: <?= $currentUser['armor'] ?></div>
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
                <div>Урон: <?= $enemy['damage_min'] ?>-<?= $enemy['damage_max'] ?></div>
                <div>Броня: <?= $enemy['def'] ?></div>
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
                            <label class="zone-option"><input type="radio" name="attack_zone" value="3"> Живот</label>
                            <label class="zone-option"><input type="radio" name="attack_zone" value="4"> Пах</label>
                            <label class="zone-option"><input type="radio" name="attack_zone" value="5"> Ноги</label>
                        </div>
                        <div class="zone-column">
                            <h4>🛡️ Блок</h4>
                            <div class="zone-note">Выберите 2 зоны</div>
                            <label class="zone-option"><input type="checkbox" name="def_zones[]" value="1" checked> Голова</label>
                            <label class="zone-option"><input type="checkbox" name="def_zones[]" value="2" checked> Корпус</label>
                            <label class="zone-option"><input type="checkbox" name="def_zones[]" value="3"> Живот</label>
                            <label class="zone-option"><input type="checkbox" name="def_zones[]" value="4"> Пах</label>
                            <label class="zone-option"><input type="checkbox" name="def_zones[]" value="5"> Ноги</label>
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