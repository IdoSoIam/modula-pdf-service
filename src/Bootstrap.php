<?php

declare(strict_types=1);

namespace ModulaPdfService;

final class Bootstrap
{
    public static function init(string $projectRoot): array
    {
        self::loadDotEnv($projectRoot);

        $storageDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $fontsSourceDir = $storageDir . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'source';
        $fontsCustomDir = $storageDir . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'custom';

        self::ensureDirectory($storageDir);
        self::ensureDirectory($fontsSourceDir);
        self::ensureDirectory($fontsCustomDir);

        $fontFamily = FontBootstrap::ensure($projectRoot, $fontsCustomDir);

        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', $fontsCustomDir);
        }

        return [
            'projectRoot' => $projectRoot,
            'storageDir' => $storageDir,
            'fontsSourceDir' => $fontsSourceDir,
            'fontsCustomDir' => $fontsCustomDir,
            'fontFamily' => $fontFamily,
            'apiKey' => trim((string) (getenv('MODULA_PDF_API_KEY') ?: '')),
            'playgroundEnabled' => self::envToBool(getenv('MODULA_PDF_ENABLE_PLAYGROUND'), false),
        ];
    }

    private static function loadDotEnv(string $projectRoot): void
    {
        $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separator));
            if ($name === '') {
                continue;
            }

            $value = trim(substr($line, $separator + 1));
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    private static function envToBool(string|false $value, bool $default): bool
    {
        if ($value === false) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private static function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException('Impossible de creer le dossier: ' . $path);
        }
    }
}
