<?php

declare(strict_types=1);

/*
 * Пример использования. Запуск из корня пакета: php console/sample.php
 */

foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}
if (!class_exists(Krugozor\RussianBadWords\BadWordsValidator::class)) {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Krugozor\\RussianBadWords\\';
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
}

use Krugozor\RussianBadWords\BadWordsValidator;
use Krugozor\RussianBadWords\Dictionary;

// В слове «электрo-фишер» кириллическая «о» заменена на латинскую, в слове «сукa» латинская «a»,
// слова умышленно соединены разными знаками, «х у й» написано вразрядку.
$message = 'Продам_электрo-фишер.fisher-f-3500 не дорого! Ну и немного нембутала, сукa. Иди на х у й';

$profanity = new BadWordsValidator(
    Dictionary::profanity()                         // мат, включён по умолчанию
        ->merge(Dictionary::profanityRoots())       // корни мата: ловят словообразование вроде «хуяндекс»
        ->merge(Dictionary::rude())                 // грубая лексика: говно, хрен, жопа, сука
        ->with(['новоезапрещённоеслово'])           // свои слова проекта
        ->without(['очко'])                         // исключения проекта
);

$result = $profanity->check($message);
if (!$result->isClean()) {
    echo "Ненормативная лексика: ", implode(', ', $result->words()), "\n";
    echo "С маскировкой: ", $result->mask(), "\n";
}

$stopWords = new BadWordsValidator(Dictionary::stopWords(['drugs', 'fishing']));
$result = $stopWords->check($message);
if (!$result->isClean()) {
    echo "Стоп-слова: ", implode(', ', $result->words()), "\n";
    foreach ($result->occurrences() as $occurrence) {
        printf("  «%s» = %s, смещение %d\n", $occurrence->fragment(), $occurrence->word(), $occurrence->offset());
    }
}

echo "Категории стоп-слов: ", implode(', ', Dictionary::stopWordCategories()), "\n";
