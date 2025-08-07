<?php

namespace Krugozor\RussianBadWords;

class StringsHelper
{
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
    public static function mb_substr_replace($string, $replacement, $start, $length = null, $encoding = null): string
    {
        if ($encoding === null) {
            $encoding = mb_internal_encoding();
        }

        if ($length === null) {
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
}