<?php

/**
 * Получить массив с размерами товара в мм
 * @param string $str формат 58х24х35 (ДхШхВ) см
 * @return array массив из значений размерностей ['Длина' => 580, 'Ширина' => 240, ... ]
 */
function getSize(string $str)
{
    $aTmp = [];
    $size = [
        'Д' => 'Длина',
        'Ш' => 'Ширина',
        'Г' => 'Глубина',
        'В' => 'Высота',
    ];
    preg_match_all('!\d+!', $str, $numbers);
    preg_match_all('![ДШВГ]!u', $str, $sizeName);

    foreach ($sizeName[0] as $key => $item) {
        $aTmp[$size[$item]] = $numbers[0][$key] . '0';
    }

    return $aTmp;
}

/**
 * @param array $ar массив в формате ['Длина' => 580, 'Ширина' => 240, ... ], возможно не все размеры
 * @return string строка в формате «Габариты (Ш*В*Г*Д, мм): Ч*Ч*Ч*Ч», в соответствии с массивом.
 */
function getSizeString(array $ar)
{
    if (count($ar) == 0) {
        return '';
    }

    $newKeys = array_map(function ($x) {
        return mb_substr($x, 0, 1);
    }, array_keys($ar));

    $arNew = array_combine($newKeys, $ar);
    uksort($arNew, function($a, $b) {
        return mb_strpos('ШВГД', $a) - mb_strpos('ШВГД', $b);
    });

    $res = 'Габариты (' . implode('*', array_keys($arNew)) . ', мм): ' . implode('*', $arNew);

    return $res;
}