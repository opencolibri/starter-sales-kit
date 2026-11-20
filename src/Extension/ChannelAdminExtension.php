<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Log;
use OpenColibri\Starter\Core\Util\Yaml;
use OpenColibri\Starter\Fixtures\CatalogFixtures;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensionInterface;
use OpenColibri\Starter\SalesKit\Shops;

/**
 * One Sylius admin per shop loaded on its own channel, restricted to that channel: it only sees and edits the catalog
 * of its shop. For demos where each customer gets the admin of their shop.
 *
 * Its scaffold adds the channel scope on the admin user (trait and migration, wired onto AdminUser), the voter, access
 * and menu listeners and product form extension that enforce it, the product, variant and taxon repositories filtering
 * by channel (config/sylius/import_channel_admin.yaml replaces the Sylius ones), the reset listener removing the
 * admins of a reset channel and the import_admin_user fixture. Each shop gets the admin "<slug>", whose password is
 * generated once and kept in its project.yaml ("admin_password"). The default channel keeps its own admins.
 */
final class ChannelAdminExtension implements FixturesExtensionInterface
{
    public const string NAME = 'channel_admin';

    /**
     * The password of the admin of a shop, generated on the first fixtures generation and kept afterwards.
     */
    public static function password(string $shop): string
    {
        $config = Shops::config($shop);
        $password = trim((string) ($config['admin_password'] ?? ''));

        if ('' === $password) {
            $password = Shops::generatePassword();
            $config['admin_password'] = $password;
            Shops::writeConfig($shop, $config);
        }

        return $password;
    }

    /**
     * sylius-standard always ships src/Entity/User/AdminUser.php, so the channel scope is wired onto it.
     *
     * @return bool whether the entity was changed
     */
    public static function wireAdminUserEntity(App $app): bool
    {
        $path = $app->directory() . '/src/Entity/User/AdminUser.php';

        if (!is_file($path) || str_contains((string) file_get_contents($path), 'ImportChannelAdminAwareTrait')) {
            return false;
        }

        (new PhpFile($path))
            ->addInterface('App\\Entity\\User\\ImportChannelAdminAwareInterface')
            ->addTrait('App\\Entity\\User\\ImportChannelAdminAwareTrait')
            ->save()
        ;
        Log::comment('Wired the channel admin onto src/Entity/User/AdminUser.php.');

        return true;
    }

    public function scaffold(App $app): void
    {
        $changed = OwnedFiles::copy($app, 'extensions/' . self::NAME);
        $changed = self::wireAdminUserEntity($app) || $changed;
        Yaml::addImport($app, 'config/packages/_sylius.yaml', '../sylius/import_channel_admin.yaml');

        if ($changed) {
            Database::migrate($app);
        }
    }

    public function buildFixtures(CatalogTarget $target): array
    {
        // The default channel already has its own admins.
        if ($target->isDefaultChannel() || !Shops::exists($target->key)) {
            return [];
        }

        return [
            'admin_user' => CatalogFixtures::suite('import_admin_user', 'import_admin_user', [
                [
                    'channel' => $target->channelCode,
                    'username' => $target->key,
                    'password' => self::password($target->key),
                    'first_name' => Shops::name($target->key),
                    'email' => $target->key . '@import.local',
                    'import_code_prefix' => $target->codePrefix(),
                ],
            ]),
        ];
    }
}
