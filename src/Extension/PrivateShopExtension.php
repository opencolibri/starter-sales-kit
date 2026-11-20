<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Log;
use OpenColibri\Starter\Core\Util\Runner;
use OpenColibri\Starter\Fixtures\CatalogFixtures;
use OpenColibri\Starter\Fixtures\CatalogLoader;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensionInterface;
use OpenColibri\Starter\SalesKit\Shops;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Private shops: only the demo customer of a shop loaded on its own channel (its storefront login and password) gets
 * into its storefront and shop API. For demos shared with one customer.
 *
 * Its scaffold copies into the app a private shop field on the channel (entity trait and migration), a voter and its
 * access_control rules, a login page, and the import_private_shop fixture and sylius:import:channel:private command
 * that set it. A shop is made private with set(), kept in its project.yaml ("private") and set again on each load.
 * The default channel stays public.
 */
final class PrivateShopExtension implements FixturesExtensionInterface
{
    public const string NAME = 'private_shop';

    /** access_control rules of the private shops, each inserted after the rule its path matches. */
    public const array ACCESS_CONTROL = [
        // Storefront: after the login, register, verify, admin and account rules, which keep their access. The shop
        // firewall also covers the web debug toolbar (dev only), which the login page loads.
        '%sylius.security.shop_regex%/account' => '        - { path: "^/_(wdt|profiler)/", role: PUBLIC_ACCESS }' . "\n"
            . '        - { path: "%sylius.security.shop_regex%", allow_if: "is_granted(\'import_private_shop_access\')" }',
        // Shop API: after the customer token rule, before the rule opening the rest of it.
        '%sylius.security.api_shop_route%/customers/token' => '        - { path: "%sylius.security.api_shop_regex%/.*", allow_if: "is_granted(\'import_private_shop_access\')" }',
    ];

    /**
     * @param array<string, mixed> $config the project.yaml of the shop
     */
    public static function isPrivate(array $config): bool
    {
        return true === ($config['private'] ?? false);
    }

    /**
     * Makes a shop private (only its demo customer gets in) or public, right away, and keeps it in its project.yaml
     * and in its fixtures when they are generated.
     *
     * @throws \RuntimeException for an unknown shop, or a shop loaded on the default channel
     */
    public static function set(App $app, string $shop, bool $private): void
    {
        $target = Shops::target(Shops::get($shop), $app);

        if ($target->isDefaultChannel()) {
            throw new \RuntimeException(\sprintf('The shop "%s" is loaded on the default channel, which stays public.', $shop));
        }

        // First, so that a shop the app could not change is not kept private.
        Runner::run($app, \sprintf(
            'php bin/console sylius:import:channel:private --no-interaction %s%s',
            escapeshellarg($target->channelCode),
            $private ? ' --shop-user-email=' . escapeshellarg(Shops::customerEmail($shop)) : '',
        ));

        $config = Shops::config($shop);
        $config['private'] = $private;
        Shops::writeConfig($shop, $config);

        // The next load sets it again on the channel it recreates.
        $loader = new CatalogLoader($app);

        if ($loader->isGenerated($target)) {
            $loader->writeExtensionFixtures($target);
        }
    }

    /**
     * sylius-standard always ships src/Entity/Channel/Channel.php, so the private shop field is wired onto it.
     *
     * @return bool whether the entity was changed
     */
    public static function wireChannelEntity(App $app): bool
    {
        $path = $app->directory() . '/src/Entity/Channel/Channel.php';

        if (!is_file($path) || str_contains((string) file_get_contents($path), 'ImportPrivateShopAwareTrait')) {
            return false;
        }

        (new PhpFile($path))
            ->addInterface('App\\Entity\\Channel\\ImportPrivateShopAwareInterface')
            ->addTrait('App\\Entity\\Channel\\ImportPrivateShopAwareTrait')
            ->save()
        ;
        Log::comment('Wired the private shop field onto src/Entity/Channel/Channel.php.');

        return true;
    }

    /**
     * The firewall config is one block (security.access_control cannot be split across files), so the rules are
     * inserted into the access_control of sylius-standard.
     */
    public static function protectStorefront(App $app): void
    {
        $path = $app->directory() . '/config/packages/security.yaml';
        $content = (string) file_get_contents($path);

        if (str_contains($content, 'import_private_shop_access')) {
            return;
        }

        foreach (self::ACCESS_CONTROL as $after => $rule) {
            $pattern = \sprintf('/^\s*- \{ path: "%s".*$/m', preg_quote($after, '/'));
            $content = preg_replace($pattern, '$0' . "\n" . $rule, $content, 1, $count) ?? $content;

            if (1 !== $count) {
                throw new \RuntimeException(\sprintf('Could not find the "%s" access_control rule in config/packages/security.yaml to protect the private shops.', $after));
            }
        }

        (new Filesystem())->dumpFile($path, $content);
        Log::comment('Added the private shop access_control rules to config/packages/security.yaml.');
    }

    public function scaffold(App $app): void
    {
        $changed = OwnedFiles::copy($app, 'extensions/' . self::NAME);
        $changed = self::wireChannelEntity($app) || $changed;
        self::protectStorefront($app);

        if ($changed) {
            Database::migrate($app);
        }
    }

    public function buildFixtures(CatalogTarget $target): array
    {
        // The default channel stays public.
        if ($target->isDefaultChannel() || !Shops::exists($target->key)) {
            return [];
        }

        return [
            // After the channel fixture, which recreates the channel.
            'private_shop' => CatalogFixtures::suite('import_private_shop', 'import_private_shop', [
                [
                    'channel' => $target->channelCode,
                    'shop_user_email' => self::isPrivate(Shops::config($target->key)) ? Shops::customerEmail($target->key) : null,
                ],
            ], priority: -100),
        ];
    }
}
