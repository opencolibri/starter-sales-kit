<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Filesystem as CoreFilesystem;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The logo, header and interstice images the admin of the sales kit uploads for a shop, into the media/ directory of
 * the shop (image_logo.<ext>…). They are served as static files from the Sylius public directory, one directory per
 * channel (public/media/shops/<CHANNEL>/<role>.<hash>.<ext>: the hash busts browser caches), and listed by channel in
 * the app.shop_images_by_channel parameter (config/import/shop_images.php) the storefront reads.
 */
final class ShopImages
{
    public const string PUBLIC_PATH = 'media/shops';

    /** Container parameter key => storage role. */
    public const array ROLES = [
        'imageHeader' => 'image_header',
        'imageInterstice' => 'image_interstice',
        'imageLogo' => 'image_logo',
    ];

    public static function path(string $slug, string $role): ?string
    {
        if (!\in_array($role, self::ROLES, true)) {
            return null;
        }

        foreach (glob(Shops::dir($slug) . '/media/' . $role . '.*') ?: [] as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{logo: bool, header: bool, interstice: bool}
     */
    public static function presence(string $slug): array
    {
        return [
            'logo' => null !== self::path($slug, 'image_logo'),
            'header' => null !== self::path($slug, 'image_header'),
            'interstice' => null !== self::path($slug, 'image_interstice'),
        ];
    }

    /**
     * Publishes the images of every shop on its channel, and removes the ones no longer used.
     */
    public static function publish(App $app): void
    {
        $byChannel = [];

        foreach (Shops::slugs() as $slug) {
            $images = self::publishShop($app, $slug, Shops::target($slug, $app)->channelCode);

            if ([] !== $images) {
                $byChannel[Shops::target($slug, $app)->channelCode] = $images;
            }
        }

        self::prune($app, $byChannel);
        $exported = CoreFilesystem::exportArray(['parameters' => ['app.shop_images_by_channel' => $byChannel]]);

        (new Filesystem())->dumpFile(
            $app->directory() . '/config/import/shop_images.php',
            <<<PHP
                <?php

                declare(strict_types=1);

                namespace Symfony\\Component\\DependencyInjection\\Loader\\Configurator;

                return App::config({$exported});

                PHP,
        );
    }

    /**
     * @return array<string, string> parameter key => public URL
     */
    private static function publishShop(App $app, string $slug, string $channelCode): array
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]+$/', $channelCode)) {
            throw new \RuntimeException(\sprintf('Invalid channel code "%s" for shop images.', $channelCode));
        }

        $directory = self::publicDir($app) . '/' . $channelCode;
        $images = [];

        foreach (self::ROLES as $parameter => $role) {
            $path = self::path($slug, $role);
            $hash = null !== $path ? hash_file('xxh128', $path) : false;

            if (null === $path || false === $hash) {
                continue;
            }

            $filename = \sprintf('%s.%s.%s', substr($role, \strlen('image_')), substr($hash, 0, 16), strtolower(pathinfo($path, \PATHINFO_EXTENSION)));

            if (!is_file($directory . '/' . $filename)) {
                (new Filesystem())->copy($path, $directory . '/' . $filename);
            }

            $images[$parameter] = '/' . self::PUBLIC_PATH . '/' . $channelCode . '/' . $filename;
        }

        return $images;
    }

    /**
     * @param array<string, array<string, string>> $byChannel
     */
    private static function prune(App $app, array $byChannel): void
    {
        $root = self::publicDir($app);

        foreach (glob($root . '/*', \GLOB_ONLYDIR) ?: [] as $directory) {
            $keep = array_map(basename(...), array_values($byChannel[basename($directory)] ?? []));

            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file) && !\in_array(basename($file), $keep, true)) {
                    unlink($file);
                }
            }

            if ([] === (glob($directory . '/*') ?: [])) {
                rmdir($directory);
            }
        }
    }

    private static function publicDir(App $app): string
    {
        return $app->directory() . '/public/' . self::PUBLIC_PATH;
    }
}
