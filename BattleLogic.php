<?php

class BattleSystem {
    
    // --- 1.1 Усталость (Fatigue) ---
    // Возвращает множитель урона (0.5 .. 1.0) в зависимости от усталости
    public static function calculateFatigueMultiplier($currentFatigue, $maxFatigue = 100) {
        // Чем больше усталость, тем меньше урон. Макс штраф 50%.
        $penalty = min(0.5, ($currentFatigue / $maxFatigue) * 0.5);
        return 1.0 - $penalty;
    }

    // --- 1.2 Предугадывание (Mind game) ---
    // Проверяет серию угадываний и возвращает бонус к защите
    public static function checkMindGameBonus($attackerHistory, $defenderZone) {
        // Если атакующий 2 раза подряд бил в ту же зону, что и сейчас защищает защитник -> бонус защиты
        $count = 0;
        foreach (array_reverse($attackerHistory) as $move) {
            if ($move['attack_zone'] == $defenderZone) $count++;
            else break;
        }
        
        if ($count >= 2) return 0.5; // +50% к защите (снижение урона)
        return 0;
    }

    // --- 1.3 Перехват (Counter) ---
    // Возвращает урон перехвата, если условия выполнены
    public static function calculateCounterDamage($defenderStr, $isPerfectBlock) {
        if ($isPerfectBlock) {
            return floor($defenderStr * 0.3); // 30% от силы защитника
        }
        return 0;
    }

    // --- 2.1 Давление команды ---
    // Множитель урона для меньшинства (Comeback mechanic)
    public static function getTeamPressureMultiplier($myTeamCount, $enemyTeamCount) {
        if ($myTeamCount < $enemyTeamCount) {
            // +10% урона за каждого недостающего бойца
            $diff = $enemyTeamCount - $myTeamCount;
            return 1.0 + ($diff * 0.1); 
        }
        return 1.0;
    }

    // --- 2.2 Распыление урона (Splash) ---
    // Возвращает массив [основной урон, урон по союзникам]
    public static function calculateSplashDamage($rawDamage, $hitsTakenThisRound) {
        if ($hitsTakenThisRound > 2) { // Если бьют больше 2-х человек
            $mainDmg = $rawDamage * 0.7;
            $splashDmg = $rawDamage * 0.15; // 15% улетает в двух случайных союзников
            return ['main' => $mainDmg, 'splash' => $splashDmg];
        }
        return ['main' => $rawDamage, 'splash' => 0];
    }

    // --- 3.1 Турнирные модификаторы ---
    public static function generateTournamentRules() {
        $modifiers = [
            'NO_CRIT' => '🛑 Без критов: Критические удары отключены.',
            'LEG_DAY' => '🦵 День ног: Удары по ногам наносят x2 урона.',
            'FATIGUE_X2' => '😫 Изнурение: Усталость накапливается в 2 раза быстрее.',
            'ONE_BLOCK' => '🛡️ Дырявая защита: Блок действует только 50% времени.'
        ];
        $key = array_rand($modifiers);
        return ['code' => $key, 'desc' => $modifiers[$key]];
    }

    // --- 4.1 Стиль боя ---
    public static function detectPlayStyle($moveHistory) {
        $atkHead = 0; $atkBody = 0; $defBlock = 0;
        foreach ($moveHistory as $move) {
            if ($move['type'] == 'attack') {
                if ($move['zone'] == 1) $atkHead++;
                if ($move['zone'] == 2) $atkBody++;
            }
            if ($move['type'] == 'block_success') $defBlock++;
        }

        if ($atkHead > 5) return 'AGRESSIVE'; // Бонус к криту
        if ($defBlock > 5) return 'DEFENSIVE'; // Бонус к броне
        return 'BALANCED'; // Бонус к регену усталости
    }

    // --- 7.1 Анти-рандом / Анти-спам ---
    public static function checkSpamPenalty($moveHistory, $currentZone) {
        $spamCount = 0;
        foreach (array_reverse($moveHistory) as $move) {
            if ($move['attack_zone'] == $currentZone) $spamCount++;
            else break;
        }
        // Если 3 раза подряд в одну зону -> 50% шанс промаха
        return ($spamCount >= 3) ? 50 : 0;
    }
}
?>