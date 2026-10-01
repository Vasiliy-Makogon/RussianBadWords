<?php

declare(strict_types=1);

namespace Krugozor\RussianBadWords;

use Countable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Неизменяемый словарь плохих слов.
 *
 * Словарь состоит из точных словоформ и, при желании, корней: регулярных выражений,
 * которые проверяются на вхождение в слово текста. Любая операция возвращает новый
 * словарь, исходный не меняется, поэтому встроенные словари можно безопасно кэшировать.
 *
 * Встроенные словари: profanity() (мат), rude() (грубая лексика), insults() (оскорбления),
 * anatomy() (анатомия и секс), profanityRoots() (корни мата), stopWords() (стоп-слова по категориям).
 */
final class Dictionary implements Countable
{
    /** @var array<string, string> Форма слова (см. TextNormalizer::forms) => слово словаря */
    private array $index = [];

    /** @var array<string, string> Нормальная форма слова => слово словаря */
    private array $words = [];

    /** @var list<string> Корни: регулярные выражения без разделителей и флагов */
    private array $roots = [];

    /** @var list<string> Подстроки, при наличии которых слово по корням не проверяется */
    private array $exceptions = [];

    /** @var array<string, true> Точные слова, которые по корням не проверяются */
    private array $exceptionWords = [];

    private ?string $rootsPattern = null;

    private ?string $exceptionsPattern = null;

    private static ?TextNormalizer $normalizer = null;

    /** @var array<string, self> */
    private static array $builtIn = [];

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param iterable<string> $words
     */
    public static function fromWords(iterable $words): self
    {
        return (new self())->with($words);
    }

    /**
     * Загружает словарь из PHP-файла, который возвращает либо список слов, либо массив
     * с ключами words, roots и exceptions.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Файл словаря не найден: %s', $path));
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('Файл словаря должен возвращать массив: %s', $path));
        }

        if (array_key_exists('words', $data) || array_key_exists('roots', $data) || array_key_exists('exceptions', $data)) {
            $dictionary = (new self())
                ->with(self::stringList($data['words'] ?? [], $path))
                ->withRoots(self::stringList($data['roots'] ?? [], $path));

            return $dictionary->withExceptions(self::stringList($data['exceptions'] ?? [], $path));
        }

        return (new self())->with(self::stringList($data, $path));
    }

    /** Мат. Включается по умолчанию. */
    public static function profanity(): self
    {
        return self::builtIn('profanity');
    }

    /** Грубая, но не матерная лексика: говно, хрен, жопа, сука и подобное. */
    public static function rude(): self
    {
        return self::builtIn('rude');
    }

    /** Оскорбления без мата: дурак, лох, идиот, чмо и подобное. */
    public static function insults(): self
    {
        return self::builtIn('insults');
    }

    /** Анатомия, физиология и секс: анус, вагина, пенис, член, презерватив и подобное. */
    public static function anatomy(): self
    {
        return self::builtIn('anatomy');
    }

    /**
     * Корни мата для поиска по вхождению: ловят словообразование, которого нет в словаре
     * («хуяндекс», «технопоебень»). Подключаются отдельно: profanity()->merge(profanityRoots()).
     */
    public static function profanityRoots(): self
    {
        return self::builtIn('profanity-roots');
    }

    /**
     * Стоп-слова: темы, за которые площадку могут заблокировать. Без аргумента
     * загружаются все категории, список категорий даёт stopWordCategories().
     *
     * @param list<string>|null $categories
     */
    public static function stopWords(?array $categories = null): self
    {
        $available = self::stopWordCategories();
        $categories = $categories ?? $available;

        $dictionary = new self();
        foreach ($categories as $category) {
            if (!in_array($category, $available, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Неизвестная категория стоп-слов «%s». Доступны: %s',
                    $category,
                    implode(', ', $available)
                ));
            }
            $dictionary = $dictionary->merge(self::builtIn('stop-words/' . $category));
        }

