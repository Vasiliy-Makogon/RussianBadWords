<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords\Tests;

use Krugozor\RussianBadWords\BadWordsValidator;
use Krugozor\RussianBadWords\Dictionary;
use PHPUnit\Framework\TestCase;

final class BadWordsValidatorTest extends TestCase
{
    private static function profanity(): BadWordsValidator
    {
        return new BadWordsValidator(Dictionary::profanity());
    }

    private static function profanityWithRoots(): BadWordsValidator
    {
        return new BadWordsValidator(Dictionary::profanity()->merge(Dictionary::profanityRoots()));
    }

    public function testReadmeExample(): void
    {
        // В «электрo-фишер» латинская «o», в «сукa» латинская «a», слова склеены разными знаками.
        $message = 'Продам_электрo-фишер.fisher-f-3500 не дорого! Ну и немного нембутала, сукa';

        $stop = (new BadWordsValidator(Dictionary::stopWords()))->check($message);
        self::assertSame(['электро-фишер', 'fisher-f-3500', 'нембутала'], $stop->words());

        $rude = (new BadWordsValidator(Dictionary::rude()))->check($message);
        self::assertSame(['сука'], $rude->words());
        self::assertSame('сукa', $rude->occurrences()[0]->fragment());

        self::assertTrue(self::profanity()->isClean($message));
    }

    /**
     * @dataProvider evasionProvider
     */
    public function testEvasionIsCaught(string $text, string $expectedWord): void
    {
        $result = self::profanity()->check($text);

        self::assertFalse($result->isClean(), $text);
        self::assertContains($expectedWord, $result->words(), $text);
    }

