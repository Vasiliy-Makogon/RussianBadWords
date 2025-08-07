<?php

namespace Krugozor\RussianBadWords;

use Composer\Script\Event;
use Composer\Util\Filesystem;

class Installer
{
    public static function postInstall(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $packageDir = $vendorDir.'/krugozor/russian-bad-words';
        $projectRoot = dirname($vendorDir);

        // 1. Определяем путь через конфиг или используем vendor/krugozor/dictionaries
        $targetDir = self::getTargetDirectory($event, $projectRoot);

        // 2. Создаём директорию
        (new Filesystem())->ensureDirectoryExists($targetDir);

        // 3. Копируем файлы
        $sourceDir = $packageDir.'/dictionary';
        foreach (glob($sourceDir.'/*.php') as $file) {
            $target = $targetDir.'/'.basename($file);
            if (!copy($file, $target)) {
                throw new \RuntimeException("Failed to copy {$file} to {$target}");
            }
        }
    }

    private static function getTargetDirectory(Event $event, string $projectRoot): string
    {
        // Вариант 1: Через extra-конфиг в composer.json проекта
        $extra = $event->getComposer()->getPackage()->getExtra();
        if (isset($extra['russian-bad-words']['target-dir'])) {
            return $projectRoot.'/'.$extra['russian-bad-words']['target-dir'];
        }

        // Вариант 2: Стандартное расположение
        return $projectRoot.'/vendor/krugozor/dictionaries';
    }
}