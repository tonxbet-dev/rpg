<?php
define('STAT_COST_BASE', 25);
define('STAT_COST_GROWTH', 7);

function getStatDefinitions() {
    return [
        'health' => [
            'label' => 'Здоровье',
            'icon' => 'sun',
            'description' => 'Влияет на количество жизней.',
            'cost_multiplier' => 1.2
        ],
        'strength' => [
            'label' => 'Сила',
            'icon' => 'sword',
            'description' => 'Определяет наносимый противнику урон.',
            'cost_multiplier' => 1.1
        ],
        'agility' => [
            'label' => 'Ловкость',
            'icon' => 'wind',
            'description' => 'Влияет на вероятность нанести удар, а также не дает врагу сконцентрироваться и нанести критический удар.',
            'cost_multiplier' => 1.05
        ],
        'stamina' => [
            'label' => 'Выносливость',
            'icon' => 'shield',
            'description' => 'Влияет на получаемый урон и количество жизней.',
            'cost_multiplier' => 1.1
        ],
        'perception' => [
            'label' => 'Внимательность',
            'icon' => 'crown',
            'description' => 'Позволяет чаще избегать ударов соперника.',
            'cost_multiplier' => 1.0
        ],
        'cunning' => [
            'label' => 'Хитрость',
            'icon' => 'sparkles',
            'description' => 'Влияет на вероятность критического удара.',
            'cost_multiplier' => 1.05
        ],
        'charisma' => [
            'label' => 'Харизма',
            'icon' => 'gem',
            'description' => 'Помогает выбивать больше денег из соперников, больше зарабатывать и увеличивает шанс на добычу редкой вещи или ресурса в игре.',
            'cost_multiplier' => 0.9
        ]
    ];
}

function getStatColumnMap() {
    return [
        'health' => 'stat_health',
        'strength' => 'stat_strength',
        'agility' => 'stat_agility',
        'stamina' => 'stat_stamina',
        'perception' => 'stat_perception',
        'cunning' => 'stat_cunning',
        'charisma' => 'stat_charisma'
    ];
}

function getUser($id) {
    global $conn;
    return $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();
}

function getBaseStatsFromUser($user) {
    return [
        'health' => max(1, (int)($user['stat_health'] ?? 1)),
        'strength' => max(1, (int)($user['stat_strength'] ?? 1)),
        'agility' => max(1, (int)($user['stat_agility'] ?? 1)),
        'stamina' => max(1, (int)($user['stat_stamina'] ?? 1)),
        'perception' => max(1, (int)($user['stat_perception'] ?? 1)),
        'cunning' => max(1, (int)($user['stat_cunning'] ?? 1)),
        'charisma' => max(1, (int)($user['stat_charisma'] ?? 1))
    ];
}

function getEquippedItemBonuses($userId) {
    global $conn;
    $bonus = ['str' => 0, 'def' => 0, 'hp' => 0];
    $res = $conn->query("SELECT SUM(i.stat_str) as bonus_str, SUM(i.stat_def) as bonus_def, SUM(i.stat_hp) as bonus_hp FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = $userId AND inv.is_equipped = 1");
    if ($res && $row = $res->fetch_assoc()) {
        $bonus['str'] = (int)($row['bonus_str'] ?? 0);
        $bonus['def'] = (int)($row['bonus_def'] ?? 0);
        $bonus['hp'] = (int)($row['bonus_hp'] ?? 0);
    }
    return $bonus;
}

function calculateDerivedStats($baseStats, $bonusStats) {
    $health = $baseStats['health'];
    $strength = $baseStats['strength'];
    $agility = $baseStats['agility'];
    $stamina = $baseStats['stamina'];
    $perception = $baseStats['perception'];
    $cunning = $baseStats['cunning'];
    $charisma = $baseStats['charisma'];

    $maxHp = max(1, 80 + ($health * 25) + ($stamina * 10) + $bonusStats['hp']);
    $attack = max(1, ($strength * 3) + (int)floor($agility * 1.5) + $bonusStats['str']);
    $armor = max(0, ($stamina * 2) + (int)floor($perception * 1.2) + $bonusStats['def']);

    $damageMin = max(1, (int)floor($attack * 0.7));
    $damageMax = max($damageMin + 1, (int)floor($attack * 1.1));
    $hitChance = min(95, 60 + ($agility * 2) + (int)floor($perception * 0.5));
    $critChance = min(50, 5 + ($cunning * 2));
    $critResist = min(40, (int)floor($agility * 1.5));
    $dodgeChance = min(35, 3 + ($perception * 2));
    $goldBonus = min(50, $charisma * 2);

    return [
        'total_str' => $attack,
        'total_def' => $armor,
        'armor' => $armor,
        'total_max_hp' => $maxHp,
        'damage_min' => $damageMin,
        'damage_max' => $damageMax,
        'hit_chance' => $hitChance,
        'crit_chance' => $critChance,
        'crit_resist' => $critResist,
        'dodge_chance' => $dodgeChance,
        'gold_bonus' => $goldBonus
    ];
}

