<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

use RuntimeException;

/**
 * Разбивает текст на слова и приводит их к виду, по которому ведётся поиск в словаре.
 *
 * Нормализация слова: восстановление валидного UTF-8, нормализация NFKC (при наличии ext-intl),
 * нижний регистр, удаление диакритики и невидимых форматирующих символов, «ё» → «е».
 * Для сравнения со словарём у слова строятся формы в кириллице и латинице по таблице
 * похожих символов, а также варианты со схлопнутыми повторами букв («бляяяять» → «блять»).
 */
final class TextNormalizer
{
    /**
     * Слово: буквы, диакритика, цифры, невидимые форматирующие символы, «@» и дефисы
     * («электро-фишер», «fisher-f-3500»); дефисы по краям срезает tokenize().
     * Дефис стоит в символьном классе намеренно: шаблон вида «слово(?:-+слово)*» хранит
     * кадр отката на каждое дефисное звено и на цепочке из 8192 звеньев исчерпывает JIT-стек PCRE.
     */
    private const TOKEN = '/[\p{L}\p{M}\p{N}\p{Cf}@-]+/u';

    /** Диакритика и невидимые форматирующие символы: мягкий перенос, пробел нулевой ширины и т. п. */
    private const STRIP = '/[\p{M}\p{Cf}]+/u';

    /**
     * Пробельные и невидимые символы по краям слова. Под модификатором «u» в \s входят
     * и юникодные пробелы: неразрывный U+00A0, узкий неразрывный U+202F, идеографический U+3000.
     * Просмотр назад во второй ветке делает шаблон линейным без JIT (см. BadWordsValidator::matchWord()).
     */
    private const TRIM = '/^[\s\p{Cf}]+|(?<![\s\p{Cf}])[\s\p{Cf}]+$/u';

    /** Латинские буквы, цифры и знаки, которыми подменяют похожие кириллические буквы. */
    private const TO_CYRILLIC = [
        'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н', 'i' => 'и', 'k' => 'к', 'm' => 'м',
        'n' => 'п', 'o' => 'о', 'p' => 'р', 'r' => 'г', 't' => 'т', 'u' => 'и', 'w' => 'ш', 'x' => 'х',
        'y' => 'у', 'z' => 'з', 'і' => 'и', 'ї' => 'и', 'ў' => 'у',
        '0' => 'о', '3' => 'з', '4' => 'ч', '6' => 'б', '@' => 'а',
    ];

    /** Кириллические буквы, которыми подменяют похожие латинские. */
    private const TO_LATIN = [
        'а' => 'a', 'в' => 'b', 'с' => 'c', 'е' => 'e', 'н' => 'h', 'и' => 'i', 'к' => 'k', 'м' => 'm',
        'п' => 'n', 'о' => 'o', 'р' => 'p', 'г' => 'r', 'т' => 't', 'ш' => 'w', 'х' => 'x', 'у' => 'y',
        'з' => 'z', '@' => 'a',
    ];

