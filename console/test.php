<?php

// Пример генерации русского слова с подменёнными латинскими буквами
print_r(createFakeWords(['наркотик']));
echo PHP_EOL;

// Проверка сообщения от пользователя.
// В слове "электрo-фишер" кириллистическая буква "о" заменена на латинскую,
// а слова умышленно соединены различными символами с целью обмана программы.
$message = 'Продам_электрo-фишер.fisher-f-3500 не дорого!';
// Получим плохие слова, обнаруженные в сообщении пользователя.
// В первый аргумент функции нужно передать массив из dictionary.php
// (для примера передадим массив только с несколькими плохими словами в качестве примера).
print_r(validate(['fisher-f-3500', 'электро-фишер'], $message));

/**
 * Возвращает массив плохих слов из словаря $dictionary,
 * если они обнаружены в теле сообщения $message.
 *
 * @param array $dictionary словарь плохих слов в виде массива
 * @param string $message сообщение от пользователя
 * @return array
 */
function validate(array $dictionary, string $message): array
{
    if (!$message) {
        return [];
    }

    // Разбиваем сообщение по любому символу, отличному от буквы, цифры и знака дефис.
    $messageWords = preg_split('~(\s|_|\.|,|\||\?|:|;|@|#|%|\^|&|\*|=|\+|!|\~|`|\'|"|\[|\]|\)|\()~', $message);

    // Исключаем все слова менее 2 символов
    $messageWords = array_filter($messageWords, function($v) {
        return mb_strlen($v) >= 3;
    });

    // Преобразуем все слова в нижний регистр
    array_walk($messageWords, function (&$val) {
        $val = mb_strtolower($val);
    });

    return array_intersect($messageWords, createFakeWords($dictionary));
}

/**
 * Заменяет русские буквы на английские поочередно и все сразу.
 *
 * @param array $words
 * @return array
 */
function createFakeWords(array $words = []): array
{
    static $letters = [
        ['а', 'е', 'о', 'с', 'х', 'м', 'к', 'р'], ['a', 'e', 'o', 'c', 'x', 'm', 'k', 'p']
    ];

    $data = [];
    foreach ($words as $word) {
        $tmp = [];
        foreach ($letters[0] as $key => $letter) {
            $offset = 0;
            while (($position = mb_strpos($word, $letter, $offset)) !== false) {
                $tmp[] = mb_substr_replace($word, $letters[1][$key], $position, 1);
                $offset = $position + 1;
            }
        }
        $tmp[] = str_replace($letters[0], $letters[1], $word);
        $data = array_merge($data, array_unique($tmp));
    }

    return $data;
}

/**
 * Заменяет часть строки string, начинающуюся с символа с порядковым номером start
 * и (необязательной) длиной length, строкой replacement и возвращает результат.
 *
 * @param $string
 * @param $replacement
 * @param $start
 * @param null $length
 * @param null $encoding
 * @return string
 */
function mb_substr_replace($string, $replacement, $start, $length = null, $encoding = null): string
{
    if ($encoding == null) {
        $encoding = mb_internal_encoding();
    }

    if ($length == null) {
        return mb_substr($string, 0, $start, $encoding) . $replacement;
    } else {
        if ($length < 0) {
            $length = mb_strlen($string, $encoding) - $start + $length;
        }

        return
            mb_substr($string, 0, $start, $encoding) .
            $replacement .
            mb_substr($string, $start + $length, mb_strlen($string, $encoding), $encoding);
    }
}