<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Tests\Unit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Dataset\DatasetDirectories;
use OpenColibri\Starter\Core\Workspace;
use OpenColibri\Starter\Fixtures\CatalogLoader;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use OpenColibri\Starter\SalesKit\Extension\ChannelAdminExtension;
use OpenColibri\Starter\SalesKit\Extension\PrivateShopExtension;
use OpenColibri\Starter\SalesKit\Extension\ShopCustomerExtension;
use OpenColibri\Starter\SalesKit\Extension\ShopThemeExtension;
use OpenColibri\Starter\SalesKit\ShopImages;
use OpenColibri\Starter\SalesKit\Shops;
use OpenColibri\Starter\SalesKit\Tasks\ShopTasks;
use OpenColibri\Starter\SalesKit\VarPush;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class ShopsTest extends TestCase
{
    private string $root;
    private App $app;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sales-kit-shops-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/.castor', 0o775, true);
        mkdir($this->root . '/app', 0o775, true);
        Workspace::setRoot($this->root);
        $this->app = new App('app', $this->root . '/app', 'demo.test');

        $dataset = $this->root . '/datasets/wines';
        mkdir($dataset . '/media', 0o775, true);
        file_put_contents($dataset . '/dataset.yaml', Yaml::dump(['name' => 'Cellar', 'description' => 'Wines', 'theme' => 'cellar']));
        file_put_contents($dataset . '/collections.yaml', Yaml::dump(['collections' => [['name' => 'Red']]]));
        file_put_contents($dataset . '/products.yaml', Yaml::dump(['products' => [['title' => 'Margaux', 'collection_name' => 'Red', 'price_eur' => 60, 'image' => 'media/margaux.webp']]]));
        file_put_contents($dataset . '/media/margaux.webp', 'bottle');
        DatasetDirectories::register('test', $this->root . '/datasets');
    }

    protected function tearDown(): void
    {
        DatasetDirectories::unregister('test');
        Workspace::setRoot(null);
        (new Filesystem())->remove($this->root);
    }

    public function testCreatesAShopFromADataset(): void
    {
        static::assertSame('my-cellar', Shops::create('wines', name: 'My Cellar'));

        static::assertSame(['my-cellar'], Shops::slugs());
        static::assertFileExists($this->root . '/.castor/import/var/my-cellar/products.yaml');
        static::assertSame('bottle', file_get_contents($this->root . '/.castor/import/var/my-cellar/media/margaux.webp'));
        $config = Shops::config('my-cellar');
        static::assertSame('My Cellar', $config['name']);
        static::assertSame('Wines', $config['description']);
        static::assertSame('wines', $config['dataset']);
        static::assertSame(12, \strlen((string) $config['shop_password']));
        static::assertSame('Margaux', Shops::catalog('my-cellar')->products()[0]['title']);

        $this->expectExceptionMessage('Shop "my-cellar" already exists');
        Shops::create('wines', name: 'My Cellar');
    }

    public function testLoadsAShopOnItsOwnChannelOrOnTheDefaultOne(): void
    {
        Shops::create('wines');
        $target = Shops::target('wines', $this->app);

        static::assertSame('WINES', $target->channelCode);
        static::assertSame('wines.demo.test', $target->hostname);
        static::assertSame('wines@shop.local', $target->customerEmail);
        static::assertSame('php bin/console sylius:import:channel:reset WINES --prefix=wines --shop-email=wines@shop.local -n', $target->resetCommand());

        $target = Shops::persistTarget('wines', '', $this->app);

        static::assertTrue($target->isDefaultChannel());
        static::assertSame(['channel' => 'WEB_STORE', 'subdomain' => null], Shops::config('wines')['shop']);
        static::assertStringEndsWith('--keep-channel -n', $target->resetCommand());
    }

    public function testExtensionsAddTheFixturesOfTheShopsOnly(): void
    {
        Shops::create('wines');
        $target = Shops::target('wines', $this->app);
        $other = CatalogTarget::defaultChannel('Catalog');

        foreach ([new ShopCustomerExtension(), new ShopThemeExtension(), new PrivateShopExtension(), new ChannelAdminExtension()] as $extension) {
            static::assertSame([], $extension->buildFixtures($other), $extension::class . ' leaves other catalogs alone.');
        }

        $user = (new ShopCustomerExtension())->buildFixtures($target)['shop_user']['sylius_fixtures']['suites']['import']['fixtures']['import_shop_user'];
        static::assertSame('wines@shop.local', $user['options']['custom'][0]['email']);
        static::assertSame(Shops::config('wines')['shop_password'], $user['options']['custom'][0]['password']);

        // The theme of the dataset until one is picked.
        $theme = (new ShopThemeExtension())->buildFixtures($target)['channel_theme']['sylius_fixtures']['suites']['import']['fixtures']['import_channel_theme'];
        static::assertSame(-100, $theme['priority']);
        static::assertSame('WINES', $theme['options']['custom'][0]['channel']);

        $private = (new PrivateShopExtension())->buildFixtures($target)['private_shop']['sylius_fixtures']['suites']['import']['fixtures']['import_private_shop'];
        static::assertNull($private['options']['custom'][0]['shop_user_email']);

        $admin = (new ChannelAdminExtension())->buildFixtures($target)['admin_user']['sylius_fixtures']['suites']['import']['fixtures']['import_admin_user'];
        static::assertSame('wines', $admin['options']['custom'][0]['username']);
        static::assertSame(Shops::config('wines')['admin_password'], $admin['options']['custom'][0]['password']);

        // A shop on the default channel stays public and keeps the admins of the default channel.
        $default = Shops::persistTarget('wines', null, $this->app);
        static::assertSame([], (new PrivateShopExtension())->buildFixtures($default));
        static::assertSame([], (new ChannelAdminExtension())->buildFixtures($default));
    }

    public function testDescribesTheShopsForTheAdmin(): void
    {
        Shops::create('wines');
        (new CatalogLoader($this->app))->generate(Shops::catalog('wines'), Shops::target('wines', $this->app));

        $shop = ShopTasks::describe($this->app, 'wines');

        static::assertSame('Cellar', $shop['name']);
        static::assertSame('ai', $shop['mode']);
        static::assertSame(1, $shop['productCount']);
        static::assertTrue($shop['hasFixtures']);
        static::assertSame('https://wines.demo.test', $shop['shopUrl']);
        static::assertSame('cellar', $shop['theme']);
        static::assertFalse($shop['private']);
        static::assertTrue($shop['canBePrivate']);
        static::assertSame(['logo' => false, 'header' => false, 'interstice' => false], $shop['shopImages']);
    }

    public function testPublishesTheShopImagesOnTheirChannel(): void
    {
        Shops::create('wines');
        file_put_contents(Shops::dir('wines') . '/media/image_logo.png', 'logo');
        mkdir($this->app->directory() . '/public/media/shops/GONE', 0o775, true);
        file_put_contents($this->app->directory() . '/public/media/shops/GONE/logo.old.png', 'old');

        ShopImages::publish($this->app);

        $config = (string) file_get_contents($this->app->directory() . '/config/import/shop_images.php');
        static::assertMatchesRegularExpression("#'imageLogo' => '/media/shops/WINES/logo\\.[0-9a-f]{16}\\.png'#", $config);
        static::assertCount(1, glob($this->app->directory() . '/public/media/shops/WINES/logo.*.png') ?: []);
        static::assertDirectoryDoesNotExist($this->app->directory() . '/public/media/shops/GONE');
    }

    public function testBuildsTheSshCommandOfThePush(): void
    {
        $config = ['host' => 'demo.example.com', 'user' => 'deploy', 'port' => 2222, 'identityFile' => '/keys/id', 'remoteDir' => '/srv/stack'];

        static::assertSame("ssh '-p' '2222' '-o' 'BatchMode=yes' '-i' '/keys/id'", VarPush::sshCommand($config));
        static::assertSame('/srv/stack/.castor/import/var/wines', VarPush::remoteDir('wines', '/srv/stack/'));
    }
}
