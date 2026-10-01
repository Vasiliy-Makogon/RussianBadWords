<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords\Tests;

use Krugozor\RussianBadWords\AbstractBadWordsValidator;
use Krugozor\RussianBadWords\Items\ProfanityWordsValidator;
use Krugozor\RussianBadWords\Items\StopWordsValidator;
use PHPUnit\Framework\TestCase;

/**
 * Совместимость с API 1.x.
 */
final class LegacyApiTest extends TestCase
{
    protected function tearDown(): void
    {
        ProfanityWordsValidator::$words = [];
        StopWordsValidator::$words = [];
    }

    public function testProfanityWordsValidatorKeepsOldContract(): void
    {
        $validator = new ProfanityWordsValidator('Ну и немного нембутала, хуйня');

        self::assertFalse($validator->validate());
        self::assertSame(['хуйня'], $validator->getFailedWords());
        self::assertTrue((new ProfanityWordsValidator('чистый текст'))->validate());
    }

    public function testStopWordsValidatorReturnsLowercasedFragments(): void
    {
        $validator = new StopWordsValidator('Продам_электрo-фишер.fisher-f-3500 не дорого!');

        self::assertFalse($validator->validate());
        self::assertSame(['электрo-фишер', 'fisher-f-3500'], $validator->getFailedWords());
    }

    public function testHyphenatedWordYieldsEachMatchingPart(): void
    {
        $validator = new ProfanityWordsValidator('ну ты и хуйня-блядь');

        self::assertFalse($validator->validate());
        self::assertSame(['хуйня', 'блядь'], $validator->getFailedWords());
    }

    public function testFailedWordsKeepYoAndDropInvisibleCharacters(): void
    {
        $validator = new ProfanityWordsValidator("Ёб\u{200B}нутый");

        self::assertFalse($validator->validate());
        self::assertSame(['ёбнутый'], $validator->getFailedWords());
    }

    public function testEmptyAndZeroTextsAreValid(): void
    {
        self::assertTrue((new ProfanityWordsValidator('   '))->validate());
        self::assertTrue((new ProfanityWordsValidator('0'))->validate());
        self::assertSame([], (new ProfanityWordsValidator('0'))->getFailedWords());
    }

    public function testUserSubclassWithOwnWords(): void
    {
        $validator = new class('текст со своимсловом внутри') extends AbstractBadWordsValidator {
            public static array $words = ['своимсловом'];
        };

        self::assertFalse($validator->validate());
        self::assertSame(['своимсловом'], $validator->getFailedWords());
    }

    public function testAdditionalWordsOnBuiltInValidator(): void
    {
        self::assertTrue((new ProfanityWordsValidator('новоеслово'))->validate());

        ProfanityWordsValidator::$words[] = 'новоеслово';

        self::assertFalse((new ProfanityWordsValidator('новоеслово'))->validate());
        self::assertFalse((new ProfanityWordsValidator('хуйня'))->validate());
    }

    public function testReplacingWordsWithSameCountRebuildsDictionary(): void
    {
        ProfanityWordsValidator::$words = ['первоеслово'];
        self::assertFalse((new ProfanityWordsValidator('первоеслово'))->validate());

        ProfanityWordsValidator::$words = ['второеслово'];
        self::assertFalse((new ProfanityWordsValidator('второеслово'))->validate());
        self::assertTrue((new ProfanityWordsValidator('первоеслово'))->validate());
    }

    public function testCreateFakeWordsStillWorks(): void
    {
        self::assertSame(['сoк', 'cок', 'соk', 'cok'], AbstractBadWordsValidator::createFakeWords(['сок']));
    }
}
