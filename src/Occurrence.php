<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

/**
 * Найденное в тексте плохое слово: фрагмент исходного текста, слово словаря и положение фрагмента.
 */
final class Occurrence
{
    private string $fragment;

    private string $word;

    private int $offset;

    private int $length;

    private bool $byRoot;

    public function __construct(string $fragment, string $word, int $offset, int $length, bool $byRoot = false)
    {
        $this->fragment = $fragment;
        $this->word = $word;
        $this->offset = $offset;
        $this->length = $length;
        $this->byRoot = $byRoot;
    }

    /** Фрагмент исходного текста, как его написал пользователь. */
    public function fragment(): string
    {
        return $this->fragment;
    }

    /** Слово словаря, с которым совпал фрагмент; при совпадении по корню - нормальная форма фрагмента. */
    public function word(): string
    {
        return $this->word;
    }

    /** Смещение фрагмента в байтах от начала текста. */
    public function offset(): int
    {
        return $this->offset;
    }

    /** Длина фрагмента в байтах. */
    public function length(): int
    {
        return $this->length;
    }

    /** Найдено по корню, а не по точному совпадению со словом словаря. */
    public function isByRoot(): bool
    {
        return $this->byRoot;
    }
}
