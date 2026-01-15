<?php
/**
 * ТУРНИР 10x10 (Стенка на стенку)
 * Подключается в index.php
 */

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

function getTournamentHitChance($attacker, $defender) {
    $chance = (int)$attacker['hit_chance'] - (int)$defender['dodge_chance'];
    return max(5, min(95, $chance));
}

function getTournamentCritChance($attacker, $defender) {
    $chance = (int)$attacker['crit_chance'] - (int)$defender['crit_resist'];
    return max(0, min(60, $chance));
}

function calculateTournamentDamage($attacker, $defender) {
    $raw = rand((int)$attacker['damage_min'], (int)$attacker['damage_max']);
    $mitigation = (int)floor((int)$defender['total_def'] * 0.6);
    return max(1, $raw - $mitigation);
}

// Старт турнира
if (isset($_POST['start_tournament'])) {
    $teamA = [];
    // Игрок
    $teamA[] = [
        'id' => 'u'.$currentUser['id'],
        'name' => $currentUser['username'],
        'hp' => $currentUser['total_max_hp'],
        'max_hp' => $currentUser['total_max_hp'],
        'str' => $currentUser['total_str'],
        'def' => $currentUser['total_def'],
        'damage_min' => $currentUser['damage_min'],
        'damage_max' => $currentUser['damage_max'],
        'total_def' => $currentUser['total_def'],
        'hit_chance' => $currentUser['hit_chance'],
        'crit_chance' => $currentUser['crit_chance'],
        'crit_resist' => $currentUser['crit_resist'],
        'dodge_chance' => $currentUser['dodge_chance'],
        'alive' => true,
        'is_player' => true
    ];
    // 9 ботов союзников
    for($i=1; $i<10; $i++) {
        $allyStats = buildBotCombatStats(max(1, (int)$currentUser['level']), 'balanced');
        $teamA[] = [
            'id' => 'a'.$i,
            'name' => "Бот союзник $i",
            'hp' => $allyStats['total_max_hp'],
            'max_hp' => $allyStats['total_max_hp'],
            'str' => $allyStats['total_str'],
            'def' => $allyStats['total_def'],
            'damage_min' => $allyStats['damage_min'],
            'damage_max' => $allyStats['damage_max'],
            'total_def' => $allyStats['total_def'],
            'hit_chance' => $allyStats['hit_chance'],
            'crit_chance' => $allyStats['crit_chance'],
            'crit_resist' => $allyStats['crit_resist'],
            'dodge_chance' => $allyStats['dodge_chance'],
            'alive' => true,
            'is_player' => false
        ];
    }
    
    $teamB = [];
    // 10 ботов врагов
    for($i=0; $i<10; $i++) {
        $enemyStats = buildBotCombatStats(max(1, (int)$currentUser['level']), 'brutal');
        $teamB[] = [
            'id' => 'b'.$i,
            'name' => "Бот враг $i",
            'hp' => $enemyStats['total_max_hp'],
            'max_hp' => $enemyStats['total_max_hp'],
            'str' => $enemyStats['total_str'],
            'def' => $enemyStats['total_def'],
            'damage_min' => $enemyStats['damage_min'],
            'damage_max' => $enemyStats['damage_max'],
            'total_def' => $enemyStats['total_def'],
            'hit_chance' => $enemyStats['hit_chance'],
            'crit_chance' => $enemyStats['crit_chance'],
            'crit_resist' => $enemyStats['crit_resist'],
            'dodge_chance' => $enemyStats['dodge_chance'],
            'alive' => true,
            'is_player' => false
        ];
    }
    
    $_SESSION['tournament'] = [
        'teamA' => $teamA,
        'teamB' => $teamB,
        'player_target' => 0, // По умолчанию бьем первого
        'log' => [],
        'turn' => 1,
        'active' => true,
        'turn_deadline' => time() + 20 // 20 сек на ход
    ];
    header("Location: index.php?page=tournament"); exit;
}

