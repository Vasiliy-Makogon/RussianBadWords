<?php

namespace Krugozor\RussianBadWords;

use RuntimeException;

class AbstractBadWordsValidator
{
    /** @var array|array[] */
    protected static array $letters = [
        ['а', 'е', 'о', 'с', 'х', 'м', 'к', 'р'],
        ['a', 'e', 'o', 'c', 'x', 'm', 'k', 'p']
    ];

    /**
     * Кэш слов с преобразованием (см. self::createFakeWords) для каждого конкретного
     * класса по ключу - имени класса, пример:
     *
     * [
     *   Krugozor\RussianBadWords\Items\ProfanityWordsValidator => [слово, слово, ...],
     *   Krugozor\RussianBadWords\Items\StopWordsValidator => [слово, слово, ...],
     * ]
     *
     * @var array
     */
    protected static array $cacheWords = [];

    /**
     * @var string Проверяемый на плохие слова текст
     */
    private string $value;

    /**
     * @var array Массив плохих слов, найденных после запуска метода validate()
     */
    private array $failedWords = [];

    /**
     * @param mixed $value
     */
    public function __construct(string $value)
    {
        if (!isset(static::$words)) {
            throw new RuntimeException(sprintf(
                '%s: Не объявлены необходимые свойства в дочернем классе %s', __METHOD__, get_class($this)
            ));
        }

        $this->value = trim($value);
    }

    /**
     * Возвращает false (факт ошибки), если найдено объявление с плохими словами в строке.
     */
    public function validate(): bool
    {
        if (!$this->value) {
            return true;
        }

        $texts = preg_split('~(\s|_|\.|,|\||\?|:|;|@|#|%|\^|&|\*|=|\+|!|\~|`|\'|"|\[|\]|\)|\(|\\\|/)~', $this->value);

        array_walk($texts, function (&$val) {
            $val = mb_strtolower($val);
        });

        $texts = array_filter($texts, function ($v) {
            return mb_strlen($v) >= 3;
        });

        $className = get_class($this);
        if (!isset(self::$cacheWords[$className])) {
            self::$cacheWords[$className] = array_merge(static::$words, self::createFakeWords(static::$words));
        }

        $this->failedWords = array_intersect($texts, self::$cacheWords[$className]);

        return !$this->failedWords;
    }

    /**
     * @return array
     */
    public function getFailedWords(): array
    {
        return $this->failedWords;
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