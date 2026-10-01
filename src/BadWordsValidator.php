<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

/**
 * Проверяет текст по словарю: текст делится на слова, каждое слово ищется в словаре.
 *
 * Слово ищется целиком, без дефисов и по частям через дефис, в кириллической и латинской
 * форме, со схлопнутыми повторами букв и без цифр по краям. Короткие слова, записанные
 * подряд («с у к а», «х.у.й», «на х уй»), дополнительно проверяются склеенными: только по
 * точному словарю, без корней и без схлопывания повторов, и только пока между словами одни
 * пробелы или одни знаки без пробелов. Перевод строки и знак препинания с пробелом («ел, да»)
 * разделяют предложение, через них слова не склеиваются. Слово с дефисом или «@» участвует
 * в склейке своими частями: «иди-на-х-у-й» склеивается так же, как «иди на х у й». Слово словаря
 * короче трёх букв и слово до трёх символов без кириллицы находятся только буква в букву:
 * инициалы «Е.Б.» и коды «e6», «e6a» не мат.
 */
final class BadWordsValidator
{
    /** Слова не длиннее стольких символов склеиваются с соседними при проверке. */
    private const SHORT_WORD = 3;

    /** Наибольшее число коротких слов, которые склеиваются вместе. */
    private const MAX_RUN = 12;

    /**
     * Слова короче стольких букв находятся только буква в букву: ни склейкой, ни подменой
     * похожих символов. Иначе инициалы «Е.Б.» и коды «e6», «Е6», «E-6» давали бы «еб».
     */
    private const MIN_WORD = 3;

    /**
     * Знаки, по которым слово дополнительно делится на части: дефис («супер-хуйня») и «@».
     * «@» внутри слова читается и как буква «а» («сук@», «г@ндон»), и как разделитель адреса
     * или упоминания («хуй@mail.ru», «@хуйло», «блядь@»): проверяются оба прочтения.
     */
    private const PART_SEPARATORS = '/[-@]+/';

    private Dictionary $dictionary;

    private TextNormalizer $normalizer;

    /** @var array<string, true>|null Обычные короткие слова из data/short-words.php, ключами */
    private static ?array $ordinaryWords = null;

    public function __construct(Dictionary $dictionary, ?TextNormalizer $normalizer = null)
    {
        $this->dictionary = $dictionary;
        $this->normalizer = $normalizer ?? new TextNormalizer();
    }

    public function dictionary(): Dictionary
    {
        return $this->dictionary;
    }

    public function isClean(string $text): bool
    {
        return $this->check($text)->isClean();
    }

    public function check(string $text): Result
    {
        $text = $this->normalizer->scrub($text);
        $tokens = $this->normalizer->tokenize($text);

        $occurrences = [];
        // Для склейки коротких слов слово с дефисом или «@» распадается на части: «иди-на-х-у-й»
        // склеивается так же, как «иди на х у й». Части, покрытые найденным совпадением, не склеиваются.
        $runTokens = [];
        $runMatched = [];
        foreach ($tokens as $token) {
            $matches = $this->matchToken($token);
            foreach ($matches as [$word, $byRoot, $offset, $length]) {
                $occurrences[] = new Occurrence(
                    substr($token->fragment, $offset, $length),
                    $word,
                    $token->offset + $offset,
                    $length,
                    $byRoot
                );
            }
            foreach ($this->parts($token) as $part) {
                $relative = $part->offset - $token->offset;
                foreach ($matches as [, , $offset, $length]) {
                    if ($relative < $offset + $length && $offset < $relative + $part->length) {
                        $runMatched[count($runTokens)] = true;
                        break;
                    }
                }
                $runTokens[] = $part;
            }
        }

        $count = count($runTokens);
        for ($i = 0; $i < $count; $i++) {
            if (isset($runMatched[$i]) || !$this->isShort($runTokens[$i])) {
                continue;
            }
            $end = $i;
            while (
                $end + 1 < $count
                && !isset($runMatched[$end + 1])
                && $this->isShort($runTokens[$end + 1])
                && $this->isJoinable($text, $runTokens[$end], $runTokens[$end + 1])
            ) {
                $end++;
            }
            if ($end > $i) {
                $this->matchRun($text, $runTokens, $i, $end, $occurrences);
            }
            $i = $end;
        }

        usort($occurrences, static fn (Occurrence $a, Occurrence $b): int => $a->offset() <=> $b->offset());

        return new Result($text, $occurrences);
    }