// Если турнир не начат, показываем кнопку
if (!isset($_SESSION['tournament'])) {
    echo '<div style="text-align:center; padding:50px;">
            <h2>Турнир 10x10</h2>
            <p>Битва стенка на стенку.</p>
            <form method="post"><button type="submit" name="start_tournament" class="battle-btn">Вступить в бой</button></form>
          </div>';
    return; // Прерываем выполнение, чтобы не показывать интерфейс боя
}

$t = &$_SESSION['tournament'];
$secondsLeft = $t['turn_deadline'] - time();
if ($secondsLeft < 0) $secondsLeft = 0;

// ОБРАБОТКА ХОДА
if (isset($_POST['process_tournament']) && $t['active']) {
    $isTimeout = ($secondsLeft <= 0) || (isset($_POST['timed_out']) && $_POST['timed_out'] == '1');
    
    // Выбор цели игрока
    if (isset($_POST['target_index'])) {
        $t['player_target'] = (int)$_POST['target_index'];
    }
    
    // Если цель мертва, ищем новую
    if (!$t['teamB'][$t['player_target']]['alive']) {
        foreach ($t['teamB'] as $k => $v) { 
            if ($v['alive']) { $t['player_target'] = $k; break; } 
        }
    }

    if ($isTimeout) {
        $playerAtkZone = 0;
        $playerDefZones = [];
    } else {
        $playerAtkZone = $_POST['attack_zone'] ?? rand(1, 5);
        $playerAtkZone = max(1, min(5, (int)$playerAtkZone));
        $playerDefZones = normalizeDefenseZones($_POST['def_zones'] ?? [], 5, 2);
    }

    $roundLog = "<div class='log-entry-wrapper'><div class='log-turn-title'>Раунд {$t['turn']}</div>";

    // 1. АТАКА КОМАНДЫ А (Ваши)
    foreach ($t['teamA'] as &$fighter) {
        if (!$fighter['alive']) continue;

        $target = null;
        if ($fighter['is_player']) {
            if ($isTimeout) {
                $roundLog .= "<div style='color:#7f8c8d'>⌛ {$fighter['name']} пропустил ход.</div>";
                continue; 
            }
            if ($t['teamB'][$t['player_target']]['alive']) {
                $target = &$t['teamB'][$t['player_target']];
            }
        } else {
            // Бот бьет случайного живого
            $liveEnemies = [];
            foreach ($t['teamB'] as $k => $v) { if ($v['alive']) $liveEnemies[] = $k; }
            if (!empty($liveEnemies)) {
                $target = &$t['teamB'][$liveEnemies[array_rand($liveEnemies)]];
            }
        }

        if ($target) {
            $attackZone = $fighter['is_player'] ? $playerAtkZone : rand(1, 5);
            if ($attackZone < 1) {
                $roundLog .= "<div class='log-line' style='color:#7f8c8d'>⌛ {$fighter['name']} пропустил ход.</div>";
                continue;
            }
            $defZones = normalizeDefenseZones([], 5, 2);
            $context = [
                'attacker' => $fighter['is_player'] ? 'Вы' : $fighter['name'],
                'defender' => $target['name'],
                'zone' => $zonesAccusative[$attackZone],
                'defZones' => formatZoneList($defZones, $zonesAccusative)
            ];

            if (in_array($attackZone, $defZones, true)) {
                $roundLog .= "<div class='log-line'>" . getBattlePhrase('defense', 'block', $context) . "</div>";
            } else {
                if (rand(1, 100) <= 4) {
                    $selfDmg = calculateSelfDamage($fighter);
                    $fighter['hp'] = max(0, $fighter['hp'] - $selfDmg);
                    if ($fighter['hp'] <= 0) { $fighter['hp'] = 0; $fighter['alive'] = false; }
                    $context['selfDamage'] = "<span class='log-dmg'>-$selfDmg</span>";
                    $roundLog .= "<div class='log-line'>" . getBattlePhrase('attack', 'self', $context) . "</div>";
                } else {
                    $hitChance = getTournamentHitChance($fighter, $target);
                    if (rand(1, 100) > $hitChance) {
                        $roundLog .= "<div class='log-line'>" . getBattlePhrase('defense', 'dodge', $context) . "</div>";
                    } else {
                        $defRoll = rand(1, 100);
                        $defType = $defRoll <= 12 ? 'panic' : ($defRoll <= 32 ? 'dance' : 'fail');
                        $roundLog .= "<div class='log-line'>" . getBattlePhrase('defense', $defType, $context) . "</div>";

                        $dmg = calculateTournamentDamage($fighter, $target);
                        $glance = rand(1, 100) <= 12;
                        if ($glance) {
                            $dmg = max(1, (int)floor($dmg * 0.6));
                        }
                        $critChance = getTournamentCritChance($fighter, $target);
                        $isCrit = (!$glance && rand(1, 100) <= $critChance);
                        if ($isCrit) {
                            $dmg = (int)floor($dmg * 1.5);
                            $context['damage'] = "<span class='log-dmg'>-$dmg</span>";
                            $roundLog .= "<div class='log-line'>" . getBattlePhrase('attack', 'crit', $context) . "</div>";
                        } else {
                            $context['damage'] = "<span class='log-dmg'>-$dmg</span>";
                            $roundLog .= "<div class='log-line'>" . getBattlePhrase('attack', $glance ? 'glance' : 'hit', $context) . "</div>";
                        }

                        $target['hp'] -= $dmg;
                        if ($target['hp'] <= 0) { $target['hp'] = 0; $target['alive'] = false; }
                    }
                }
            }
        }
    }

    // 2. АТАКА КОМАНДЫ B (Враги)
    foreach ($t['teamB'] as &$fighter) {
        if (!$fighter['alive']) continue;
        
        $liveEnemies = [];
        foreach ($t['teamA'] as $k => $v) { if ($v['alive']) $liveEnemies[] = $k; }
        
        if (!empty($liveEnemies)) {
            $target = &$t['teamA'][$liveEnemies[array_rand($liveEnemies)]];
            $attackZone = rand(1, 5);
            $defZones = $target['is_player'] ? $playerDefZones : normalizeDefenseZones([], 5, 2);
            $context = [
                'attacker' => $fighter['name'],
                'defender' => $target['is_player'] ? $currentUser['username'] : $target['name'],
                'zone' => $zonesAccusative[$attackZone],
                'defZones' => formatZoneList($defZones, $zonesAccusative)
            ];

            if (!empty($defZones) && in_array($attackZone, $defZones, true)) {
                $roundLog .= "<div class='log-line'>" . getBattlePhrase('defense', 'block', $context) . "</div>";
            } else {
                if (rand(1, 100) <= 4) {
                    $selfDmg = calculateSelfDamage($fighter);
                    $fighter['hp'] = max(0, $fighter['hp'] - $selfDmg);
                    if ($fighter['hp'] <= 0) { $fighter['hp'] = 0; $fighter['alive'] = false; }
                    $context['selfDamage'] = "<span class='log-dmg'>-$selfDmg</span>";
                    $roundLog .= "<div class='log-line'>" . getBattlePhrase('attack', 'self', $context) . "</div>";
                } else {
                    $hitChance = getTournamentHitChance($fighter, $target);
                    if (rand(1, 100) > $hitChance) {
                        $roundLog .= "<div class='log-line'>" . getBattlePhrase('defense', 'dodge', $context) . "</div>";
                    } else {
                        $defRoll = rand(1, 100);
                        $defType = $defRoll <= 12 ? 'panic' : ($defRoll <= 32 ? 'dance' : 'fail');
                        $roundLog .= "<div class='log-line'>" . getBattlePhrase('defense', $defType, $context) . "</div>";

                        $dmg = calculateTournamentDamage($fighter, $target);
                        $glance = rand(1, 100) <= 12;
                        if ($glance) {
                            $dmg = max(1, (int)floor($dmg * 0.6));
                        }
                        $critChance = getTournamentCritChance($fighter, $target);
                        $isCrit = (!$glance && rand(1, 100) <= $critChance);
                        if ($isCrit) {
                            $dmg = (int)floor($dmg * 1.5);
                            $context['damage'] = "<span class='log-dmg'>-$dmg</span>";
                            $roundLog .= "<div class='log-line'>" . getBattlePhrase('attack', 'crit', $context) . "</div>";
                        } else {
                            $context['damage'] = "<span class='log-dmg'>-$dmg</span>";
                            $roundLog .= "<div class='log-line'>" . getBattlePhrase('attack', $glance ? 'glance' : 'hit', $context) . "</div>";
                        }

                        $target['hp'] -= $dmg;
                        if ($target['hp'] <= 0) { $target['hp'] = 0; $target['alive'] = false; }
                    }
                }
            }
        }
    }

    // Итоги
    $aliveA = 0; foreach($t['teamA'] as $f) if($f['alive']) $aliveA++;
    $aliveB = 0; foreach($t['teamB'] as $f) if($f['alive']) $aliveB++;

    if ($aliveA == 0 && $aliveB == 0) {
        $t['active'] = false;
        $roundLog .= "<div class='log-line' style='color:#f1c40f; font-weight:bold;'>⚖️ НИЧЬЯ!</div>";
        $conn->query("UPDATE users SET draws = draws + 1 WHERE id = {$currentUser['id']}");
    } elseif ($aliveA == 0) {
        $t['active'] = false;
        $roundLog .= "<div class='log-line' style='color:#e74c3c; font-weight:bold;'>🔴 ПОРАЖЕНИЕ!</div>";
        $conn->query("UPDATE users SET losses = losses + 1 WHERE id = {$currentUser['id']}");
    } elseif ($aliveB == 0) {
        $t['active'] = false;
        $roundLog .= "<div class='log-line' style='color:#2ecc71; font-weight:bold;'>🔵 ПОБЕДА!</div>";
        $conn->query("UPDATE users SET wins = wins + 1 WHERE id = {$currentUser['id']}");
    }

    $roundLog .= "</div>";

    array_unshift($t['log'], $roundLog);
    $t['turn']++;
    $t['turn_deadline'] = time() + 20; // Сброс таймера
    
    header("Location: index.php?page=tournament&log_page=1"); exit;
}

