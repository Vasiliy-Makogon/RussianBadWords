<?php

require __DIR__ . '/../../../autoload.php';

// Данный пример запускать после установки пакета!

// Проверка сообщения от пользователя.
// В слове "электрo-фишер" кириллическая буква "о" заменена на латинскую,
// а слова умышленно соединены различными символами с целью обмана программы.
$message = 'Продам_электрo-фишер.fisher-f-3500 не дорого! Ну и немного нембутала';

$validator = new Krugozor\RussianBadWords\Items\StopWordsValidator($message);
if (!$validator->validate()) {
    echo "Текст не проходит валидацию! Плохие слова:\n";
    print_r($validator->getFailedWords());
}