    /**
     * Ищет слово в словаре. Слово с дефисами проверяется целиком, без дефисов и по частям,
     * слово с «@» целиком и по частям; точные совпадения на любом уровне важнее корневых:
     * «хуйня-блядь» даёт два слова словаря, а не одно корневое «хуйня-блядь». Совпавшая часть
     * отмечается в своих границах, чтобы «супер-хуйня» маскировалось как «супер-*****»,
     * а «хуй@mail.ru» как «***@mail.ru».
     *
     * @return list<array{0: string, 1: bool, 2: int, 3: int}> Слово словаря, признак совпадения
     *     по корню, смещение и длина найденного фрагмента в байтах внутри слова
     */
    private function matchToken(Token $token): array
    {
        if (strpbrk($token->fragment, '-@') === false) {
            $found = $this->matchWord($token->normalized, true);

            return $found === null ? [] : [[$found[0], $found[1], 0, $token->length]];
        }

        $partTokens = $this->parts($token);
        $parts = [];
        foreach ($partTokens as $part) {
            $parts[] = [$part->normalized, $part->offset - $token->offset, $part->length];
        }
        // Слово без дефисов проверяется, если части не складываются в обычную речь: «ел-да» это «ел, да».
        $whole = [$token->normalized];
        $joined = str_replace('-', '', $token->normalized);
        if ($joined !== $token->normalized && !$this->isOrdinarySpeech($partTokens, 0, count($partTokens) - 1)) {
            $whole[] = $joined;
        }

        // Точный словарь: слово целиком важнее частей («электро-фишер», «по-хуй»).
        foreach ($whole as $candidate) {
            $found = $this->matchWord($candidate, false);
            if ($found !== null) {
                return [[$found[0], $found[1], 0, $token->length]];
            }
        }
        // Части: каждая сначала по точному словарю, затем по корням, чтобы в «блядь-хуепутало»
        // найти и словарное «блядь», и корневое «хуепутало», а в «супер-хуепутало» отметить
        // только «хуепутало».
        $matches = $this->matchParts($parts, $this->dictionary->hasRoots());
        if ($matches !== [] || !$this->dictionary->hasRoots()) {
            return $matches;
        }

        // Корни целого слова, если ни одна часть не подошла.
        foreach ($whole as $candidate) {
            $found = $this->matchWord($candidate, true);
            if ($found !== null) {
                return [[$found[0], $found[1], 0, $token->length]];
            }
        }

        return [];
    }

    /**
     * Части слова по дефисам и «@» как отдельные токены со смещениями в тексте;
     * слово без этих знаков возвращается как есть.
     *
     * @return list<Token>
     */
    private function parts(Token $token): array
    {
        if (strpbrk($token->fragment, '-@') === false) {
            return [$token];
        }

        $parts = [];
        foreach (preg_split(self::PART_SEPARATORS, $token->fragment, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE) ?: [] as [$part, $offset]) {
            $parts[] = new Token($part, $this->normalizer->normalizeWord($part), $token->offset + $offset, strlen($part));
        }

        return $parts;
    }

    /**
     * @param list<array{0: string, 1: int, 2: int}> $parts Нормализованная часть, её смещение и длина внутри слова
     * @return list<array{0: string, 1: bool, 2: int, 3: int}>
     */
    private function matchParts(array $parts, bool $withRoots): array
    {
        $matches = [];
        foreach ($parts as [$normalized, $offset, $length]) {
            $found = $this->matchWord($normalized, $withRoots);
            if ($found !== null) {
                $matches[] = [$found[0], $found[1], $offset, $length];
            }
        }

        return $matches;
    }

    /**
     * Ищет слово как есть и без цифр по краям («хуйня1», «12хуйня34»): сначала оба написания
     * по точному словарю, затем оба по корням. Иначе с корнями «хуйня1» находилось бы как
     * корневое «хуйня1», а не как словарное «хуйня». По корням первым идёт написание без цифр,
     * чтобы в word() не попадали цифры, прочитанные как буквы («хуяндекс2о2ч»). Слово,
     * исключённое через without(), не проверяется по корням ни с цифрой на краю,
     * ни с растянутой буквой: «хуйня1» и «хуйняяя» так же чисты, как «хуйня».
     *
     * @return array{0: string, 1: bool}|null Слово словаря и признак совпадения по корню
     */
    private function matchWord(string $word, bool $withRoots): ?array
    {
        $candidates = [$word];
        // Просмотр назад делает шаблон линейным и без JIT: иначе ветка «\p{N}+$» перебиралась бы
        // заново с каждой цифры внутри слова, и слово с десятками тысяч цифр считалось секундами.
        $trimmed = preg_replace('/^\p{N}+|(?<!\p{N})\p{N}+$/u', '', $word);
        if (is_string($trimmed) && $trimmed !== '' && $trimmed !== $word) {
            $candidates[] = $trimmed;
        }

        $cyrillic = [];
        foreach ($candidates as $candidate) {
            foreach ($this->normalizer->variants($candidate) as $variant) {
                $found = $this->findExact($variant);
                if ($found !== null) {
                    return [$found, false];
                }
                $cyrillic[] = $this->normalizer->toCyrillic($variant);
            }
        }

        if (!$withRoots || !$this->dictionary->hasRoots()) {
            return null;
        }
        foreach ($cyrillic as $form) {
            if ($this->dictionary->isExcluded($form)) {
                return null;
            }
        }
        foreach (array_reverse($candidates) as $candidate) {
            foreach ($this->normalizer->variants($candidate) as $variant) {
                $form = $this->normalizer->toCyrillic($variant);
                if ($this->dictionary->matchesRoot($form)) {
                    return [$form, true];
                }
            }
        }

        return null;
    }

