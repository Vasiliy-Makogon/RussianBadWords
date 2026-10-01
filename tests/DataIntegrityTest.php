<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords\Tests;

use Krugozor\RussianBadWords\Dictionary;
use Krugozor\RussianBadWords\TextNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Проверки файлов данных: формат, регистр, отсутствие дублей и пересечений между уровнями.
 */
final class DataIntegrityTest extends TestCase
{
    /**
     * @dataProvider dataFileProvider
     */
    public function testDataFileIsWellFormed(string $file): void
    {
        $data = require $file;
        self::assertIsArray($data, $file);
        $normalizer = new TextNormalizer();

        if (isset($data['roots'])) {
            foreach ($data['roots'] as $root) {
                self::assertIsString($root);
                self::assertNotFalse(@preg_match('~(?:' . $root . ')~u', ''), $root);
            }
            foreach ($data['exceptions'] as $exception) {
                self::assertIsString($exception);
                self::assertSame($exception, mb_strtolower($exception, 'UTF-8'));
                self::assertSame($exception, $normalizer->toCyrillic($exception), "исключение должно быть в кириллице: {$exception}");
            }

            return;
        }

        $seen = [];
        foreach ($data as $key => $word) {
            self::assertIsInt($key, 'файл должен возвращать список');
            self::assertIsString($word);
            self::assertSame(1, preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', $word), "недопустимые символы: {$word}");
            self::assertSame($word, mb_strtolower($word, 'UTF-8'), "слово должно быть в нижнем регистре: {$word}");
            $normalized = $normalizer->normalizeWord($word);
            self::assertArrayNotHasKey($normalized, $seen, "дубль: {$word}");
            $seen[$normalized] = true;
        }
        self::assertNotEmpty($seen, $file);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function dataFileProvider(): iterable
    {
        $dataDir = dirname(__DIR__) . '/data';
        foreach (array_merge(glob($dataDir . '/*.php') ?: [], glob($dataDir . '/stop-words/*.php') ?: []) as $file) {
            yield substr($file, strlen($dataDir) + 1) => [$file];
        }
    }

    public function testTiersDoNotOverlap(): void
    {
        $tiers = [
            'profanity' => Dictionary::profanity(),
            'rude' => Dictionary::rude(),
            'insults' => Dictionary::insults(),
            'anatomy' => Dictionary::anatomy(),
        ];
        $normalizer = new TextNormalizer();
        $owner = [];
        foreach ($tiers as $name => $dictionary) {
            foreach ($dictionary->words() as $word) {
                $normalized = $normalizer->normalizeWord($word);
                self::assertArrayNotHasKey($normalized, $owner, "«{$word}» есть и в {$name}, и в " . ($owner[$normalized] ?? ''));
                $owner[$normalized] = $name;
            }
        }
    }

    public function testGeneralVocabularyIsNotInProfanity(): void
    {
        $validator = Dictionary::profanity()->merge(Dictionary::rude(), Dictionary::insults(), Dictionary::anatomy());
        $normalizer = new TextNormalizer();
        foreach (['поставить', 'сила', 'прикинуть', 'школьницы', 'передок', 'струк', 'клоака'] as $word) {
            if ($word === 'клоака') {
                self::assertNotNull(Dictionary::anatomy()->findExact($normalizer->forms($word)), $word);
                continue;
            }
            self::assertNull($validator->findExact($normalizer->forms($word)), $word);
        }
    }
}
