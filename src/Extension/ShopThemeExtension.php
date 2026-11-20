<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Dataset\Datasets;
use OpenColibri\Starter\Core\Theme\ThemeName;
use OpenColibri\Starter\Fixtures\CatalogFixtures;
use OpenColibri\Starter\Fixtures\CatalogLoader;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensionInterface;
use OpenColibri\Starter\SalesKit\Shops;

use function Castor\run;

/**
 * One storefront theme per shop, for several shops loaded on their own channel in one Sylius app:
 *  - the theme of a shop is kept in its project.yaml ("theme"), the theme of its dataset until one is picked;
 *  - the import_channel_theme fixture (copied into the app) sets it on the channel each time the fixtures recreate it;
 *  - sylius:shop:list gives the "theme" of each shop.
 *
 * The themes are the ones of opencolibri/starter-themes installed with the theme-bundle target: pick one with set(),
 * which applies it right away through "castor sylius:theme:channel".
 */
final class ShopThemeExtension implements FixturesExtensionInterface
{
    public const string NAME = 'shop_theme';

    /**
     * The theme of a shop: the one picked for it, else the one of its dataset. Null when neither has one.
     *
     * @param array<string, mixed> $config the project.yaml of the shop
     */
    public static function theme(array $config): ?string
    {
        $theme = trim((string) ($config['theme'] ?? ''));

        if ('' === $theme && '' !== trim((string) ($config['dataset'] ?? ''))) {
            $theme = (string) Datasets::find(trim((string) $config['dataset']))?->configValue('theme');
        }

        return '' !== $theme ? $theme : null;
    }

    /**
     * Picks the theme of a shop ("default" for the Sylius look): applies it to its channel, keeps it in its
     * project.yaml, and in its fixtures when they are generated.
     *
     * @throws \RuntimeException for an unknown shop, or when the theme cannot be applied (not installed...)
     */
    public static function set(App $app, string $shop, string $theme): void
    {
        $target = Shops::target(Shops::get($shop), $app);

        // First, so that a theme sylius:theme:channel refuses is not kept.
        run([getenv('CASTOR_BINARY') ?: 'castor', 'sylius:theme:channel', '--no-interaction', $target->channelCode, $theme]);

        $config = Shops::config($shop);
        $config['theme'] = $theme;
        Shops::writeConfig($shop, $config);

        // The next load sets it again on the channel it recreates.
        $loader = new CatalogLoader($app);

        if ($loader->isGenerated($target)) {
            $loader->writeExtensionFixtures($target);
        }
    }

    public function scaffold(App $app): void
    {
        OwnedFiles::copy($app, 'extensions/' . self::NAME);
    }

    public function buildFixtures(CatalogTarget $target): array
    {
        $theme = Shops::exists($target->key) ? self::theme(Shops::config($target->key)) : null;

        if (null === $theme) {
            return [];
        }

        return [
            // After the channel fixture, which recreates the channel.
            'channel_theme' => CatalogFixtures::suite('import_channel_theme', 'import_channel_theme', [
                ['channel' => $target->channelCode, 'theme_name' => ThemeName::of($theme)],
            ], priority: -100),
        ];
    }
}
