<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Log;
use OpenColibri\Starter\Core\Util\Yaml;
use OpenColibri\Starter\Fixtures\CatalogFixtures;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensionInterface;
use OpenColibri\Starter\SalesKit\Shops;
use Symfony\Component\Filesystem\Filesystem;

/**
 * What every demo shop gets: a demo customer (its storefront login, shown in the admin), and the logo, header and
 * interstice images uploaded in the admin (resources/application/: the storefront contributors feeding the shared
 * hero and brand slots, the shop_image() Twig function and the interstice block).
 */
final class ShopCustomerExtension implements FixturesExtensionInterface
{
    public const string NAME = 'shop_customer';

    public function scaffold(App $app): void
    {
        OwnedFiles::copy($app, 'application');

        if (!is_file($app->directory() . '/config/packages/_sylius.yaml')) {
            return;
        }

        Yaml::addImport($app, 'config/packages/_sylius.yaml', '../sylius/shop_images.yaml');
        Yaml::addImportWithOptions($app, 'config/packages/_sylius.yaml', '../import/shop_images.php', ignoreErrors: true);
        self::removeLegacyTemplates($app);
    }

    public function buildFixtures(CatalogTarget $target): array
    {
        if (!Shops::exists($target->key)) {
            return [];
        }

        return [
            'shop_user' => CatalogFixtures::suite('import_shop_user', 'import_shop_user', [
                [
                    'email' => Shops::customerEmail($target->key),
                    'password' => Shops::customerPassword($target->key),
                    'first_name' => Shops::name($target->key),
                    'last_name' => 'Customer',
                ],
            ]),
        ];
    }

    /**
     * Applications scaffolded before the shared hero and brand slots rendered the shop images from templates themes
     * also write to: remove them when they are the generated ones.
     */
    private static function removeLegacyTemplates(App $app): void
    {
        $filesystem = new Filesystem();
        $legacy = [
            'templates/shop/homepage/banner.html.twig' => "shop_image('imageHeader')",
            'templates/shop/shared/layout/header/logo.html.twig' => "shop_image('imageLogo')",
            'templates/shop/shared/layout/footer/copy.html.twig' => "shop_image('imageLogo')",
        ];

        foreach ($legacy as $file => $marker) {
            $path = $app->directory() . '/' . $file;

            if (is_file($path) && str_contains((string) file_get_contents($path), $marker)) {
                $filesystem->remove($path);
                Log::comment(\sprintf('Removed the legacy %s.', $file));
            }
        }

        $legacyLogo = $app->directory() . '/templates/bundles/SyliusShopBundle/shared/logo.html.twig';

        if (is_file($legacyLogo) && '<span class="fw-bold text-uppercase">logo</span>' === trim((string) file_get_contents($legacyLogo))) {
            $filesystem->remove($legacyLogo);
        }
    }
}
