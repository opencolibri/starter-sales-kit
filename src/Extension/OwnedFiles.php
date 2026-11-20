<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Extension;

use OpenColibri\Starter\Core\App;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Files the sales kit copies into the Sylius application and keeps up to date (not meant to be customized).
 */
final class OwnedFiles
{
    public static function resourcesDir(string $name): string
    {
        return \dirname(__DIR__, 2) . '/resources/' . $name;
    }

    /**
     * @return bool whether a file was added or changed
     */
    public static function copy(App $app, string $name): bool
    {
        $filesystem = new Filesystem();
        $changed = false;

        foreach ((new Finder())->files()->in(self::resourcesDir($name)) as $file) {
            $target = $app->directory() . '/' . $file->getRelativePathname();

            if (!is_file($target) || file_get_contents($target) !== $file->getContents()) {
                $filesystem->copy($file->getPathname(), $target, true);
                $changed = true;
            }
        }

        return $changed;
    }
}
