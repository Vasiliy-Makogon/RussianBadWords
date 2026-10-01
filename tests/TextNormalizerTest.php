<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords\Tests;

use Krugozor\RussianBadWords\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase
{
    private TextNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TextNormalizer();
    }

    public function testTokenizeReturnsFragmentsWithByteOffsets(): void
    {
        $tokens = $this->normalizer->tokenize('Привет, мир!');

        self::assertCount(2, $tokens);
        self::assertSame('Привет', $tokens[0]->fragment);
        self::assertSame('привет', $tokens[0]->normalized);
        self::assertSame(0, $tokens[0]->offset);
        self::assertSame(12, $tokens[0]->length);
        self::assertSame('мир', $tokens[1]->fragment);
        self::assertSame(14, $tokens[1]->offset);
        self::assertSame(6, $tokens[1]->length);
    }

    public function testHyphenatedWordIsOneToken(): void
    {
        $tokens = $this->normalizer->tokenize('электро-фишер и fisher-f-3500');

        self::assertSame(['электро-фишер', 'и', 'fisher-f-3500'], array_map(
            static fn ($token): string => $token->normalized,
            $tokens
        ));
    }

    public function testDanglingHyphensAndPunctuationAreSeparators(): void
    {
        $tokens = $this->normalizer->tokenize("слово- «второе»… третье—четвёртое_пятое\u{00A0}шестое");

        self::assertSame(
            ['слово', 'второе', 'третье', 'четвертое', 'пятое', 'шестое'],
            array_map(static fn ($token): string => $token->normalized, $tokens)
        );
    }

    public function testEdgeHyphensAreTrimmedWithOffsets(): void
    {
        $tokens = $this->normalizer->tokenize('--слово- -ещё');

        self::assertCount(2, $tokens);
        self::assertSame('слово', $tokens[0]->fragment);
        self::assertSame(2, $tokens[0]->offset);
        self::assertSame(10, $tokens[0]->length);
        self::assertSame('ещё', $tokens[1]->fragment);
        self::assertSame(15, $tokens[1]->offset);
        self::assertSame(6, $tokens[1]->length);
    }

    public function testInvisibleOnlyFragmentsAreNotTokens(): void
    {
        self::assertSame([], $this->normalizer->tokenize("\u{00AD}"));
        self::assertSame([], $this->normalizer->tokenize(" \u{200B} \u{FEFF} "));
        self::assertSame(['х', 'у'], array_map(
            static fn ($token): string => $token->normalized,
            $this->normalizer->tokenize("х \u{200B} у")
        ));
    }

    public function testLongHyphenChainDoesNotExhaustPcre(): void
    {
        // Шаблон вида «слово(?:-+слово)*» исчерпывал JIT-стек PCRE на 8192 звеньях,
        // а ошибка движка превращалась в пустой список слов.
        $tokens = $this->normalizer->tokenize('хуйня ' . str_repeat('1-', 20000) . '1');

        self::assertCount(2, $tokens);
        self::assertSame('хуйня', $tokens[0]->normalized);
        self::assertSame(40001, $tokens[1]->length);
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalizeWord(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalizeWord($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function normalizeProvider(): iterable
    {
        yield 'верхний регистр и ё' => ['ЁЖИК', 'ежик'];
        yield 'комбинируемое ударение' => ["су\u{0301}ка", 'сука'];
        yield 'мягкий перенос' => ["су\u{00AD}ка", 'сука'];
        yield 'пробел нулевой ширины' => ["су\u{200B}ка", 'сука'];
        yield 'соединитель нулевой ширины' => ["су\u{200D}ка", 'сука'];
    }

    /**
     * @dataProvider foldProvider
     */
    public function testFold(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->fold($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function foldProvider(): iterable
    {
        yield 'нижний регистр, ё сохраняется' => ['ЁЖИК', 'ёжик'];
        yield 'комбинируемое ударение' => ["су\u{0301}ка", 'сука'];
        yield 'мягкий перенос и пробел нулевой ширины' => ["су\u{00AD}к\u{200B}а", 'сука'];
        yield 'латиница и дефисы не меняются' => ['Fisher-F-3500', 'fisher-f-3500'];
    }

    public function testDecomposedLettersAreComposedBeforeStripping(): void
    {
        // Работает и без intl: «й» и «ё» из двух знаков склеиваются сами.
        self::assertSame('хуй', $this->normalizer->fold("ху\u{0438}\u{0306}"));
        self::assertSame('ёжик', $this->normalizer->fold("\u{0415}\u{0308}жик"));
        self::assertSame('ежик', $this->normalizer->normalizeWord("\u{0415}\u{0308}жик"));
    }

    public function testCaseIsLoweredBeforeStrippingMarks(): void
    {
        // Строчная форма турецкой «İ» это «i» с точкой сверху U+0307: точка срезается, а не остаётся в слове.
        self::assertSame('пiзда', $this->normalizer->fold('пİзда'));
        self::assertSame('fisher', $this->normalizer->fold('FİSHER'));
    }

    public function testCompatibilityFormsAreFolded(): void
    {
        if (!class_exists(\Normalizer::class)) {
            self::markTestSkipped('Нужно расширение intl');
        }

        self::assertSame('cyka', $this->normalizer->fold('ｃｙｋａ'));
        self::assertSame('fisher', $this->normalizer->fold('ﬁsher'));
        self::assertSame('xyй', $this->normalizer->fold('𝐱𝐲й'));
    }

    /**
     * @dataProvider trimProvider
     */
    public function testTrim(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->trim($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function trimProvider(): iterable
    {
        yield 'ASCII-пробелы, табуляция и перевод строки' => [" \tсука\r\n", 'сука'];
        yield 'неразрывный пробел' => ["\u{00A0}сука\u{00A0}", 'сука'];
        yield 'узкий неразрывный и идеографический пробелы' => ["\u{202F}сука\u{3000}", 'сука'];
        yield 'BOM и пробел нулевой ширины' => ["\u{FEFF}сука\u{200B}", 'сука'];
        yield 'мягкий перенос' => ["\u{00AD}сука\u{00AD}", 'сука'];
        yield 'смесь видимого и невидимого' => ["\u{FEFF} \u{00A0}сука\u{200B}\t", 'сука'];
        yield 'только невидимые символы' => ["\u{00A0}\u{200B}\u{FEFF}", ''];
        yield 'пробел внутри сохраняется' => [" на\u{00A0}хуй ", "на\u{00A0}хуй"];
        yield 'регистр и ё не меняются' => [' Ёжик ', 'Ёжик'];
    }

    public function testTrimScrubsInvalidUtf8(): void
    {
        $trimmed = $this->normalizer->trim(" \xFF\xFEсука ");

        self::assertSame(1, preg_match('//u', $trimmed));
        self::assertSame($this->normalizer->scrub("\xFF\xFEсука"), $trimmed);
    }

    public function testFormsMapHomoglyphsBothWays(): void
    {
        self::assertContains('сука', $this->normalizer->forms('cyka'));
        self::assertContains('сука', $this->normalizer->forms('cукa'));
        self::assertContains('porn', $this->normalizer->forms("p\u{043E}rn"));
        self::assertContains('пизда', $this->normalizer->forms('пи3да'));
        self::assertContains('долбоеб', $this->normalizer->forms('д0лб0е6'));
        self::assertSame(['сука', 'cyka'], $this->normalizer->forms('сука'));
        self::assertSame(['12з', '123'], $this->normalizer->forms('123'));
    }

    public function testVariantsCollapseRepeatedLetters(): void
    {
        self::assertContains('блять', $this->normalizer->variants('бляяяять'));
        self::assertContains('сука', $this->normalizer->variants('сссууука'));
        self::assertSame('ссать', $this->normalizer->variants('ссать')[0]);
        self::assertSame(['ахуеннно', 'ахуенно', 'ахуено'], $this->normalizer->variants('ахуеннно'));
        self::assertSame(['ссссать', 'ссать', 'сать'], $this->normalizer->variants('ссссать'));
    }

    public function testScrubReplacesInvalidUtf8(): void
    {
        $scrubbed = $this->normalizer->scrub("\xFF\xFEсука");

        self::assertSame(1, preg_match('//u', $scrubbed));
        self::assertStringContainsString('сука', $scrubbed);
        self::assertSame(['сука'], array_map(
            static fn ($token): string => $token->normalized,
            $this->normalizer->tokenize("\xFF\xFE сука")
        ));
    }
}
