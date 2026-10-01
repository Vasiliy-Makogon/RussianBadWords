<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords\Tests;

use InvalidArgumentException;
use Krugozor\RussianBadWords\Dictionary;
use PHPUnit\Framework\TestCase;

final class DictionaryTest extends TestCase
{
    public function testFromWordsDedupesByNormalForm(): void
    {
        $dictionary = Dictionary::fromWords(['Сука', 'сука', 'ёбаный', 'ебаный', '  ', '']);

        self::assertCount(2, $dictionary);
        self::assertSame(['Сука', 'ёбаный'], $dictionary->words());
    }

    public function testWordsAreStoredWithoutSurroundingWhitespace(): void
    {
        $dictionary = Dictionary::fromWords([' Хуй ', "пизда\n", "\u{00A0}блядь\u{00A0}", "\u{FEFF}ебать\u{200B}"]);

        self::assertSame(['Хуй', 'пизда', 'блядь', 'ебать'], $dictionary->words());
        self::assertSame('Хуй', $dictionary->findExact(['хуй']));
        self::assertSame('блядь', $dictionary->findExact(['блядь']));
        self::assertSame(['Хуй', 'ебать'], $dictionary->without(["пизда\n", "блядь\u{00A0}"])->words());
        self::assertSame(['корень'], Dictionary::empty()->withExceptions(["\u{00A0}корень "])->exceptions());
    }

    public function testWithAndWithoutReturnNewDictionaries(): void
    {
        $a = Dictionary::fromWords(['сука']);
        $b = $a->with(['блядь']);
        $c = $b->without(['сука']);

        self::assertCount(1, $a);
        self::assertCount(2, $b);
        self::assertSame(['блядь'], $c->words());
        self::assertSame('сука', $a->findExact(['сука']));
        self::assertNull($c->findExact(['сука']));
    }

    public function testWordsWithIdenticalFormsAreOneWord(): void
    {
        $dictionary = Dictionary::fromWords(['сука', 'cyka']);

        self::assertSame(['сука'], $dictionary->words());
        self::assertSame('сука', $dictionary->findExact(['cyka']));

        $without = $dictionary->with(['блядь'])->without(['cyka']);
        self::assertSame(['блядь'], $without->words());
        self::assertNull($without->findExact(['сука']));
    }

    public function testWithoutKeepsWordsThatShareOnlySomeForms(): void
    {
        // «х0й» добавлено первым и заняло общую кириллическую форму «хой».
        $dictionary = Dictionary::fromWords(['х0й', 'хой'])->withRoots(['^хо'])->without(['х0й']);

        self::assertSame(['хой'], $dictionary->words());
        self::assertSame('хой', $dictionary->findExact(['хой']));
        self::assertSame('хой', $dictionary->findExact(['xoй']));
        self::assertNull($dictionary->findExact(['x0й']));
        self::assertTrue($dictionary->matchesRoot('хой'));

        // Общая форма переходит к оставшемуся слову независимо от порядка добавления.
        $reversed = Dictionary::fromWords(['хой', 'х0й'])->without(['хой']);

        self::assertSame(['х0й'], $reversed->words());
        self::assertSame('х0й', $reversed->findExact(['хой']));
        self::assertSame('х0й', $reversed->findExact(['x0й']));
        self::assertNull($reversed->findExact(['xoй']));
    }

    public function testNumericWordsSurviveWithout(): void
    {
        self::assertSame(['228'], Dictionary::fromWords(['228', '1488'])->without(['1488'])->words());
    }

    public function testMergeCombinesWordsRootsAndExceptions(): void
    {
        $words = Dictionary::fromWords(['слово']);
        $roots = Dictionary::empty()->withRoots(['^кор'])->withExceptions(['корень']);
        $merged = $words->merge($roots, Dictionary::fromWords(['другое']));

        self::assertSame(['слово', 'другое'], $merged->words());
        self::assertTrue($merged->hasRoots());
        self::assertSame(['^кор'], $merged->roots());
        self::assertSame(['корень'], $merged->exceptions());
        self::assertFalse($words->hasRoots());
    }

    public function testRootsRespectExceptionsAndWithout(): void
    {
        $dictionary = Dictionary::empty()->withRoots(['^кор'])->withExceptions(['корень']);

        self::assertTrue($dictionary->matchesRoot('корова'));
        self::assertFalse($dictionary->matchesRoot('корень'));
        self::assertFalse($dictionary->matchesRoot('коренья'));
        self::assertFalse($dictionary->without(['корова'])->matchesRoot('корова'));
        self::assertTrue($dictionary->without(['корова'])->matchesRoot('корма'));
    }