    public function testProfanityBeforeLongHyphenChainIsStillCaught(): void
    {
        // Цепочка из 8192 дефисных звеньев исчерпывала JIT-стек PCRE при разборе на слова,
        // и весь текст проходил как чистый.
        $text = 'хуйня, блядь! ' . str_repeat('1-', 8192) . '1';

        $result = self::profanity()->check($text);

        self::assertSame(['хуйня', 'блядь'], $result->words());
        self::assertStringStartsWith('*****, *****! 1-1-', $result->mask());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function evasionProvider(): iterable
    {
        yield 'латинская x' => ['xуйня', 'хуйня'];
        yield 'латинская y' => ['хyйня', 'хуйня'];
        yield 'две латинские буквы' => ['xyйня', 'хуйня'];
        yield 'верхний регистр' => ['ХУЙНЯ', 'хуйня'];
        yield 'цифра в конце' => ['хуйня1', 'хуйня'];
        yield 'цифры вокруг' => ['12хуйня34', 'хуйня'];
        yield 'дефис в конце' => ['хуйня-', 'хуйня'];
        yield 'дефис между словами' => ['хуйня-блядь', 'хуйня'];
        yield 'угловые скобки' => ['<хуйня>', 'хуйня'];
        yield 'фигурные скобки' => ['{хуйня}', 'хуйня'];
        yield 'ёлочки' => ['«хуйня»', 'хуйня'];
        yield 'длинное тире' => ['хуйня—блядь', 'хуйня'];
        yield 'многоточие' => ['хуйня…', 'хуйня'];
        yield 'доллар' => ['хуйня$', 'хуйня'];
        yield 'параграф' => ['§хуйня', 'хуйня'];
        yield 'неразрывный пробел' => ["хуйня\u{00A0}блядь", 'хуйня'];
        yield 'пробелы между буквами' => ['х у й н я', 'хуйня'];
        yield 'табуляция между буквами' => ["х\tу\tй\tн\tя", 'хуйня'];
        yield 'неразрывные пробелы между буквами' => ["х\u{00A0}у\u{00A0}й\u{00A0}н\u{00A0}я", 'хуйня'];
        yield 'точки между буквами' => ['х.у.й.н.я', 'хуйня'];
        yield 'звёздочки между буквами' => ['х*у*й*н*я', 'хуйня'];
        yield 'дефисы между буквами' => ['х-у-й-н-я', 'хуйня'];
        yield 'слово из двух частей' => ['ху йня', 'хуйня'];
        yield 'латиница в разрядке' => ['x y й', 'хуй'];
        yield 'удвоение буквы' => ['хууйня', 'хуйня'];
        yield 'растянутое слово' => ['бляяяять', 'блять'];
        yield 'комбинируемое ударение' => ["ху\u{0301}йня", 'хуйня'];
        yield 'пробел нулевой ширины' => ["ху\u{200B}йня", 'хуйня'];
        yield 'мягкий перенос' => ["ху\u{00AD}йня", 'хуйня'];
        yield 'цифра 3 вместо з' => ['пи3дец', 'пиздец'];
        yield 'цифра 6 вместо б' => ['прое6ал', 'проебал'];
        yield 'цифра 0 вместо о' => ['д0лб0еб', 'долбоеб'];
        yield 'ё вместо е' => ['ёбнутый', 'ёбнутый'];
        yield 'е вместо ё' => ['ебнутый', 'ёбнутый'];
        yield 'раздельное написание' => ['иди на хуй', 'хуй'];
        yield 'разрядка с предлогом' => ['иди на х уй', 'нахуй'];
        yield 'аббревиатура из двух букв' => ['ЕБ', 'еб'];
        yield 'двухбуквенное слово буква в букву' => ['да ёб же', 'еб'];
        yield 'цифра в коротком слове с кириллицей' => ['6ля', 'бля'];
        yield 'упоминание: «@» перед словом' => ['Эй, @хуйло, иди сюда', 'хуйло'];
        yield 'адрес: «@» после слова' => ['хуй@mail.ru', 'хуй'];
        yield '«@» в конце слова' => ['блядь@', 'блядь'];
        yield '«@» вместо «а» в начале слова' => ['@хуеть', 'ахуеть'];
        yield '«@» вместо «а» внутри слова' => ['г@ндон', 'гандон'];
        yield 'слово внутри предложения' => ['Ну и хуйня же это всё', 'хуйня'];
    }

    /**
     * @dataProvider cleanProvider
     */
    public function testNeutralTextIsClean(string $text): void
    {
        self::assertTrue(self::profanityWithRoots()->isClean($text), $text);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function cleanProvider(): iterable
    {
        $texts = [
            'Продам настольную игру Monopoly', 'Книга рецептов', 'Мучает бессонница', 'Привет, как дела?',
            'хлеб и требовать', 'команда и мандарин', 'три рубля', 'застрахуй машину', 'не психуй',
            'себе и тебе', 'веб-разработка', 'употреблять и оскорблять', 'скипидар', 'тихую и плохую',
            'Глеб', 'колебания', 'стебаться', 'чаепития в библиотеке', 'гребля', 'облита',
            'озлобляет и обособляются', 'страхуются', 'мандалорцы', 'спидраннеров', 'хулиган',
            'бляха-муха', 'бляхи', 'холестериновые бляшки', 'блямба', 'педикюр', 'дебоширить', 'лапидарный', 'штрихуй',
            'жопа', 'дурак', 'член команды', 'сука', 'хрен знает', '0', '', '   ', 'x', '12345',
            'Иди ты в баню', 'небо и хлебом', 'убеждение', 'победа', 'требуха', 'хребет', 'учебник',
            'ivan@mail.ru', 'Иван@yandex.ru', 'пишите на info@site.ru', '@ivan_petrov привет',
        ];
        foreach ($texts as $text) {
            yield $text === '' ? 'пустая строка' : $text => [$text];
        }
    }

    public function testAtSignIsReadBothAsLetterAndAsSeparator(): void
    {
        // «@» вместо «а»: слово целиком.
        self::assertSame(['сука'], (new BadWordsValidator(Dictionary::rude()))->check('сук@')->words());

        // «@» как разделитель адреса или упоминания: по частям, маска закрывает только мат.
        $result = self::profanity()->check('хуй-блядь@mail.ru');
        self::assertSame(['хуй', 'блядь'], $result->words());
        self::assertSame('***-*****@mail.ru', $result->mask());
        $stop = new BadWordsValidator(Dictionary::stopWords(['fraud', 'darknet']));
        self::assertSame(['спам', 'onion'], $stop->check('спам@mail.ru, onion@')->words());

        // С корнями часть находится по точному словарю, а не целое слово по корню.
        $result = self::profanityWithRoots()->check('хуй@mail.ru');
        self::assertSame(['хуй'], $result->words());
        self::assertFalse($result->occurrences()[0]->isByRoot());
        self::assertSame('***@mail.ru', $result->mask());
    }

    /**
     * Инициалы и короткие коды не превращаются в мат ни склейкой, ни подменой похожих символов:
     * слово словаря короче трёх букв и слово до трёх символов без кириллицы находятся только
     * буква в букву.
     *
     * @dataProvider initialsAndCodesProvider
     */
    public function testInitialsAndShortCodesAreClean(string $text): void
    {
        $validator = new BadWordsValidator(Dictionary::profanity()->merge(Dictionary::rude(), Dictionary::insults()));

        self::assertTrue($validator->isClean($text), $text);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function initialsAndCodesProvider(): iterable
    {
        yield 'инициалы через точку: «еб»' => ['Иванова Е.Б.'];
        yield 'инициалы без последней точки' => ['Е.Б'];
        yield 'шахматный ход: «e6»' => ['1.e4 e6 2.d4'];
        yield 'кириллическая буква с цифрой' => ['трасса Е6'];
        yield 'латиница с цифрой через дефис' => ['E-6'];
        yield 'латиница и цифра через пробел' => ['e 6'];
        yield 'код из латиницы и цифр: «еба»' => ['e6a'];
        yield 'код из цифры и латиницы: «чмо»' => ['4mo'];
        yield 'латиница без кириллицы: «хер»' => ['xep'];
    }

    public function testShortLatinWordsStayUnderRootsSupervision(): void
    {
        // Правила для коротких слов касаются только точного словаря: с корнями латиница проверяется как раньше.
        self::assertTrue(self::profanity()->isClean('xyi'));

        $result = self::profanityWithRoots()->check('xyi');
        self::assertSame(['хуи'], $result->words());
        self::assertTrue($result->occurrences()[0]->isByRoot());
    }

    /**
     * Короткие слова обычной речи не склеиваются в мат: границей служат перевод строки
     * и знак препинания с пробелом, склейка сверяется с точным словарём без корней
     * и без схлопывания повторов.
     *
     * @dataProvider shortWordsOfNormalSpeechProvider
     */
    public function testShortWordsOfNormalSpeechAreNotGluedIntoBadWords(string $text): void
    {
        $validator = new BadWordsValidator(Dictionary::profanity()->merge(
            Dictionary::rude(),
            Dictionary::insults(),
            Dictionary::anatomy(),
            Dictionary::profanityRoots()
        ));

        self::assertTrue($validator->isClean($text), $text);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function shortWordsOfNormalSpeechProvider(): iterable
    {
        yield 'запятая с пробелом: «елда»' => ['Он ел, да не всё'];
        yield 'точка и перевод строки: «елда»' => ["Он ел.\nДа, не всё"];
        yield 'точка с пробелом: «елда»' => ['Он ел. Да не всё'];
        yield 'запятая в перечислении: «задрот»' => ['Болят зад, рот и уши'];
        yield 'запятая после междометия: «ахуели»' => ['Ах, у ели стоит дом'];
        yield 'схлопывание «её» в «е» по корню: «ебез»' => ['Я видел её без шляпы'];
        yield 'схлопывание «её» в «е» по словарю: «ебал»' => ['Её бал удался'];
        yield 'приставка корня «у»: «унеебыл»' => ['У неё был день'];
        yield 'приставки корня «я», «от», «не»: «яотнеебезума»' => ['Я от неё без ума'];
        yield 'корень «мудак» в склейке: «емудакак»' => ['Скажи ему да как можно скорее'];
        yield 'корень «ху» в склейке: «ахуего»' => ['Ах у его отца'];
        // Речь без запятой: все слова цепочки обычные, поэтому она не склеивается.
        yield 'без запятой: «елда»' => ['Он ел да спал'];
        yield 'запятая без пробела: «елда»' => ['Он ел,да не всё'];
        yield 'точка без пробела: «елда»' => ['Он ел.Да'];
        yield 'через дефис: «елда»' => ['ел-да'];
        yield 'без запятой: «ахуели»' => ['ах у ели стоит дом'];
        yield 'без запятой: «охуел»' => ['ох у ел я'];
        yield 'без запятой: «идиот»' => ['иди от сюда'];
        yield 'без запятой: «задрот»' => ['Болят зад рот и уши'];
        yield 'без запятой: «суки»' => ['сук и ветки'];
    }

    public function testSpacedLettersAreGluedEvenWhenTheyAreWords(): void
    {
        // Цепочка из одиночных букв это разрядка, а не речь, хотя «с», «у», «к», «а» сами по себе слова.
        $all = new BadWordsValidator(Dictionary::profanity()->merge(Dictionary::rude(), Dictionary::insults()));

        self::assertSame(['сука'], $all->check('с у к а')->words());
        self::assertSame(['идиот'], $all->check('и д и о т')->words());
        self::assertSame(['елда'], $all->check('е.л.д.а')->words());
        self::assertSame(['мудак'], $all->check('му да к')->words(), '«му» не слово, значит это разрядка');
        self::assertSame(['ахуели'], $all->check('ах у е л и')->words(), '«е» и «л» не слова, значит это разрядка');
        self::assertSame(['задрот'], $all->check('за д рот')->words());
        self::assertSame(['сука'], $all->check('су-ка')->words());
    }

    public function testGluedWordsAreMatchedWithoutRoots(): void
    {
        $result = self::profanityWithRoots()->check('иди на х у й');

        self::assertSame(['нахуй'], $result->words());
        self::assertFalse($result->occurrences()[0]->isByRoot());
    }

    /**
     * @dataProvider rootsProvider
     */
    public function testRootsCatchProductiveForms(string $text): void
    {
        self::assertTrue(self::profanity()->isClean($text), 'без корней: ' . $text);

        $result = self::profanityWithRoots()->check($text);
        self::assertFalse($result->isClean(), 'с корнями: ' . $text);
        self::assertTrue($result->occurrences()[0]->isByRoot());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function rootsProvider(): iterable
    {
        foreach ([
            'хуяндексище', 'айтиблядство', 'технопоебенище', 'копипиздингом', 'росблядьнадзором',
            'выебщиками', 'дохулиардище', 'невротъебищный', 'шароебилище', 'пиздобляцкость', 'заебамба',
            'мудоебище', 'охуевайтингом', 'залупенцией',
            // Приставки трое, лихо, херо, страхо, бляхо у корня «еб»; слова на «бляхо…» не гасятся оговоркой про «бляху».
            'лихоуебище', 'страхоуебище', 'хероебучая', 'троееби', 'бляхоебаная', 'бляхозалупищем',
        ] as $text) {
            yield $text => [$text];
        }
    }

    public function testTiersAreSeparate(): void
    {
        self::assertFalse((new BadWordsValidator(Dictionary::rude()))->isClean('жопа'));
        self::assertFalse((new BadWordsValidator(Dictionary::insults()))->isClean('дурак'));
        self::assertFalse((new BadWordsValidator(Dictionary::anatomy()))->isClean('член'));
        self::assertFalse((new BadWordsValidator(Dictionary::stopWords(['darknet'])))->isClean('Monopoly'));
        self::assertTrue((new BadWordsValidator(Dictionary::stopWords(['drugs'])))->isClean('Monopoly'));

        $strict = new BadWordsValidator(Dictionary::profanity()->merge(Dictionary::rude(), Dictionary::insults()));
        self::assertSame(['хуйня', 'жопа', 'дурак'], $strict->check('хуйня, жопа и дурак')->words());
    }

    public function testProjectWordsAndExceptions(): void
    {
        $dictionary = Dictionary::profanity()
            ->merge(Dictionary::profanityRoots())
            ->with(['новоеслово'])
            ->without(['хуйня', 'хули']);
        $validator = new BadWordsValidator($dictionary);

        self::assertFalse($validator->isClean('новоеслово'));
        self::assertTrue($validator->isClean('хуйня'));
        self::assertTrue($validator->isClean('хули'));
        self::assertFalse($validator->isClean('хуйнёй'));
    }

    public function testInvisibleCharactersBetweenSpacedLettersDoNotBreakGluing(): void
    {
        foreach ([
            'пробел нулевой ширины' => "х \u{200B} у й",
            'комбинируемое ударение' => "х \u{0301} у й",
            'соединитель слов' => "х \u{2060} у й",
            'мягкий перенос' => "х \u{00AD} у й",
            'ZWJ внутри семейного эмодзи' => "х👨\u{200D}👩\u{200D}👧у👨\u{200D}👩\u{200D}👧й",
            'VS16 после эмодзи' => "х❤\u{FE0F}у❤\u{FE0F}й",
        ] as $title => $text) {
            self::assertSame(['хуй'], self::profanity()->check($text)->words(), $title);
        }

        // Знак с пробелами по обе стороны по-прежнему разделяет слова, как «ел, да».
        self::assertTrue(self::profanity()->isClean("х ❤\u{FE0F} у ❤\u{FE0F} й"));
    }

    public function testGluedRunWindowSlidesOverLongChains(): void
    {
        self::assertSame(['хуйня', 'блядь', 'пизда'], self::profanity()->check('х у й н я б л я д ь п и з д а')->words());
        self::assertSame(['нахуй', 'блядь'], self::profanity()->check('и д и н а х у й б л я д ь')->words());
        self::assertSame(['хуй'], self::profanity()->check('а и о у е я ё э ю ы х у й')->words(), 'слово на границе двенадцатого и тринадцатого');
        self::assertTrue(self::profanity()->isClean('а и о у е я ё э ю ы не на ту да ко он мы вы ты'));
    }

    public function testHyphenatedPartsAreGluedLikeSeparateWords(): void
    {
        foreach ([
            'х-у-й-н-я-б-л-я-д-ь' => ['хуйня', 'блядь'],
            'иди-на-х-у-й' => ['нахуй'],
            'п-и-з-д-а-е-б-а-т-ь' => ['пизда', 'ебать'],
            'иди на х-у й' => ['нахуй'],
        ] as $text => $words) {
            self::assertSame($words, self::profanity()->check($text)->words(), $text);
        }
        self::assertSame('иди-********', self::profanity()->check('иди-на-х-у-й')->mask());
        self::assertSame(['нахуй'], self::profanityWithRoots()->check('иди на х-у й')->words());

        foreach (['как-то', 'из-за', 'что-нибудь', 'ля-ля-ля', 'Ростов-на-Дону', 'Ту-154', 'тет-а-тет'] as $text) {
            self::assertTrue(self::profanity()->isClean($text), $text);
        }
    }

    public function testStretchedDoubledLetterIsFound(): void
    {
        // Словарные слова с законным удвоением: повтор из трёх букв сводится к двум, а не к одной.
        self::assertSame(['ахуенно'], self::profanity()->check('ахуеннно')->words());
        self::assertSame(['выебанный'], self::profanity()->check('выебаннный')->words());
        self::assertSame(['ссать'], (new BadWordsValidator(Dictionary::rude()))->check('сссать')->words());
        self::assertTrue(self::profanity()->isClean('ссссылка на сайт'));
    }

    public function testExactWordWithEdgeDigitsBeatsRoot(): void
    {
        foreach (['хуйня1' => 'хуйня', '12хуйня34' => 'хуйня', 'пизда3' => 'пизда', 'бля6' => 'бля'] as $text => $word) {
            $result = self::profanityWithRoots()->check($text);

            self::assertSame([$word], $result->words(), $text);
            self::assertFalse($result->occurrences()[0]->isByRoot(), $text . ': словарное слово с цифрой на краю находится точно, а не по корню');
        }
    }

    public function testRootWordIsReportedWithoutEdgeDigits(): void
    {
        $result = self::profanityWithRoots()->check('хуепутало4');

        self::assertSame(['хуепутало'], $result->words());
        self::assertTrue($result->occurrences()[0]->isByRoot());
        self::assertSame('**********', $result->mask());
    }

    public function testExcludedWordStaysExcludedWithEdgeDigitsAndStretchedLetters(): void
    {
        $validator = new BadWordsValidator(Dictionary::profanity()->merge(Dictionary::profanityRoots())->without(['хуйня']));

        foreach (['хуйня', 'хуйня1', 'xyйня1', 'хуйняя', 'хуйняяя'] as $text) {
            self::assertTrue($validator->isClean($text), $text);
        }
        self::assertFalse($validator->isClean('хуйнястый'), 'другое слово с тем же корнем остаётся');
    }

    public function testMaskReplacesWordsAndKeepsPunctuation(): void
    {
        self::assertSame('Ну ты и *****, *****!', self::profanity()->check('Ну ты и хуйня, блядь!')->mask());
        self::assertSame('*********', self::profanity()->check('х у й н я')->mask());
        self::assertSame('Иди ********', self::profanity()->check('Иди на х у й')->mask());
        self::assertSame('Иди ********', self::profanityWithRoots()->check('Иди на х у й')->mask());
        self::assertSame(['нахуй'], self::profanityWithRoots()->check('Иди на х у й')->words());
        self::assertSame('##### и ###', self::profanity()->check('Хуйня и xуй')->mask('#'));
        self::assertSame('чистый текст', self::profanity()->check('чистый текст')->mask());
    }

    public function testMaskCanKeepEdgeLetters(): void
    {
        $result = self::profanity()->check('Ну ты и хуй, блядь! xуйня');

        self::assertSame('Ну ты и х@й, б@@@ь! x@@@я', $result->mask('@', 1, 1));
        self::assertSame('Ну ты и ху*, бл***! xу***', $result->mask('*', 2));
        self::assertSame('Ну ты и *уй, ***дь! ***ня', $result->mask('*', 0, 2));
        self::assertSame('Ну ты и ***, *****! *****', $result->mask('*', -1, -1));
        self::assertSame('**', self::profanity()->check('ЕБ')->mask('*', 1, 1), 'скрывать нечего: слово закрывается целиком');
        self::assertSame('х***й', self::profanity()->check('х у й')->mask('*', 1, 1));
    }

    public function testOccurrencesCarryOffsets(): void
    {
        $occurrence = self::profanity()->check('ах ты хуйня')->occurrences()[0];

        self::assertSame('хуйня', $occurrence->fragment());
        self::assertSame('хуйня', $occurrence->word());
        self::assertSame(10, $occurrence->offset());
        self::assertSame(10, $occurrence->length());
        self::assertFalse($occurrence->isByRoot());
    }

    public function testWordsAreUniqueAndOrdered(): void
    {
        self::assertSame(['хуйня', 'блядь'], self::profanity()->check('хуйня блядь ХУЙНЯ')->words());
    }

    public function testNumericWordsStayStrings(): void
    {
        $validator = new BadWordsValidator(Dictionary::fromWords(['228', 'спайс']));

        self::assertSame(['228', 'спайс'], $validator->check('статья 228 и спайс')->words());
    }

    public function testInvalidUtf8DoesNotBreakTheCheck(): void
    {
        $result = self::profanity()->check("\xFF\xFE хуйня \xC3");

        self::assertFalse($result->isClean());
        self::assertSame(1, preg_match('//u', $result->text()));
        self::assertSame(1, preg_match('//u', $result->mask()));
    }

    public function testLatinDictionaryWordsWithCyrillicHomoglyphs(): void
    {
        $validator = new BadWordsValidator(Dictionary::stopWords(['sex']));

        self::assertSame(['sex'], $validator->check("s\u{0435}\u{0445}")->words());
        self::assertSame(['porn'], $validator->check("p\u{043E}rn")->words());
    }

    public function testHyphenatedDictionaryWords(): void
    {
        $validator = new BadWordsValidator(Dictionary::stopWords(['fishing']));

        self::assertContains('электро-фишер', $validator->check('Продам электро-фишер')->words());
        self::assertContains('skat-950-ls', $validator->check('Skat-950-LS в наличии')->words());
        self::assertContains('фишер', $validator->check('фишер-ф-1234')->words());
        self::assertSame('Продам *************', $validator->check('Продам электро-фишер')->mask(), 'словарное слово с дефисом закрывается целиком');
        self::assertSame('*****-ф-1234', $validator->check('фишер-ф-1234')->mask(), 'закрывается только совпавшая часть');
    }

    public function testHyphenatedWordReportsEachMatchingPart(): void
    {
        foreach (['без корней' => self::profanity(), 'с корнями' => self::profanityWithRoots()] as $mode => $validator) {
            $result = $validator->check('Ну ты и хуйня-блядь!');

            self::assertSame(['хуйня', 'блядь'], $result->words(), $mode);
            self::assertSame('Ну ты и *****-*****!', $result->mask(), $mode);
            self::assertCount(2, $result->occurrences(), $mode);
            self::assertFalse($result->occurrences()[0]->isByRoot(), $mode . ': точное совпадение части важнее корня целого слова');
            self::assertSame('блядь', $result->occurrences()[1]->fragment(), $mode);
            self::assertSame(24, $result->occurrences()[1]->offset(), $mode);
        }

        self::assertSame('супер-*****', self::profanity()->check('супер-хуйня')->mask());
        self::assertSame('*****-супер', self::profanityWithRoots()->check('хуйня-супер')->mask());
        self::assertSame('******-******', self::profanity()->check('хуйня1-2блядь')->mask());
        self::assertSame(['бля'], self::profanityWithRoots()->check('бля-бля')->words());
        self::assertSame('***-***', self::profanityWithRoots()->check('бля-бля')->mask());
    }

    public function testHyphenatedWordMatchesAsWholeBeforeParts(): void
    {
        self::assertSame('******', self::profanity()->check('хуй-ня')->mask());
        self::assertSame('*********', self::profanity()->check('х-у-й-н-я')->mask());
        self::assertSame(['похуй'], self::profanityWithRoots()->check('по-хуй')->words());
        self::assertSame(['хуйня'], self::profanity()->check('x-уйня')->words());
    }

    public function testExactAndRootPartsOfHyphenatedWordAreBothReported(): void
    {
        $result = self::profanityWithRoots()->check('блядь-хуепутало');

        self::assertSame(['блядь', 'хуепутало'], $result->words());
        self::assertSame('*****-*********', $result->mask());
        self::assertFalse($result->occurrences()[0]->isByRoot());
        self::assertTrue($result->occurrences()[1]->isByRoot());

        self::assertSame(['хуепутало', 'хуйня', 'залупенция'], self::profanityWithRoots()->check('хуепутало-хуйня-залупенция')->words());
        self::assertSame('*********-*****-**********', self::profanityWithRoots()->check('хуепутало-хуйня-залупенция')->mask());
    }

    public function testRootMarksOnlyTheOffendingPartOfHyphenatedWord(): void
    {
        $result = self::profanityWithRoots()->check('супер-хуепутало');
        $occurrence = $result->occurrences()[0];

        self::assertSame(['хуепутало'], $result->words());
        self::assertTrue($occurrence->isByRoot());
        self::assertSame('супер-*********', $result->mask());
        self::assertSame(11, $occurrence->offset());
        self::assertSame(18, $occurrence->length());

        self::assertSame(['хуепутало'], self::profanityWithRoots()->check('х-у-е-п-у-т-а-л-о')->words(), 'разрядка дефисами ловится без дефисов');
        self::assertTrue(self::profanityWithRoots()->isClean('за-страхуй и мандарин-ка'), 'исключения корней действуют и на части');
    }
}