    /**
     * Возвращает текст с валидным UTF-8: невалидные байты заменяются знаком «?».
     */
    public function scrub(string $text): string
    {
        if ($text === '' || preg_match('//u', $text) === 1) {
            return $text;
        }

        return (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * Разбивает текст на слова. Смещения и длины считаются в байтах исходной строки.
     *
     * @return list<Token>
     * @throws RuntimeException Если PCRE не смог разобрать текст. Ошибка движка не должна
     *     превращаться в «слов нет»: иначе такой текст прошёл бы проверку как чистый
     */
    public function tokenize(string $text): array
    {
        $text = $this->scrub($text);
        if ($text === '') {
            return [];
        }
        if (preg_match_all(self::TOKEN, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new RuntimeException(sprintf('Не удалось разбить текст на слова, код ошибки PCRE: %d', preg_last_error()));
        }

        $tokens = [];
        foreach ($matches[0] as [$fragment, $offset]) {
            $leading = strspn($fragment, '-');
            $fragment = trim($fragment, '-');
            $normalized = $this->normalizeWord($fragment);
            // Фрагмент из одних дефисов или невидимых символов словом не является: иначе одиночный
            // пробел нулевой ширины между разнесёнными буквами («х \u{200B} у й») обрывал бы склейку.
            if ($fragment === '' || $normalized === '') {
                continue;
            }
            $tokens[] = new Token($fragment, $normalized, (int) $offset + $leading, strlen($fragment));
        }

        return $tokens;
    }

    /**
     * Приводит слово к нормальной форме: нижний регистр, без диакритики и невидимых символов, «ё» → «е».
     */
    public function normalizeWord(string $word): string
    {
        return str_replace('ё', 'е', $this->fold($word));
    }

    /**
     * Приводит слово к нижнему регистру без диакритики и невидимых форматирующих символов.
     * Сначала восстанавливается валидный UTF-8 и выполняется нормализация NFKC (при наличии
     * ext-intl): разложенные «й» и «ё» склеиваются прежде, чем срежутся комбинируемые знаки,
     * а совместимые формы букв сводятся к обычным: полноширинные «ｃｙｋａ», лигатура «ﬁ»,
     * математический жирный «𝐱». Без intl хотя бы «й» и «ё» из двух знаков склеиваются сами.
     * Регистр понижается до среза знаков: строчная форма турецкой «İ» это «i» с точкой сверху,
     * и точка должна срезаться, а не оставаться в слове.
     * В отличие от normalizeWord(), «ё» сохраняется: слово остаётся в привычном написании,
     * например в списке найденных слов.
     */
    public function fold(string $word): string
    {
        $word = $this->scrub($word);

        if (class_exists(\Normalizer::class)) {
            $composed = \Normalizer::normalize($word, \Normalizer::FORM_KC);
            if (is_string($composed)) {
                $word = $composed;
            }
        } else {
            $word = str_replace(["и\u{0306}", "И\u{0306}", "е\u{0308}", "Е\u{0308}"], ['й', 'Й', 'ё', 'Ё'], $word);
        }

        $word = mb_strtolower($word, 'UTF-8');

        return (string) preg_replace(self::STRIP, '', $word);
    }

    /**
     * Срезает пробельные и невидимые символы по краям слова. Штатный trim() знает только
     * ASCII-пробелы, а слово, скопированное из документа или с веб-страницы, часто приходит
     * с неразрывным пробелом по краю. Пробелы внутри слова сохраняются.
     */
    public function trim(string $word): string
    {
        return (string) preg_replace(self::TRIM, '', $this->scrub($word));
    }

    /**
     * Варианты слова со схлопнутыми повторами букв: само слово, затем повторы из трёх и более
     * букв сведены к двум, затем к одной, затем любые повторы сведены к одной. Вариант с двумя
     * буквами нужен словам с законным удвоением: «ахуеннно» → «ахуенно», «сссать» → «ссать».
     *
     * @return list<string>
     */
    public function variants(string $word): array
    {
        $variants = [$word];
        foreach ([['/(.)\1{2,}/u', '$1$1'], ['/(.)\1{2,}/u', '$1'], ['/(.)\1+/u', '$1']] as [$pattern, $replacement]) {
            $collapsed = preg_replace($pattern, $replacement, $word);
            if (is_string($collapsed) && !in_array($collapsed, $variants, true)) {
                $variants[] = $collapsed;
            }
        }

        return $variants;
    }

    /**
     * Формы слова в кириллице и в латинице по таблице похожих символов.
     * Слово из словаря и слово из текста сравниваются по этим формам, поэтому
     * подмена любого числа букв в любом направлении не мешает совпадению.
     *
     * @return list<string>
     */
    public function forms(string $word): array
    {
        $cyrillic = $this->toCyrillic($word);
        $latin = $this->toLatin($word);

        return $cyrillic === $latin ? [$cyrillic] : [$cyrillic, $latin];
    }

    public function toCyrillic(string $word): string
    {
        return strtr($word, self::TO_CYRILLIC);
    }

    public function toLatin(string $word): string
    {
        return strtr($word, self::TO_LATIN);
    }
}
