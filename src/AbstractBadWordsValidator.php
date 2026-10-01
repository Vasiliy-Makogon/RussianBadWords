<?php

namespace Krugozor\RussianBadWords;

/**
 * Валидатор в стиле 1.x: класс-наследник объявляет статическое свойство $words со списком слов.
 *
 * Сохранён для совместимости, в том числе с копиями словарей, которые установщик 1.x
 * раскладывал по проектам. Внутри работает новое ядро: см. BadWordsValidator и Dictionary.
 *
 * @deprecated Используйте BadWordsValidator и Dictionary.
 */
abstract class AbstractBadWordsValidator
{
    /**
     * @deprecated Таблица подмен 1.x, используется только в createFakeWords().
     * @var array{0: list<string>, 1: list<string>}
     */
    protected static array $letters = [
        ['а', 'е', 'о', 'с', 'х', 'м', 'к', 'р'],
        ['a', 'e', 'o', 'c', 'x', 'm', 'k', 'p'],
    ];

    /**
     * Слова словаря. Класс-наследник переопределяет свойство своим списком.
     *
     * @var list<string>
     */
    public static array $words = [];

    /** @var array<string, array{0: list<string>, 1: Dictionary}> Словарь каждого класса и список $words при его сборке */
    private static array $dictionaries = [];

    private string $value;

    /** @var list<string> */
    private array $failedWords = [];

    public function __construct(string $value)
    {
        $this->value = trim($value);
    }

    /**
     * Возвращает false, если в тексте найдены плохие слова.
     */
    public function validate(): bool
    {
        $this->failedWords = [];
        if ($this->value === '') {
            return true;
        }

        $normalizer = new TextNormalizer();
        $result = (new BadWordsValidator(self::cachedDictionary(), $normalizer))->check($this->value);
        foreach ($result->occurrences() as $occurrence) {
            $fragment = $normalizer->fold($occurrence->fragment());
            if (!in_array($fragment, $this->failedWords, true)) {
                $this->failedWords[] = $fragment;
            }
        }

        return $this->failedWords === [];
    }

    /**
     * Найденные плохие слова: фрагменты текста в нижнем регистре, без диакритики
     * и невидимых символов, без повторов. «ё» сохраняется как в тексте.
     *
     * @return list<string>
     */
    public function getFailedWords(): array
    {
        return $this->failedWords;
    }

    /**
     * Словарь класса. По умолчанию строится из static::$words.
     */
    protected static function dictionary(): Dictionary
    {
        return Dictionary::fromWords(static::$words);
    }

    /**
     * Заменяет русские буквы на английские поочерёдно и все сразу.
     *
     * @deprecated Новое ядро сравнивает формы слов и не нуждается в вариантах подмен.
     * @param list<string> $words
     * @return list<string>
     */
    public static function createFakeWords(array $words): array
    {
        $data = [];
        foreach ($words as $word) {
            $tmp = [];
            foreach (self::$letters[0] as $key => $letter) {
                $offset = 0;
                while (($position = mb_strpos($word, $letter, $offset, 'UTF-8')) !== false) {
                    $tmp[] = StringsHelper::mb_substr_replace($word, self::$letters[1][$key], $position, 1, 'UTF-8');
                    $offset = $position + 1;
                }
            }
            $tmp[] = str_replace(self::$letters[0], self::$letters[1], $word);
            foreach (array_unique($tmp) as $fake) {
                $data[] = $fake;
            }
        }

        return $data;
    }

    private static function cachedDictionary(): Dictionary
    {
        $class = static::class;
        if (!isset(self::$dictionaries[$class]) || self::$dictionaries[$class][0] !== static::$words) {
            self::$dictionaries[$class] = [static::$words, static::dictionary()];
        }

        return self::$dictionaries[$class][1];
    }
}
