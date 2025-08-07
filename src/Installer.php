<?php
namespace Krugozor\RussianBadWords;

use Composer\Script\Event;
use RuntimeException;

class Installer
{
    public static function postInstall(Event $event = null)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);
        $sourceDir = __DIR__ . '/../../dictionary'; // Путь к словарям внутри пакета
        $targetDir = $projectRoot . '/dictionary';   // Куда копировать (корень проекта)

        if (!is_dir($sourceDir)) {
            throw new RuntimeException("Source directory {$sourceDir} not found!");
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach (glob($sourceDir . '/*.php') as $file) {
            $targetFile = $targetDir . '/' . basename($file);
            if (!copy($file, $targetFile)) {
                throw new RuntimeException("Failed to copy {$file} to {$targetFile}");
            }
        }

        $event->getIO()->write("RussianBadWords: Dictionaries copied to {$targetDir}");
    }
}