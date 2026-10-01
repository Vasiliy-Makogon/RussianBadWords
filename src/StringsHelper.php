<?php

namespace Krugozor\RussianBadWords;

/**
 * @deprecated Использовался в 1.x для генерации вариантов подмен; сохранён для совместимости.
 */
class StringsHelper
{
    /**
     * Заменяет часть строки, начинающуюся с символа с порядковым номером $start
     * и (необязательной) длиной $length, строкой $replacement и возвращает результат.
     */
    public static function mb_substr_replace(
        string $string,
        string $replacement,
        int $start,
        ?int $length = null,
        ?string $encoding = null
    ): string {
        $encoding = $encoding ?? mb_internal_encoding();

        if ($length === null) {
            return mb_substr($string, 0, $start, $encoding) . $replacement;
        }

        if ($length < 0) {
            $length = mb_strlen($string, $encoding) - $start + $length;
        }

        return mb_substr($string, 0, $start, $encoding)
            . $replacement
            . mb_substr($string, $start + $length, mb_strlen($string, $encoding), $encoding);
    }
}