    public function testIsExcludedKnowsWordsRemovedByWithout(): void
    {
        $dictionary = Dictionary::profanity()->without(['хуйня']);

        self::assertTrue($dictionary->isExcluded('хуйня'));
        self::assertFalse($dictionary->isExcluded('блядь'));
        self::assertFalse(Dictionary::profanity()->isExcluded('хуйня'));
    }

    public function testExceptionsAreNormalizedLikeTextWords(): void
    {
        // Латинская «C» и комбинируемое ударение: так исключение приходит из скопированного текста.
        $dictionary = Dictionary::empty()->withRoots(['страх'])->withExceptions(["Cтраху\u{0301}"]);

        self::assertSame(['страху'], $dictionary->exceptions());
        self::assertFalse($dictionary->matchesRoot('застрахуй'));
        self::assertTrue($dictionary->matchesRoot('страхи'));
        self::assertSame(['страху'], $dictionary->merge($dictionary)->exceptions());
    }

    public function testInvalidRootIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dictionary::empty()->withRoots(['(']);
    }

    public function testRootsIncompatibleWithEachOtherAreRejected(): void
    {
        // Каждый корень верен сам по себе, но в общем шаблоне одно имя получает разные номера групп.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('несовместимы');

        Dictionary::empty()->withRoots(['(?<a>х)(?<b>у)'])->withRoots(['(?<b>п)(?<a>и)']);
    }

    public function testGroupsAndBackreferencesStayLocalToEachRoot(): void
    {
        $named = Dictionary::empty()->withRoots(['(?<a>ху)', '(?<a>пи)']);
        self::assertTrue($named->matchesRoot('хуй'));
        self::assertTrue($named->matchesRoot('пизда'));

        $numbered = Dictionary::empty()->withRoots(['(а)\1', '(б)\1']);
        self::assertTrue($numbered->matchesRoot('аа'));
        self::assertTrue($numbered->matchesRoot('бб'), 'ссылка \1 второго корня указывает на его собственную группу');
        self::assertFalse($numbered->matchesRoot('аб'));
    }

    public function testPcreFailureWhileMatchingRootsIsAnException(): void
    {
        $dictionary = Dictionary::empty()->withRoots(['(\w+\w+)+$']);
        $jit = ini_set('pcre.jit', '0');
        $limit = ini_set('pcre.backtrack_limit', '100');

        try {
            $this->expectException(\RuntimeException::class);
            $dictionary->matchesRoot(str_repeat('а', 30) . '!');
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }

    public function testBuiltInDictionariesAreLoaded(): void
    {
        self::assertGreaterThan(2000, count(Dictionary::profanity()));
        self::assertGreaterThan(300, count(Dictionary::rude()));
        self::assertGreaterThan(40, count(Dictionary::insults()));
        self::assertGreaterThan(40, count(Dictionary::anatomy()));
        self::assertFalse(Dictionary::profanity()->hasRoots());
        self::assertTrue(Dictionary::profanityRoots()->hasRoots());
        self::assertCount(0, Dictionary::profanityRoots());
        self::assertSame(Dictionary::profanity(), Dictionary::profanity());
    }

    public function testStopWordsAreSplitIntoCategories(): void
    {
        $categories = Dictionary::stopWordCategories();

        self::assertContains('drugs', $categories);
        self::assertContains('extremism', $categories);
        self::assertContains('fishing', $categories);
        self::assertGreaterThan(200, count(Dictionary::stopWords(['drugs'])));

        $total = 0;
        foreach ($categories as $category) {
            $total += count(Dictionary::stopWords([$category]));
        }
        self::assertSame($total, count(Dictionary::stopWords()));
    }

    public function testUnknownStopWordCategoryIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('nope');

        Dictionary::stopWords(['nope']);
    }

    public function testFromFileRequiresExistingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dictionary::fromFile(__DIR__ . '/missing-dictionary.php');
    }

    public function testFromFileSupportsPlainListAndStructuredData(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'dict');
        $structured = tempnam(sys_get_temp_dir(), 'dict');
        file_put_contents($plain, "<?php return ['Тест', 'слово'];");
        file_put_contents($structured, "<?php return ['words' => ['тест'], 'roots' => ['^кор'], 'exceptions' => ['корень']];");

        try {
            self::assertSame(['Тест', 'слово'], Dictionary::fromFile($plain)->words());

            $dictionary = Dictionary::fromFile($structured);
            self::assertSame(['тест'], $dictionary->words());
            self::assertTrue($dictionary->matchesRoot('корова'));
            self::assertFalse($dictionary->matchesRoot('корень'));
        } finally {
            unlink($plain);
            unlink($structured);
        }
    }
}
