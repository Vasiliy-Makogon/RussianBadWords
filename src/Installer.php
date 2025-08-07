<?php

namespace Krugozor\RussianBadWords;

use Composer\Script\Event;
use Composer\Util\Filesystem;

class Installer
{
    public static function postInstall(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);
        $packageDir = $vendorDir.'/krugozor/russian-bad-words';

        // Пути к файлам
        $sourceDir = $packageDir.'/dictionaries';
        $targetDir = $projectRoot.'/dictionaries';

        // Инициализация
        $fs = new Filesystem();
        $fs->ensureDirectoryExists($targetDir);

        // Статистика
        $newFiles = 0;
        $updatedFiles = 0;
        $skippedFiles = 0;

        echo "\nRussian Bad Words Dictionary Installer\n";
        echo "==================================\n";
        echo "Source: {$sourceDir}\n";
        echo "Target: {$targetDir}\n\n";

        // Проверка исходной директории
        if (!is_dir($sourceDir)) {
            throw new \RuntimeException("Source directory not found: {$sourceDir}");
        }

        // Обработка файлов
        foreach (glob($sourceDir.'/*.php') as $sourceFile) {
            $filename = basename($sourceFile);
            $targetFile = $targetDir.'/'.$filename;

            // Файл существует
            if (file_exists($targetFile)) {
                // Сравниваем содержимое
                if (md5_file($sourceFile) !== md5_file($targetFile)) {
                    // Делаем резервную копию перед обновлением
                    $backupFile = $targetDir.'/backup_'.$filename;
                    copy($targetFile, $backupFile);

                    copy($sourceFile, $targetFile);
                    $updatedFiles++;
                    echo "[UPDATED] {$filename} (backup saved as backup_{$filename})\n";
                } else {
                    $skippedFiles++;
                    echo "[SKIPPED] {$filename} (no changes)\n";
                }
            } else {
                // Новый файл
                copy($sourceFile, $targetFile);
                $newFiles++;
                echo "[ADDED] {$filename}\n";
            }
        }

        // Итоговый отчёт
        echo "\nOperation complete:\n";
        echo "- New files added: {$newFiles}\n";
        echo "- Files updated: {$updatedFiles} (backups created)\n";
        echo "- Files skipped: {$skippedFiles}\n";
        echo "\nNote: User-modified files are preserved automatically.\n";
    }
}