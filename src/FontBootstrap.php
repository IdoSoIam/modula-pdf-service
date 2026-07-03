<?php

declare(strict_types=1);

namespace ModulaPdfService;

use Com\Tecnick\Pdf\Font\Import;

final class FontBootstrap
{
    public static function ensure(string $projectRoot, string $fontsCustomDir): string
    {
        $customFontsDir = realpath($fontsCustomDir);
        if ($customFontsDir === false) {
            throw new \RuntimeException('Impossible de resoudre le dossier de polices custom.');
        }

        $existingFamily = self::detectExistingFamily($customFontsDir);
        if ($existingFamily !== null) {
            return $existingFamily;
        }

        $windowsFonts = [
            ['arial', 'C:\\Windows\\Fonts\\arial.ttf'],
            ['arialbd', 'C:\\Windows\\Fonts\\arialbd.ttf'],
            ['ariali', 'C:\\Windows\\Fonts\\ariali.ttf'],
            ['arialbi', 'C:\\Windows\\Fonts\\arialbi.ttf'],
        ];

        $family = null;
        foreach ($windowsFonts as [$expectedFamily, $sourceFontPath]) {
            if (!is_file($sourceFontPath)) {
                continue;
            }

            $imported = self::importFont($expectedFamily, $sourceFontPath, $customFontsDir);
            if ($family === null && str_ends_with($expectedFamily, 'arial')) {
                $family = $imported;
            }
        }

        if ($family !== null) {
            return $family;
        }

        throw new \RuntimeException(
            'Aucune police utilisable n a ete trouvee. Fournissez les fichiers convertis dans storage/fonts/custom ou des polices TTF sur le serveur.'
        );
    }

    private static function detectExistingFamily(string $customFontsDir): ?string
    {
        $preferredFamilies = ['arial', 'modulaarial'];
        foreach ($preferredFamilies as $family) {
            if (is_file($customFontsDir . DIRECTORY_SEPARATOR . $family . '.json')) {
                return $family;
            }
        }

        $jsonFonts = glob($customFontsDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($jsonFonts as $fontPath) {
            $family = pathinfo($fontPath, PATHINFO_FILENAME);
            if (is_string($family) && $family !== '') {
                return strtolower($family);
            }
        }

        return null;
    }

    private static function importFont(string $expectedFamily, string $sourceFontPath, string $customFontsDir): string
    {
        $convertedFontPath = $customFontsDir . DIRECTORY_SEPARATOR . $expectedFamily . '.json';
        if (is_file($convertedFontPath)) {
            return $expectedFamily;
        }

        try {
            $import = new Import(
                $sourceFontPath,
                $customFontsDir . DIRECTORY_SEPARATOR,
                'TrueTypeUnicode',
                '',
                32,
                3,
                1,
                false,
            );

            return strtolower($import->getFontName());
        } catch (\Throwable $error) {
            if (
                str_contains($error->getMessage(), 'already imported:')
                && preg_match('/([a-z0-9_\-]+)\.json$/i', $error->getMessage(), $match) === 1
            ) {
                return strtolower((string) $match[1]);
            }

            throw new \RuntimeException(
                'Echec import police "' . $expectedFamily . '": ' . $error->getMessage(),
                0,
                $error,
            );
        }
    }
}
