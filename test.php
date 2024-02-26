<?php

$letters = [
    ['а', 'е', 'о', 'с', 'х', 'м', 'к', 'р'], ['a', 'e', 'o', 'c', 'x', 'm', 'k', 'p']
];

// сюда нужно передать массив из dictionary.php, но для примера
// передам массив только с одним "плохим" словом в качестве примера
print_r(createFakeWords($letters, ['наркотик']));

/**
 * Заменяет русские буквы на английские поочередно и все сразу.
 *
 * @param array $letters карта замены похожих букв из кириллицы на латиницу
 * @param array $words
 * @return array
 */
function createFakeWords(array $letters, array $words = [])
{
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
function mb_substr_replace($string, $replacement, $start, $length = null, $encoding = null)
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