if (isset($_POST['reset_tournament'])) { 
    unset($_SESSION['tournament']); 
    header("Location: index.php?page=tournament"); exit; 
}

// Пагинация лога
$currentPage = isset($_GET['log_page']) ? (int)$_GET['log_page'] : 1;
$currentLogEntry = isset($t['log'][$currentPage-1]) ? $t['log'][$currentPage-1] : "Бой начался!";
?>

<!-- HTML ТУРНИРА -->
<script>
    let timeLeft = <?= $secondsLeft ?>;
    let timerInterval;
    function startTimer() {
        const timerEl = document.getElementById('battle-timer-display');
        if (!timerEl) return;
        timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                document.getElementById('timed_out_input').value = '1';
                document.getElementById('battle-form').submit();
            } else {
                timeLeft--;
                timerEl.innerText = timeLeft;
            }
        }, 1000);
    }
    window.onload = function() {
        startTimer();
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
        <div>🏆 <b>Турнир 10x10</b> (Раунд <?= $t['turn'] ?>)</div>
        <div><a href="?page=home" style="color:#f1c40f">Выйти</a></div>
    </div>

    <!-- СПИСКИ КОМАНД -->
    <form method="post" id="battle-form">
        <input type="hidden" name="process_tournament" value="1">
        <input type="hidden" name="timed_out" id="timed_out_input" value="0">

        <div style="display:flex; justify-content:space-between; gap:10px; margin-bottom:15px;">
            <!-- TEAM A -->
            <div style="flex:1; border:2px solid #3498db; background:#fff;">
                <div style="background:#3498db; color:white; padding:5px; text-align:center;">СИНИЕ (<?= count(array_filter($t['teamA'], function($f){return $f['alive'];})) ?>)</div>
                <?php foreach($t['teamA'] as $f): ?>
                    <div style="padding:4px; border-bottom:1px solid #eee; font-size:11px; <?= !$f['alive']?'color:#ccc; text-decoration:line-through;':'' ?> <?= $f['is_player']?'background:#eafaf1; font-weight:bold;':'' ?>">
                        <?= $f['name'] ?> [<?= $f['hp'] ?>]
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- TEAM B -->
            <div style="flex:1; border:2px solid #c0392b; background:#fff;">
                <div style="background:#c0392b; color:white; padding:5px; text-align:center;">КРАСНЫЕ (<?= count(array_filter($t['teamB'], function($f){return $f['alive'];})) ?>)</div>
                <?php foreach($t['teamB'] as $idx => $f): ?>
                    <div style="padding:4px; border-bottom:1px solid #eee; font-size:11px; display:flex; align-items:center; <?= !$f['alive']?'color:#ccc; text-decoration:line-through;':'' ?>">
                        <?php if($f['alive'] && $t['active']): ?>
                            <input type="radio" name="target_index" value="<?= $idx ?>" <?= ($idx == $t['player_target']) ? 'checked' : '' ?> style="margin-right:5px;">
                        <?php endif; ?>
                        <?= $f['name'] ?> [<?= $f['hp'] ?>]
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- УПРАВЛЕНИЕ -->
        <?php if ($t['active']): ?>
            <div class="battle-controls-bottom">
                <div style="margin-bottom:5px; font-size:12px; color:#aaa;">Время:</div>
                <div id="battle-timer-display" class="battle-timer"><?= $secondsLeft ?></div>

                <div class="zone-selector" style="margin-top:10px;">
                    <div class="zone-column">
                        <h4>Атака</h4>
                        <label class="zone-option"><input type="radio" name="attack_zone" value="1" checked> Голова</label>
                        <label class="zone-option"><input type="radio" name="attack_zone" value="2"> Корпус</label>
                        <label class="zone-option"><input type="radio" name="attack_zone" value="3"> Живот</label>
                        <label class="zone-option"><input type="radio" name="attack_zone" value="4"> Пах</label>
                        <label class="zone-option"><input type="radio" name="attack_zone" value="5"> Ноги</label>
                    </div>
                    <div class="zone-column">
                        <h4>Блок</h4>
                        <div class="zone-note">Выберите 2 зоны</div>
                        <label class="zone-option"><input type="checkbox" name="def_zones[]" value="1" checked> Голова</label>
                        <label class="zone-option"><input type="checkbox" name="def_zones[]" value="2" checked> Корпус</label>
                        <label class="zone-option"><input type="checkbox" name="def_zones[]" value="3"> Живот</label>
                        <label class="zone-option"><input type="checkbox" name="def_zones[]" value="4"> Пах</label>
                        <label class="zone-option"><input type="checkbox" name="def_zones[]" value="5"> Ноги</label>
                    </div>
                </div>
                <button type="submit" class="battle-btn">АТАКА</button>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:20px;">
                <button type="submit" name="reset_tournament" class="battle-btn">Выход</button>
            </div>
        <?php endif; ?>
    </form>

    <!-- LOG -->
    <div class="battle-log-container" style="height:300px;">
        <?= $currentLogEntry ?>
        <!-- Пагинация (простая) -->
        <?php if (count($t['log']) > 1): ?>
            <div class="log-pagination">
                <?php for ($i = 1; $i <= min(5, count($t['log'])); $i++): ?>
                    <a href="?page=tournament&log_page=<?= $i ?>" class="log-page-link"><?= count($t['log']) - $i + 1 ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>