        return $dictionary;
    }

    /**
     * @return list<string>
     */
    public static function stopWordCategories(): array
    {
        // Не glob(): в пути установки с «[», «]», «*» или «?» он молча вернул бы пустой список.
        $directory = self::dataDir() . '/stop-words';
        $files = is_dir($directory) ? scandir($directory) : false;
        if ($files === false) {
            throw new RuntimeException(sprintf('Каталог стоп-слов не найден: %s', $directory));
        }

        $categories = [];
        foreach ($files as $file) {
            if (substr($file, -4) === '.php') {
                $categories[] = substr($file, 0, -4);
            }
        }
        sort($categories);

        return $categories;
    }

    /**
     * Объединяет словари: слова, корни и исключения складываются.
     */
    public function merge(self ...$others): self
    {
        $result = clone $this;
        foreach ($others as $other) {
            $result = $result
                ->with($other->words)
                ->withRoots($other->roots)
                ->withExceptions($other->exceptions);
            foreach ($other->exceptionWords as $word => $_) {
                $result->exceptionWords[$word] = true;
            }
        }

        return $result;
    }

    /**
     * Возвращает словарь с добавленными словами. Пробелы и невидимые символы по краям слова
     * срезаются, остальное написание сохраняется. Слово, все формы которого уже заняты
     * другим словом (та же запись в другой раскладке), считается его двойником и не добавляется.
     *
     * @param iterable<string> $words
     */
    public function with(iterable $words): self
    {
        $result = clone $this;
        $normalizer = self::normalizer();
        foreach ($words as $word) {
            $word = $normalizer->trim($word);
            $key = $normalizer->normalizeWord($word);
            if ($key === '' || isset($result->words[$key])) {
                continue;
            }
            $free = [];
            foreach ($normalizer->forms($key) as $form) {
                if (!isset($result->index[$form])) {
                    $free[] = $form;
                }
            }
            if ($free === []) {
                continue;
            }
            $result->words[$key] = $word;
            foreach ($free as $form) {
                $result->index[$form] = $word;
            }
        }

        return $result;
    }

    /**
     * Возвращает словарь без указанных слов. Эти слова также не будут находиться по корням.
     * Вместе со словом уходят его двойники: слова, все формы которых есть у удаляемых.
     * Форма, общая с оставшимся словом (та же запись в кириллице при другой латинице,
     * как у «х0й» и «хой»), остаётся за ним: по ней слово находится и проверяется по корням.
     *
     * @param iterable<string> $words
     */
    public function without(iterable $words): self
    {
        $result = clone $this;
        $normalizer = self::normalizer();
        /** @var array<string, true> $removed */
        $removed = [];
        foreach ($words as $word) {
            $key = $normalizer->normalizeWord($normalizer->trim($word));
            if ($key === '') {
                continue;
            }
            unset($result->words[$key]);
            foreach ($normalizer->forms($key) as $form) {
                $removed[$form] = true;
            }
        }
        foreach ($result->words as $key => $_) {
            foreach ($normalizer->forms((string) $key) as $form) {
                if (!isset($removed[$form])) {
                    continue 2;
                }
            }
            unset($result->words[$key]);
        }
        $result->index = [];
        foreach ($result->words as $key => $word) {
            foreach ($normalizer->forms((string) $key) as $form) {
                if (!isset($result->index[$form])) {
                    $result->index[$form] = $word;
                }
            }
        }
        foreach ($removed as $form => $_) {
            if (!isset($result->index[$form])) {
                $result->exceptionWords[(string) $form] = true;
            }
        }

        return $result;
    }

    /**
     * Возвращает словарь с добавленными корнями: регулярными выражениями без разделителей
     * и флагов, которые проверяются на вхождение в слово текста в кириллической форме.
     * Шаблон собирается с разделителем «~», поэтому буквальную тильду в корне нужно
     * экранировать: «\~». Практической нужды в ней нет: в слове текста тильды не бывает.
     *
     * @param iterable<string> $roots
     */
    public function withRoots(iterable $roots): self
    {
        $result = clone $this;
        foreach ($roots as $root) {
            if ($root === '' || in_array($root, $result->roots, true)) {
                continue;
            }
            if (@preg_match('~(?:' . $root . ')~u', '') === false) {
                throw new InvalidArgumentException(sprintf('Некорректное регулярное выражение корня: %s', $root));
            }
            $result->roots[] = $root;
        }
        // Корни проверяются и вместе: по одному они могут быть верны, а в общем шаблоне конфликтовать,
        // например одним именем для групп с разными номерами. Иначе каждый вызов matchesRoot() давал бы
        // предупреждение и false, молча отключая все корни, включая встроенные.
        if ($result->roots !== []) {
            error_clear_last();
            if (@preg_match(self::compileRoots($result->roots), '') === false) {
                $error = error_get_last();
                throw new InvalidArgumentException('Корни несовместимы друг с другом в общем регулярном выражении'
                    . ($error !== null ? ': ' . $error['message'] : ''));
            }
        }
        $result->rootsPattern = null;

        return $result;
    }

    /**
     * Общий шаблон корней. Ветки объединяются группой со сбросом нумерации «(?|…)», поэтому
     * нумерованные обратные ссылки и имена групп каждого корня остаются его собственными
     * и не сдвигаются соседними корнями.
     *
     * @param list<string> $roots
     */
    private static function compileRoots(array $roots): string
    {
        return '~(?|(?:' . implode(')|(?:', $roots) . '))~u';
    }

    /**
     * Возвращает словарь с добавленными исключениями: слова текста, содержащие любую
     * из подстрок, по корням не проверяются («страху» защищает «застрахуй»). Подстрока
     * приводится к тому же виду, в котором слово текста проверяется по корням: нижний регистр,
     * «ё» → «е», латинские омоглифы → кириллица.
     *
     * @param iterable<string> $substrings
     */
    public function withExceptions(iterable $substrings): self
    {
        $result = clone $this;
        $normalizer = self::normalizer();
        foreach ($substrings as $substring) {
            $substring = $normalizer->toCyrillic($normalizer->normalizeWord($normalizer->trim($substring)));
            if ($substring === '' || in_array($substring, $result->exceptions, true)) {
                continue;
            }
            $result->exceptions[] = $substring;
        }
        $result->exceptionsPattern = null;

        return $result;
    }

    /**
     * @return list<string>
     */
    public function words(): array
    {
        return array_values($this->words);
    }

    /**
     * @return list<string>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * @return list<string>
     */
    public function exceptions(): array
    {
        return $this->exceptions;
    }

    public function hasRoots(): bool
    {
        return $this->roots !== [];
    }

    /**
     * Исключено ли слово через without(). Проверяется форма слова в кириллице или латинице,
     * как она хранится в индексе: такие слова не находятся ни точно, ни по корням.
     */
    public function isExcluded(string $form): bool
    {
        return isset($this->exceptionWords[$form]);
    }

    public function count(): int
    {
        return count($this->words);
    }

    /**
     * Ищет точное совпадение одной из форм слова и возвращает слово словаря.
     *
     * @param list<string> $forms
     */
    public function findExact(array $forms): ?string
    {
        foreach ($forms as $form) {
            if (isset($this->index[$form])) {
                return $this->index[$form];
            }
        }

        return null;
    }

    /**
     * Проверяет слово в кириллической форме на вхождение корней с учётом исключений.
     *
     * @throws RuntimeException Если PCRE не смог выполнить проверку. Ошибка движка не должна
     *     превращаться в «корень не найден»: иначе слово прошло бы как чистое
     */
    public function matchesRoot(string $word): bool
    {
        if ($this->roots === [] || $word === '' || isset($this->exceptionWords[$word])) {
            return false;
        }

        if ($this->exceptions !== []) {
            if ($this->exceptionsPattern === null) {
                $this->exceptionsPattern = '~' . implode('|', array_map(
                    static fn (string $substring): string => preg_quote($substring, '~'),
                    $this->exceptions
                )) . '~u';
            }
            $excepted = preg_match($this->exceptionsPattern, $word);
            if ($excepted === false) {
                throw new RuntimeException(sprintf('Не удалось проверить слово по исключениям, код ошибки PCRE: %d', preg_last_error()));
            }
            if ($excepted === 1) {
                return false;
            }
        }

        if ($this->rootsPattern === null) {
            $this->rootsPattern = self::compileRoots($this->roots);
        }
        $matched = preg_match($this->rootsPattern, $word);
        if ($matched === false) {
            throw new RuntimeException(sprintf('Не удалось проверить слово по корням, код ошибки PCRE: %d', preg_last_error()));
        }

        return $matched === 1;
    }

    private static function builtIn(string $name): self
    {
        if (!isset(self::$builtIn[$name])) {
            self::$builtIn[$name] = self::fromFile(self::dataDir() . '/' . $name . '.php');
        }

        return self::$builtIn[$name];
    }

    private static function dataDir(): string
    {
        return dirname(__DIR__) . '/data';
    }

    private static function normalizer(): TextNormalizer
    {
        return self::$normalizer ?? (self::$normalizer = new TextNormalizer());
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList($value, string $path): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Ожидался массив строк в файле словаря: %s', $path));
        }
        $list = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(sprintf('Ожидался массив строк в файле словаря: %s', $path));
            }
            $list[] = $item;
        }

        return $list;
    }
}
