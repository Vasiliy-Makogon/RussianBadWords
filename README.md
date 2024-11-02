# Словарь плохих русских слов для сайта

[Данный словарь](dictionary.php) предназначен для веб-мастеров, которе обслуживают информационные системы,
контент которых наполняют пользователи - доски объявлений, форумы, функционал комментариев.

Данный словарь НЕ содержит ненормативную лексику (словарь ненормативной лексики
[есть тут](https://github.com/bars38/Russian_ban_words)), здесь представлены, в том числе, и слова,
за которые вы легко можете попасть под блокировку Роскомнадзором, если сообщение от пользователя,
содержащее слова из этого набора, окажутся на вашем сайте (например, объявление о продаже `электроудочки` или
препаратов, которые употребляют наркоманы - это реальные случаи из моей практики,
когда мне прилетали запросы от РКН).
Кроме того, словарь содержит набор стоп-слов, которые заведомо будут носить негативный характер,
например: `гадалка`, `эскорт`, `кредит` и т.д.

Словарь представлен в виде структурированного массива в формате PHP и это важно - владелец сайта
НЕ ДОЛЖЕН полагаться на базу стоп-слов, в которой слова находятся в хаотическом порядке,
***должна быть возможность в любой момент дописать или перепроверить содержимое словаря***, поэтому -
никаких данных в формате JSON или в виде SQL-дампа (это дружеский совет и напутствие возможным
контрибьютерам).

### Дополнительное runtime-решение на PHP

В одном из своих проектов я сделал дополнительную валидацию: я использую этот словарь и, во время
валидации сообщения от пользователя, преобразую в каждом слове из этого набора все буквы кириллицы
на латинские эквиваленты, что бы немного понимающий в IT пользователь всё-таки не разместил
негативное сообщение просто заменив "похожие" буквы.

Пример находится в файле [test.php](test.php) и генерирует для
слова `наркотик` такой массив данных:

```text
Array
(
    [0] => нaркотик
    [1] => наркoтик
    [2] => нарkотик
    [3] => наркотиk
    [4] => наpкотик
    [5] => нapkoтиk
)
```

В [том же файле](test.php)  вы найдете пример готовой функции `validate` для работы с данным словарём.

Проверяйте поступающую от пользователя информацию и не допускайте на своём сайте сообщений,
благодаря которым ваш сайт может быть заблокирован.