function buildUserStatsSnapshot($userId) {
    $user = getUser($userId);
    $base = getBaseStatsFromUser($user);
    $bonus = getEquippedItemBonuses($userId);
    $derived = calculateDerivedStats($base, $bonus);
    return ['user' => $user, 'base' => $base, 'bonus' => $bonus, 'derived' => $derived];
}

function syncUserDerivedStats($userId, $derived, $currentHp = null) {
    global $conn;
    $maxHp = (int)$derived['total_max_hp'];
    $strength = (int)$derived['total_str'];
    $defense = (int)$derived['total_def'];
    $armor = (int)$derived['armor'];
    $damageMin = (int)$derived['damage_min'];
    $damageMax = (int)$derived['damage_max'];

    $hpSql = '';
    if ($currentHp !== null && $currentHp > $maxHp) {
        $hpSql = ", hp = $maxHp";
    }

    $conn->query("UPDATE users SET max_hp = $maxHp, strength = $strength, defense = $defense, armor = $armor, damage_min = $damageMin, damage_max = $damageMax $hpSql WHERE id = $userId");
}

function calculateUpgradeCost($currentValue, $increaseAmount, $multiplier = 1.0) {
    $total = 0;
    for ($i = 0; $i < $increaseAmount; $i++) {
        $stepCost = STAT_COST_BASE + ($currentValue + $i) * STAT_COST_GROWTH;
        $total += $stepCost;
    }
    return (int)ceil($total * $multiplier);
}

function applyStatUpgrade($userId, $statKey, $amount) {
    global $conn;
    $defs = getStatDefinitions();
    $columns = getStatColumnMap();

    if (!isset($defs[$statKey]) || !isset($columns[$statKey])) {
        return ['success' => false, 'message' => 'Неизвестная характеристика.'];
    }

    $amount = (int)$amount;
    if ($amount < 1) $amount = 1;
    if ($amount > 50) $amount = 50;

    $user = getUser($userId);
    $base = getBaseStatsFromUser($user);
    $currentValue = $base[$statKey];
    $cost = calculateUpgradeCost($currentValue, $amount, $defs[$statKey]['cost_multiplier']);

    if ((int)$user['money'] < $cost) {
        return ['success' => false, 'message' => 'Недостаточно золота для прокачки.'];
    }

    $column = $columns[$statKey];
    $newValue = $currentValue + $amount;
    $conn->query("UPDATE users SET $column = $newValue, money = money - $cost WHERE id = $userId");

    $snapshot = buildUserStatsSnapshot($userId);
    syncUserDerivedStats($userId, $snapshot['derived'], (int)$user['hp']);

    return [
        'success' => true,
        'message' => $defs[$statKey]['label'] . " увеличено на $amount (-$cost золота)."
    ];
}

function generateBotBaseStats($level, $profile = 'balanced') {
    $base = [
        'health' => 1 + (int)floor($level * 1.4),
        'strength' => 1 + (int)floor($level * 1.3),
        'agility' => 1 + (int)floor($level * 1.1),
        'stamina' => 1 + (int)floor($level * 1.2),
        'perception' => 1 + (int)floor($level * 1.0),
        'cunning' => 1 + (int)floor($level * 0.9),
        'charisma' => 1 + (int)floor($level * 0.6)
    ];

    if ($profile === 'agile') {
        $base['agility'] += 2;
        $base['perception'] += 1;
        $base['strength'] = max(1, $base['strength'] - 1);
    } elseif ($profile === 'brutal') {
        $base['strength'] += 2;
        $base['health'] += 1;
        $base['perception'] = max(1, $base['perception'] - 1);
    } elseif ($profile === 'tank') {
        $base['stamina'] += 2;
        $base['health'] += 2;
        $base['agility'] = max(1, $base['agility'] - 1);
    }

    return $base;
}

function buildBotCombatStats($level, $profile = 'balanced') {
    $baseStats = generateBotBaseStats($level, $profile);
    $derived = calculateDerivedStats($baseStats, ['str' => 0, 'def' => 0, 'hp' => 0]);
    return array_merge($derived, ['base_stats' => $baseStats]);
}
?>
