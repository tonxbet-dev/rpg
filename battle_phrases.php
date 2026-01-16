<?php
$attackPhrases = [
    'hit' => [
        '{attacker} пробил {zone} и нанес {damage} урона.',
        '{attacker} попал в {zone} — минус {damage}.',
        '{attacker} резко ударил по {zone}, выбив {damage}.',
        '{attacker} нашел брешь и зарядил в {zone} ({damage}).'
    ],
    'crit' => [
        '{attacker} устроил КРИТ по {zone}! Потери {damage}.',
        '{attacker} поймал момент и кританул в {zone} на {damage}.',
        '{attacker} размашисто рубанул по {zone} — КРИТ {damage}.'
    ],
    'self' => [
        '{attacker} пытался ударить по {zone}, но поскользнулся и получил {selfDamage}.',
        '{attacker} замахнулся на {zone} и уронил оружие себе на ногу {selfDamage}.',
        '{attacker} сделал эффектный выпад в {zone}, но споткнулся и получил {selfDamage}.'
    ],
    'glance' => [
        '{attacker} чихнул в момент удара, поэтому по {zone} прошло лишь {damage}.',
        '{attacker} задел {zone} вскользь — всего {damage} урона.',
        '{attacker} запнулся о собственную тень и нанес лишь {damage} по {zone}.'
    ]
];

$defensePhrases = [
    'block' => [
        '{defender} прикрыл {defZones} и отразил атаку.',
        '{defender} закрывает {defZones} — удар в {zone} не прошел.',
        '{defender} вовремя подставил защиту на {defZones}.'
    ],
    'dodge' => [
        '{attacker} пытался ударить "{defender}", но тот увернулся от удара.',
        '"{defender}" скользнул в сторону — удар по {zone} ушел в молоко.',
        '{defender} вывернулся, и атака по {zone} прошла мимо.'
    ],
    'fail' => [
        '{defender} защищал {defZones}, но пропустил удар в {zone}.',
        '{defender} не угадал защиту ({defZones}) и получил в {zone}.',
        '{defender} прикрыл не те зоны и схлопотал в {zone}.'
    ],
    'dance' => [
        '{defender} пытался танцевать во время боя и пропустил удар в {zone}.',
        '{defender} увлекся танцами и забыл про {defZones}, получив в {zone}.',
        '{defender} включил боевой танец, но удар пришелся в {zone}.'
    ],
    'panic' => [
        '{defender} в панике закрыл глаза и пропустил удар в {zone}.',
        '{defender} крикнул "мама!" и получил в {zone}.'
    ]
];

function formatBattlePhrase($template, $context) {
    foreach ($context as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
}

function getBattlePhrase($group, $type, $context) {
    global $attackPhrases, $defensePhrases;
    $list = $group === 'attack' ? $attackPhrases : $defensePhrases;
    if (!isset($list[$type]) || empty($list[$type])) {
        return '';
    }
    $template = $list[$type][array_rand($list[$type])];
    return formatBattlePhrase($template, $context);
}
?>
