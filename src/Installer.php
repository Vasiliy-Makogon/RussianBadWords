<?php

namespace Krugozor\RussianBadWords;

/**
 * Заглушка установщика 1.x. Пакет 2.x ничего не копирует в проект: словари подключаются
 * через Dictionary, а свои слова и исключения проект задаёт в коде.
 *
 * Класс оставлен, чтобы composer.json проектов с вызовами Installer продолжал работать
 * без предупреждений об отсутствующем классе; при вызове печатается подсказка.
 *
 * @deprecated Удалите вызовы Installer из секции scripts вашего composer.json.
 */
class Installer
{
    private const NOTICE = 'krugozor/russian-bad-words 2.x больше не копирует словари в проект. '
        . 'Удалите вызовы Krugozor\RussianBadWords\Installer из секции scripts вашего composer.json '
        . 'и подключите словари через Dictionary (см. README).';

    private static bool $notified = false;

    /**
     * @param object|null $event Composer\Script\Event
     */
    public static function postInstall($event = null): void
    {
        self::notify($event);
    }

    /**
     * @param object|null $event Composer\Installer\PackageEvent
     */
    public static function preUninstall($event = null): void
    {
    }

    /**
     * @param object|null $event
     */
    private static function notify($event): void
    {
        if (self::$notified) {
            return;
        }
        self::$notified = true;

        if (is_object($event) && method_exists($event, 'getIO')) {
            $io = $event->getIO();
            if (is_object($io) && method_exists($io, 'writeError')) {
                $io->writeError('<warning>' . self::NOTICE . '</warning>');

                return;
            }
        }

        fwrite(STDERR, self::NOTICE . PHP_EOL);
    }
}
