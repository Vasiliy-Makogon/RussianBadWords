<?php

namespace Krugozor\RussianBadWords\Items;

use Krugozor\RussianBadWords\AbstractBadWordsValidator;
use Krugozor\RussianBadWords\Dictionary;

/**
 * Проверка на стоп-слова в стиле 1.x: все категории сразу.
 *
 * @deprecated Используйте new BadWordsValidator(Dictionary::stopWords([...категории...])).
 */
class StopWordsValidator extends AbstractBadWordsValidator
{
    /**
     * Дополнительные слова проекта. В 1.x здесь лежал весь словарь, теперь он поставляется
     * в data/stop-words/*.php и подключается через Dictionary::stopWords().
     *
     * @var list<string>
     */
    public static array $words = [];

    protected static function dictionary(): Dictionary
    {
        return Dictionary::stopWords()->with(static::$words);
    }
}
