<?php

require __DIR__ . '/../../../autoload.php';

// Данный пример запускать после установки пакета!

// Проверка сообщения от пользователя.
// В слове "электрo-фишер" кириллическая буква "о" заменена на латинскую,
// а слова умышленно соединены различными символами с целью обмана программы.
// В слове "сукa" кириллическая буква "a" заменена на латинскую.
$message = 'Продам_электрo-фишер.fisher-f-3500 не дорого! Ну и немного нембутала, сукa';

$validator = new Krugozor\RussianBadWords\Items\StopWordsValidator($message);
if (!$validator->validate()) {
    echo "Текст не проходит валидацию! Плохие слова:\n";
    print_r($validator->getFailedWords());
}

$validator = new Krugozor\RussianBadWords\Items\ProfanityWordsValidator($message);
if (!$validator->validate()) {
    echo "Текст не проходит валидацию! Ненормативная лексика:\n";
    print_r($validator->getFailedWords());
}