<?php

namespace Krugozor\RussianBadWords;

use Composer\Script\Event;
use Composer\Util\Filesystem;
use Composer\Installer\PackageEvent;

class Installer
{
    public static function postInstall(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $packageName = implode(DIRECTORY_SEPARATOR, ['krugozor', 'russian-bad-words']);
        $packageDir = implode(DIRECTORY_SEPARATOR, [$vendorDir, $packageName]);

        // Проверяем, установлен ли ещё пакет
        if (!file_exists($packageDir)) {
            return;
        }

        $projectRoot = dirname($vendorDir);

        // Пути к файлам
        $sourceDir = implode(DIRECTORY_SEPARATOR, [$packageDir, 'dictionaries']);
        $targetDir = implode(DIRECTORY_SEPARATOR, [$projectRoot, 'dictionaries']);

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
        foreach (glob($sourceDir . DIRECTORY_SEPARATOR . '*.php') as $sourceFile) {
            $filename = basename($sourceFile);
            $targetFile = $targetDir . DIRECTORY_SEPARATOR . $filename;

            // Файл существует
            if (file_exists($targetFile)) {
                // Сравниваем содержимое
                if (md5_file($sourceFile) !== md5_file($targetFile)) {
                    // Делаем резервную копию перед обновлением
                    $backupFile = $targetDir . DIRECTORY_SEPARATOR . 'backup_' . date('Y-m-d_H:i:s') . $filename;
                    copy($targetFile, $backupFile);

                    copy($sourceFile, $targetFile);
                    $updatedFiles++;
                    echo "[UPDATED] {$targetFile} (backup saved as $backupFile)\n";
                } else {
                    $skippedFiles++;
                    echo "[SKIPPED] {$targetFile} (no changes)\n";
                }
            } else {
                // Новый файл
                copy($sourceFile, $targetFile);
                $newFiles++;
                echo "[ADDED] {$targetFile}\n";
            }
        }

        // Итоговый отчёт
        echo "\nOperation complete:\n";
        echo "- New files added: {$newFiles}\n";
        echo "- Files updated: {$updatedFiles} (backups created)\n";
        echo "- Files skipped: {$skippedFiles}\n";
        echo "\nNote: User-modified files are preserved automatically.\n";
    }

    public static function preUninstall(PackageEvent $event)
    {
        $io = $event->getIO();
        $package = $event->getOperation()->getPackage();

        $io->write([
            '',
            '<info>=== Russian Bad Words Package Removal ===</info>',
            '=========================================',
            sprintf('<comment>Package:</comment>    %s', $package->getName()),
            sprintf('<comment>Version:</comment>     %s', $package->getPrettyVersion()),
            '',
            '<fg=yellow>NOTICE: Dictionary files preservation</>',
            '• Your custom dictionary files in /dictionaries/',
            '• Will NOT be modified or removed',
            '',
            '<comment>Why?</comment>',
            '• To protect your custom word modifications',
            '• To prevent accidental data loss',
            '',
            '<info>Uninstallation completed safely</info>',
            '=========================================',
            ''
        ]);
    }
}