    /**
     * Ищет слово в точном словаре по формам в кириллице и латинице. Подмена похожих символов
     * не распространяется на два случая, где кодов и аббревиатур больше, чем мата:
     * слово словаря короче MIN_WORD («еб» ← «e6», «Е6») и короткое слово без единой
     * кириллической буквы («e6a» → «еба», «4mo» → «чмо», «xyi» → «хуи»). Такие слова
     * находятся только буква в букву; с корнями латиница по-прежнему проверяется.
     */
    private function findExact(string $word): ?string
    {
        $found = $this->dictionary->findExact($this->normalizer->forms($word));
        if ($found === null || $this->normalizer->normalizeWord($found) === $word) {
            return $found;
        }
        if (mb_strlen($found, 'UTF-8') < self::MIN_WORD) {
            return null;
        }
        if (mb_strlen($word, 'UTF-8') <= self::SHORT_WORD && preg_match('/\p{Cyrillic}/u', $word) !== 1) {
            return null;
        }

        return $found;
    }

    /**
     * Проверяет склейки коротких слов из отрезка токенов от $start до $end включительно:
     * с каждой позиции берётся самая длинная склейка не более чем из MAX_RUN слов, которая
     * есть в точном словаре. Окно скользит по отрезку, а не режет его на блоки: иначе слово
     * на границе двенадцатого и тринадцатого коротких слов никогда не собиралось бы.
     *
     * Склейка сверяется только с точным словарём и без схлопывания повторов. Корни с их
     * приставками превращали «у неё был» в «унеебыл», а схлопывание «её» → «е» превращало
     * «её без» в «ебез». Словарное слово, разбитое пробелами, находится и без того.
     *
     * @param list<Token> $tokens
     * @param list<Occurrence> $occurrences
     */
    private function matchRun(string $text, array $tokens, int $start, int $end, array &$occurrences): void
    {
        $from = $start;
        while ($from < $end) {
            $matched = false;
            for ($to = min($end, $from + self::MAX_RUN - 1); $to > $from; $to--) {
                $joined = '';
                for ($k = $from; $k <= $to; $k++) {
                    $joined .= $tokens[$k]->normalized;
                }
                if (mb_strlen($joined, 'UTF-8') < self::MIN_WORD) {
                    break; // Дальше склейки только короче: «Е.Б.» не должно давать «еб».
                }
                $found = $this->findExact($joined);
                if ($found !== null && !$this->isOrdinarySpeech($tokens, $from, $to)) {
                    $offset = $tokens[$from]->offset;
                    $length = $tokens[$to]->offset + $tokens[$to]->length - $offset;
                    $occurrences[] = new Occurrence(substr($text, $offset, $length), $found, $offset, $length);
                    $from = $to + 1;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $from++;
            }
        }
    }

    /**
     * Является ли цепочка обычной речью без запятой: каждое слово от $from до $to есть в списке
     * обычных коротких слов («ел да», «иди от», «ах у ели»), и хотя бы одно длиннее буквы.
     * Цепочка из одиночных букв («с у к а») речью не считается и склеивается всегда.
     *
     * @param list<Token> $tokens
     */
    private function isOrdinarySpeech(array $tokens, int $from, int $to): bool
    {
        $ordinary = self::ordinaryWords();
        $hasWord = false;
        for ($k = $from; $k <= $to; $k++) {
            $word = $tokens[$k]->normalized;
            if (!isset($ordinary[$word])) {
                return false;
            }
            if (mb_strlen($word, 'UTF-8') > 1) {
                $hasWord = true;
            }
        }

        return $hasWord;
    }

    /**
     * Обычные короткие слова русского языка из data/short-words.php, ключами.
     *
     * @return array<string, true>
     */
    private static function ordinaryWords(): array
    {
        if (self::$ordinaryWords === null) {
            /** @var list<string> $words */
            $words = require __DIR__ . '/../data/short-words.php';
            self::$ordinaryWords = array_fill_keys($words, true);
        }

        return self::$ordinaryWords;
    }

    /**
     * Можно ли склеить два соседних коротких слова: между ними либо одни пробелы («с у к а»),
     * либо одни знаки без пробелов («х.у.й»). Перевод строки и знак препинания с пробелом
     * («ел, да», «ел. Да») разделяют предложение, через них слова не склеиваются.
     */
    private function isJoinable(string $text, Token $left, Token $right): bool
    {
        $from = $left->offset + $left->length;
        // Невидимые символы и диакритика в промежутке не в счёт: «х \u{200B} у й» склеивается как «х  у й».
        $gap = (string) preg_replace('/[\p{M}\p{Cf}]+/u', '', substr($text, $from, $right->offset - $from));

        // \z, а не $: перед завершающим переводом строки $ совпадает, и «ел.\nДа» считалось бы склейкой через точку.
        return preg_match('/^(?:\h+|\S+)\z/u', $gap) === 1;
    }

    private function isShort(Token $token): bool
    {
        return $token->normalized !== '' && mb_strlen($token->normalized, 'UTF-8') <= self::SHORT_WORD;
    }
}
