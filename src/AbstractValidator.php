<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

use RuntimeException;

class AbstractValidator
{
    /**
     * @var array
     */
    protected static array $letters = [
        ['а', 'е', 'о', 'с', 'х', 'м', 'к', 'р'],
        ['a', 'e', 'o', 'c', 'x', 'm', 'k', 'p']
    ];

    /**
     * Кэш слов с преобразованием (см. self::createFakeWords) для каждого конкретного
     * класса по ключу - имени класса, пример:
     *
     * [
     *   Krugozor\BadWords\ProfanityWordsValidator => [слово, слово, ...],
     *   Krugozor\BadWords\StopWordsValidator => [слово, слово, ...],
     * ]
     *
     * @var array
     */
    protected static array $cacheWords = [];

    public function __construct()
    {
        if (!isset(static::$words)) {
            throw new RuntimeException(sprintf(
                '%s: Не объявлены необходимые свойства в дочернем классе ',
                __METHOD__
            ));
        }
    }

    /**
     * Возвращает массив плохих слов из словаря $dictionary,
     * если они обнаружены в теле сообщения $message.
     */
    public function validate(string $message): array
    {
        if (!$message) {
            return [];
        }

        $texts = preg_split('~(\s|_|\.|,|\||\?|:|;|@|#|%|\^|&|\*|=|\+|!|\~|`|\'|"|\[|\]|\)|\()~', $message);

        array_walk($texts, function (&$val) {
            $val = mb_strtolower($val);
        });

        $texts = array_filter($texts, function($v) {
            return mb_strlen($v) >= 3;
        });

        $className = get_class($this);
        if (!isset(self::$cacheWords[$className])) {
            self::$cacheWords[$className] = array_merge(static::$words, self::createFakeWords(static::$words));
        }

        return ! (bool) array_intersect($texts, self::$cacheWords[$className]);
    }

    /**
     * Заменяет русские буквы на английские поочередно и все сразу.
     *
     * @param array $words
     * @return array
     */
    public static function createFakeWords(array $words): array
    {
        $data = [];
        foreach ($words as $word) {
            $tmp = [];

            foreach (self::$letters[0] as $key => $letter) {
                $offset = 0;
                while (($position = mb_strpos($word, $letter, $offset)) !== false) {
                    $tmp[] = StringsHelper::mb_substr_replace($word, self::$letters[1][$key], $position, 1);
                    $offset = $position + 1;
                }
            }

            $tmp[] = str_replace(self::$letters[0], self::$letters[1], $word);
            $data = array_merge($data, array_unique($tmp));
        }

        return $data;
    }
}