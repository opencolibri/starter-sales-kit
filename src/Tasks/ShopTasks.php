<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Tasks;

use Castor\Api\Attribute\AsApi;
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;
use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Theme\ThemeName;
use OpenColibri\Starter\Fixtures\ApplicationScaffold;
use OpenColibri\Starter\Fixtures\CatalogLoader;
use OpenColibri\Starter\SalesKit\Extension\ChannelAdminExtension;
use OpenColibri\Starter\SalesKit\Extension\PrivateShopExtension;
use OpenColibri\Starter\SalesKit\Extension\ShopThemeExtension;
use OpenColibri\Starter\SalesKit\ShopImages;
use OpenColibri\Starter\SalesKit\Shops;
use OpenColibri\Starter\SalesKit\VarPush;
use Symfony\Component\Filesystem\Filesystem;

use function Castor\io;

/**
 * The demo shops of the sales kit, run by its admin through castor-api. See docs/sales-kit/shops.md.
 */
final readonly class ShopTasks
{
    public function __construct(
        private App $app,
    ) {}

    /**
     * @return iterable<int, array{task: AsTask, function: callable}>
     */
    public function __invoke(): iterable
    {
        yield [
            'task' => new AsTask('list', 'sylius:shop', 'List the demo shops (JSON when not run in a terminal)'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi] function (): void {
                self::writeList(array_map(fn(string $slug): array => self::describe($this->app, $slug), Shops::slugs()));
            },
        ];

        yield [
            'task' => new AsTask('create', 'sylius:shop', 'Create a demo shop from a dataset, ready for sylius:shop:generate'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi(lock: 'shop:{shop}')] function (
                #[AsArgument(description: 'Dataset slug (see sylius:dataset:list)')]
                string $dataset,
                #[AsOption(description: 'Shop slug (defaults to the slug of the name, or the dataset slug)')]
                ?string $shop = null,
                #[AsOption(description: 'Shop name (defaults to the dataset name)')]
                ?string $name = null,
                #[AsOption(description: 'Shop description (defaults to the dataset description)')]
                ?string $description = null,
            ): void {
                self::run(static function () use ($dataset, $shop, $name, $description): void {
                    $slug = Shops::create(trim($dataset), $shop, $name, $description);
                    io()->success(\sprintf('Shop %s created from dataset %s.', $slug, trim($dataset)));
                    io()->writeln(\sprintf('Next: castor sylius:shop:generate %1$s, then castor sylius:shop:load %1$s', $slug));
                });
            },
        ];

        yield [
            'task' => new AsTask('generate', 'sylius:shop', 'Generate the fixtures of a demo shop (no database change)'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi(async: true, lock: 'shop:{shop}')] function (
                #[AsArgument]
                string $shop,
                #[AsOption(description: 'Subdomain of its own channel (the shop slug by default, "" for the default channel)')]
                ?string $subdomain = null,
                #[AsOption(description: 'Maximum number of products')]
                ?int $limit = null,
            ): void {
                $this->run(function () use ($shop, $subdomain, $limit): void {
                    $slug = Shops::get($shop);
                    ApplicationScaffold::ensure($this->app);
                    $target = Shops::persistTarget($slug, $subdomain ?? $slug, $this->app);

                    io()->title(\sprintf('Generating the fixtures of shop %s', $slug));
                    (new CatalogLoader($this->app))->generate(Shops::catalog($slug), $target, $limit);
                    ShopImages::publish($this->app);
                    io()->success(\sprintf('Fixtures of shop %s generated (channel %s, %s).', $slug, $target->channelCode, $target->hostname ?? Shops::hostname($this->app, null)));
                });
            },
        ];

        yield [
            'task' => new AsTask('load', 'sylius:shop', 'Load the fixtures of a demo shop into Sylius, replacing its previous catalog'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi(async: true, lock: ['shop:{shop}', 'sylius:database'])] function (
                #[AsArgument]
                string $shop,
            ): void {
                $this->run(function () use ($shop): void {
                    $slug = Shops::get($shop);
                    io()->title(\sprintf('Loading shop %s', $slug));
                    (new CatalogLoader($this->app))->load(Shops::target($slug, $this->app));
                    io()->success(\sprintf('Shop %s loaded.', $slug));
                });
            },
        ];

        yield [
            'task' => new AsTask('delete', 'sylius:shop', 'Delete a demo shop: its channel and catalog in Sylius, and its files'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi(async: true, lock: ['shop:{shop}', 'sylius:database'])] function (
                #[AsArgument]
                string $shop,
            ): void {
                $this->run(function () use ($shop): void {
                    $slug = Shops::get($shop);

                    if (stream_isatty(\STDIN) && !io()->confirm(\sprintf('Delete shop "%s" and its Sylius channel and catalog?', $slug), false)) {
                        io()->comment('Aborted.');

                        return;
                    }

                    ApplicationScaffold::ensure($this->app);
                    $target = Shops::target($slug, $this->app);
                    (new Filesystem())->remove(Shops::dir($slug));
                    ShopImages::publish($this->app);
                    // Removes the catalog from the database and the application, then clears the cache.
                    (new CatalogLoader($this->app))->unload($target);
                    io()->success(\sprintf('Shop %s deleted.', $slug));
                });
            },
        ];

        yield [
            'task' => new AsTask('theme', 'sylius:shop', 'Pick the storefront theme of a demo shop ("default" for the Sylius look)'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi(lock: 'shop:{shop}')] function (
                #[AsArgument]
                string $shop,
                #[AsArgument]
                string $theme,
            ): void {
                $this->run(function () use ($shop, $theme): void {
                    ShopThemeExtension::set($this->app, $shop, $theme);
                    io()->success(\sprintf('The shop "%s" now uses the %s theme.', $shop, $theme));
                });
            },
        ];

        yield [
            'task' => new AsTask('visibility', 'sylius:shop', 'Make a demo shop private (only its demo customer sees the storefront) or public'),
            /** @phpstan-ignore-next-line  */
            'function' => #[AsApi(lock: 'shop:{shop}')] function (
                #[AsArgument]
                string $shop,
                #[AsArgument(description: 'public or private')]
                string $visibility,
            ): void {
                $this->run(function () use ($shop, $visibility): void {
                    if (!\in_array($visibility, ['public', 'private'], true)) {
                        throw new \InvalidArgumentException(\sprintf('Unknown visibility "%s". Use public or private.', $visibility));
                    }

                    PrivateShopExtension::set($this->app, $shop, 'private' === $visibility);
                    io()->success(\sprintf('The shop "%s" is now %s.', $shop, $visibility));
                });
            },
        ];

        yield [
            'task' => new AsTask('push', 'sylius:shop', 'Copy a demo shop (catalog, images, settings) to the stack of another server over SSH'),
            'function' => function (
                #[AsArgument]
                string $shop,
                #[AsOption(description: 'Show what would be copied')]
                bool $dryRun = false,
                #[AsOption(description: 'Remove the remote files the shop no longer has')]
                bool $delete = false,
            ): void {
                self::run(static fn() => VarPush::push(trim($shop), $dryRun, $delete));
            },
        ];
    }

    /**
     * One row of the shop grid of the admin.
     *
     * @return array<string, mixed>
     */
    public static function describe(App $app, string $slug): array
    {
        $config = Shops::config($slug);
        $catalog = Shops::catalog($slug);
        $target = Shops::target($slug, $app);
        $hostname = $target->hostname ?? Shops::hostname($app, null);
        $productCount = \count($catalog->products());
        $adminPassword = trim((string) ($config['admin_password'] ?? ''));
        $shopImages = \is_array($config['shop_images'] ?? null) ? $config['shop_images'] : ShopImages::presence($slug);

        return [
            'slug' => $slug,
            'name' => Shops::name($slug),
            'mode' => \is_string($config['mode'] ?? null) ? $config['mode'] : null,
            'hasYaml' => true,
            'hasProducts' => $productCount > 0,
            'productCount' => $productCount,
            'collectionCount' => \count($catalog->collections()),
            'hasFixtures' => (new CatalogLoader($app))->isGenerated($target),
            'channelCode' => $target->channelCode,
            'shopUrl' => 'https://' . $hostname,
            'adminUrl' => 'https://' . $hostname . '/admin',
            'shopPassword' => isset($config['shop_password']) ? (string) $config['shop_password'] : null,
            'shopUserEmail' => Shops::customerEmail($slug),
            'url' => isset($config['url']) ? (string) $config['url'] : null,
            'description' => isset($config['description']) ? (string) $config['description'] : null,
            'importedAt' => null,
            'dataset' => isset($config['dataset']) ? (string) $config['dataset'] : null,
            'shopImages' => [
                'logo' => (bool) ($shopImages['logo'] ?? false),
                'header' => (bool) ($shopImages['header'] ?? false),
                'interstice' => (bool) ($shopImages['interstice'] ?? false),
            ],
            'theme' => ShopThemeExtension::theme($config) ?? ThemeName::DEFAULT,
            'private' => PrivateShopExtension::isPrivate($config),
            // The default channel stays public.
            'canBePrivate' => !$target->isDefaultChannel(),
            'adminPassword' => '' !== $adminPassword ? $adminPassword : null,
        ];
    }

    /**
     * JSON on non-TTY (castor-api, pipes), a table when a human is at a terminal.
     *
     * @param list<array<string, mixed>> $shops
     */
    public static function writeList(array $shops, ?bool $tty = null): void
    {
        if (!($tty ?? stream_isatty(\STDOUT))) {
            io()->writeln(json_encode($shops, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return;
        }

        if ([] === $shops) {
            io()->writeln('No shops found.');

            return;
        }

        io()->table(['Name', 'Slug', 'Products', 'Fixtures', 'Channel', 'URL'], array_map(static fn(array $shop): array => [
            $shop['name'],
            $shop['slug'],
            (string) $shop['productCount'],
            $shop['hasFixtures'] ? 'yes' : 'no',
            $shop['channelCode'],
            $shop['shopUrl'],
        ], $shops));
    }

    /**
     * Errors end the task with a non-zero exit code, so castor-api reports the run as failed.
     */
    private static function run(callable $callback): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            throw new ProblemException($exception->getMessage(), previous: $exception);
        }
    }
}
