<?php

/**
 * Получить массив с размерами товара в мм
 * @param  string  $str  формат 58х24х35 (ДхШхВ) см
 * @return array массив из значений размерностей ['Длина' => 580, 'Ширина' => 240, ... ]
 */
function getSize(string $str): array
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
        $aTmp[$size[$item]] = $numbers[0][$key].'0';
    }

    return $aTmp;
}

/**
 * @param  array  $ar  массив в формате ['Длина' => 580, 'Ширина' => 240, ... ], возможно не все размеры
 * @return string строка в формате «Габариты (Ш*В*Г*Д, мм): Ч*Ч*Ч*Ч», в соответствии с массивом.
 */
function getSizeString(array $ar): string
{
    if (count($ar) == 0) {
        return '';
    }

    $newKeys = array_map(function ($x) {
        return mb_substr($x, 0, 1);
    }, array_keys($ar));

    $arNew = array_combine($newKeys, $ar);
    uksort($arNew, function ($a, $b) {
        return mb_strpos('ШВГД', $a) - mb_strpos('ШВГД', $b);
    });

    $res = 'Габариты ('.implode('*', array_keys($arNew)).', мм): '.implode('*', $arNew);

    return $res;
}

function loadDidom()
{
    $cwd = getcwd();
    chdir(DIR_SYSTEM.'library/DiDom');
    require_once('ClassAttribute.php');
    require_once('Document.php');
    require_once('Node.php');
    require_once('Element.php');
    require_once('Encoder.php');
    require_once('Errors.php');
    require_once('Query.php');
    require_once('StyleAttribute.php');
    require_once('Exceptions/InvalidSelectorException.php');
    chdir($cwd);
}

/**
 * @param $str - строка, содержащая цифры и текст
 * @return int - возвращает целое число, состоящее из цифр $sum
 */
function digit($str): string
{
    return preg_replace('/[^0-9]/', '', $str);
}

/**
 * удаляет комментарии из html-разметки
 * @param $html
 * @return array|string|string[]|null
 */
function removeHtmlComments($html)
{
    $res = $html;
    $startPos = mb_strpos($html, '<!--');
    $endPos = mb_strpos($html, '-->');
    while ($startPos !== false && $endPos !== false) {
        $res = mb_substr($res, 0, $startPos - 1).mb_substr($html, $endPos + 3);
        $startPos = mb_strpos($res, '<!--');
        $endPos = mb_strpos($res, '-->');
    }

//        $res = preg_replace('/<!--(.*?)-->/', '', $html);
    return $res;
}

/**
 * удаляет комментарии из html-разметки
 * @param $html
 * @return array|string|string[]|null
 */
function removeHtmlComments2($html)
{
    $res = preg_replace('/<!--(.*?)-->/', '', $html);
    return $res;
}

/**
 * TODO Не пойму, зачем нужен этот метод?
 * @param  string  $str
 * @return string
 */
function trimScript(string $str): string
{
    return preg_split("/<script>/", $str)[0];
}
