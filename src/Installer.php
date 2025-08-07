<?php
namespace Krugozor\RussianBadWords;

use Composer\Script\Event;
use Composer\Util\Filesystem;

class Installer
{
    public static function postInstall(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $packageDir = $vendorDir . '/krugozor/russian-bad-words';
        $projectRoot = dirname($vendorDir);

        // Проверяем существование исходной директории
        $sourceDir = $packageDir . '/dictionary';
        if (!is_dir($sourceDir)) {
            throw new \RuntimeException("Dictionary directory not found: {$sourceDir}");
        }

        // Создаем целевую директорию
        $targetDir = $projectRoot . '/public/dictionaries';
        (new Filesystem())->ensureDirectoryExists($targetDir);

        // Копируем файлы
        foreach (glob($sourceDir . '/*.php') as $file) {
            $targetFile = $targetDir . '/' . basename($file);
            if (!copy($file, $targetFile)) {
                throw new \RuntimeException("Failed to copy {$file} to {$targetFile}");
            }
        }
    }
}