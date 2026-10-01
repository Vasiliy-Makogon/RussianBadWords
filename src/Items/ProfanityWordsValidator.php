<?php

namespace Krugozor\RussianBadWords\Items;

use Krugozor\RussianBadWords\AbstractBadWordsValidator;
use Krugozor\RussianBadWords\Dictionary;

/**
 * Проверка на мат в стиле 1.x.
 *
 * @deprecated Используйте new BadWordsValidator(Dictionary::profanity()).
 */
class ProfanityWordsValidator extends AbstractBadWordsValidator
{
    /**
     * Дополнительные слова проекта. В 1.x здесь лежал весь словарь, теперь он поставляется
     * в data/profanity.php и подключается через Dictionary::profanity().
     *
     * @var list<string>
     */
    public static array $words = [];

    protected static function dictionary(): Dictionary
    {
        return Dictionary::profanity()->with(static::$words);
    }
}
