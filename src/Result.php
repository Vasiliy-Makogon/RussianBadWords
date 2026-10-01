<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

/**
 * Результат проверки текста.
 */
final class Result
{
    private string $text;

    /** @var list<Occurrence> */
    private array $occurrences;

    /**
     * @param list<Occurrence> $occurrences Вхождения, упорядоченные по смещению
     */
    public function __construct(string $text, array $occurrences)
    {
        $this->text = $text;
        $this->occurrences = $occurrences;
    }

    /** Плохих слов не найдено. */
    public function isClean(): bool
    {
        return $this->occurrences === [];
    }

    /**
     * Найденные слова словаря без повторов, в порядке появления в тексте.
     *
     * @return list<string>
     */
    public function words(): array
    {
        $seen = [];
        $words = [];
        foreach ($this->occurrences as $occurrence) {
            $word = $occurrence->word();
            if (isset($seen[$word])) {
                continue;
            }
            $seen[$word] = true;
            $words[] = $word;
        }

        return $words;
    }

    /**
     * Все вхождения плохих слов с их положением в тексте.
     *
     * @return list<Occurrence>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    /** Проверенный текст (с восстановленным UTF-8, если исходный был битым). */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Возвращает текст, в котором каждое найденное слово заменено повторением символа
     * по числу символов слова; остальной текст не меняется.
     *
     * $keepFirst и $keepLast оставляют открытыми столько символов в начале и в конце слова:
     * mask('@', 1, 1) превращает «хуй» в «х@й». Если после этого скрывать нечего,
     * слово закрывается целиком.
     */
    public function mask(string $char = '*', int $keepFirst = 0, int $keepLast = 0): string
    {
        if ($this->occurrences === []) {
            return $this->text;
        }

        $keepFirst = max(0, $keepFirst);
        $keepLast = max(0, $keepLast);

        $masked = '';
        $position = 0;
        foreach ($this->occurrences as $occurrence) {
            if ($occurrence->offset() < $position) {
                continue;
            }
            $masked .= substr($this->text, $position, $occurrence->offset() - $position);
            $masked .= self::maskFragment($occurrence->fragment(), $char, $keepFirst, $keepLast);
            $position = $occurrence->offset() + $occurrence->length();
        }

        return $masked . substr($this->text, $position);
    }

    private static function maskFragment(string $fragment, string $char, int $keepFirst, int $keepLast): string
    {
        $length = mb_strlen($fragment, 'UTF-8');
        $hidden = $length - $keepFirst - $keepLast;
        if ($hidden < 1) {
            return str_repeat($char, $length);
        }

        return mb_substr($fragment, 0, $keepFirst, 'UTF-8')
            . str_repeat($char, $hidden)
            . ($keepLast > 0 ? mb_substr($fragment, -$keepLast, null, 'UTF-8') : '');
    }
}
