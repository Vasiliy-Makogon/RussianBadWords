<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

/**
 * Слово текста: исходный фрагмент, его нормальная форма и положение в строке.
 *
 * @internal
 */
final class Token
{
    /** @var string Фрагмент исходного текста */
    public string $fragment;

    /** @var string Нормальная форма (см. TextNormalizer::normalizeWord) */
    public string $normalized;

    /** @var int Смещение фрагмента в байтах */
    public int $offset;

    /** @var int Длина фрагмента в байтах */
    public int $length;

    public function __construct(string $fragment, string $normalized, int $offset, int $length)
    {
        $this->fragment = $fragment;
        $this->normalized = $normalized;
        $this->offset = $offset;
        $this->length = $length;
    }
}
