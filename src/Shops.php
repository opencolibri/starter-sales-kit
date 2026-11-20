<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Dataset\Dataset;
use OpenColibri\Starter\Core\Dataset\Datasets;
use OpenColibri\Starter\Core\Workspace;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * The demo shops of the sales kit: each one is a copy of a dataset in .castor/import/var/<slug>/ (so its settings and
 * images can change without touching the dataset), with a project.yaml holding its name, the channel it is loaded
 * into, the password of its demo customer and what the extensions keep (theme, private, admin password…).
 *
 * The admin of the sales kit reads these directories: keep the layout and the project.yaml keys stable.
 */
final class Shops
{
    public const string CONFIG_FILE = 'project.yaml';

    public static function varDir(): string
    {
        return Workspace::importDir() . '/var';
    }

    public static function dir(string $slug): string
    {
        return self::varDir() . '/' . $slug;
    }

    public static function exists(string $slug): bool
    {
        return '' !== $slug && !str_starts_with($slug, '.') && Dataset::isDataset(self::dir($slug));
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $slugs = [];

        foreach (glob(self::varDir() . '/*', \GLOB_ONLYDIR) ?: [] as $path) {
            if (self::exists(basename($path))) {
                $slugs[] = basename($path);
            }
        }

        sort($slugs);

        return $slugs;
    }

    public static function get(string $slug): string
    {
        $slug = trim($slug);

        if (!self::exists($slug)) {
            throw new \RuntimeException(\sprintf('Unknown shop "%s". Run "castor sylius:shop:list" to list the shops.', $slug));
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(string $slug): array
    {
        $file = self::dir($slug) . '/' . self::CONFIG_FILE;
        $config = is_file($file) ? Yaml::parseFile($file) : null;

        return \is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function writeConfig(string $slug, array $config): void
    {
        (new Filesystem())->dumpFile(self::dir($slug) . '/' . self::CONFIG_FILE, Yaml::dump($config, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * The catalog of the shop, read like a dataset.
     */
    public static function catalog(string $slug): Dataset
    {
        return new Dataset($slug, self::dir($slug));
    }

    public static function name(string $slug): string
    {
        $name = trim((string) (self::config($slug)['name'] ?? ''));

        return '' !== $name ? $name : self::catalog($slug)->name();
    }

    /**
     * Copies a dataset into a new shop (catalog and images), ready for sylius:shop:generate.
     *
     * @return string the shop slug
     */
    public static function create(string $datasetSlug, ?string $slug = null, ?string $name = null, ?string $description = null): string
    {
        $dataset = Datasets::get($datasetSlug);
        $name = trim((string) $name);
        $slug = self::slugify('' !== trim((string) $slug) ? (string) $slug : ('' !== $name ? $name : $datasetSlug));

        if (self::exists($slug)) {
            throw new \RuntimeException(\sprintf('Shop "%s" already exists: delete it first, or choose another name.', $slug));
        }

        $filesystem = new Filesystem();

        foreach ([Dataset::PRODUCTS_FILE, Dataset::COLLECTIONS_FILE] as $file) {
            if (is_file($dataset->path . '/' . $file)) {
                $filesystem->copy($dataset->path . '/' . $file, self::dir($slug) . '/' . $file, true);
            }
        }

        if (is_dir($dataset->mediaDir())) {
            $filesystem->mirror($dataset->mediaDir(), self::dir($slug) . '/' . Dataset::MEDIA_DIR, options: ['override' => true]);
        }

        $description = trim((string) $description);
        self::writeConfig($slug, [
            'slug' => $slug,
            'name' => '' !== $name ? $name : $dataset->name(),
            'description' => '' !== $description ? $description : (string) $dataset->configValue('description'),
            'url' => (string) $dataset->configValue('url'),
            // How the catalog was made: the admin shows it.
            'mode' => 'ai' === $dataset->configValue('source') || $dataset->builtIn ? 'ai' : 'existing',
            'dataset' => $dataset->slug,
            'shop_password' => self::generatePassword(),
            'shop_images' => ShopImages::presence($slug),
        ]);

        return $slug;
    }

    /**
     * Where the shop is loaded: a channel of its own on "<subdomain>.<domain>", or the default channel when it has no
     * subdomain. Kept in project.yaml when the fixtures are generated; a shop never generated gets its own channel.
     */
    public static function target(string $slug, App $app): CatalogTarget
    {
        $shop = self::config($slug)['shop'] ?? null;
        $channel = \is_array($shop) ? trim((string) ($shop['channel'] ?? '')) : '';
        $subdomain = \is_array($shop) ? trim((string) ($shop['subdomain'] ?? '')) : $slug;

        if ('' === $channel) {
            $channel = self::channelCode($slug);
            $subdomain = $slug;
        }

        return new CatalogTarget(
            $slug,
            self::name($slug),
            $channel,
            '' !== $subdomain ? self::hostname($app, $subdomain) : null,
            self::customerEmail($slug),
        );
    }

    /**
     * @param string|null $subdomain null or "" for the default channel
     */
    public static function persistTarget(string $slug, ?string $subdomain, App $app): CatalogTarget
    {
        $subdomain = null !== $subdomain && '' !== trim($subdomain) ? trim($subdomain) : null;
        $config = self::config($slug);
        $config['shop'] = [
            'channel' => null === $subdomain ? CatalogTarget::DEFAULT_CHANNEL : self::channelCode($slug),
            'subdomain' => $subdomain,
        ];
        self::writeConfig($slug, $config);

        return self::target($slug, $app);
    }

    public static function hostname(App $app, ?string $subdomain): string
    {
        $domain = trim((string) $app->domain());
        $domain = '' !== $domain ? $domain : 'app.test';

        return null !== $subdomain && '' !== $subdomain ? $subdomain . '.' . $domain : $domain;
    }

    public static function channelCode(string $slug): string
    {
        $code = preg_replace('/[^A-Z0-9_]/', '', strtoupper(str_replace('-', '_', $slug))) ?? '';

        return '' !== $code ? $code : 'SHOP';
    }

    /**
     * The demo customer of the shop: its storefront login, with the password of project.yaml.
     */
    public static function customerEmail(string $slug): string
    {
        return $slug . '@shop.local';
    }

    public static function customerPassword(string $slug): string
    {
        $config = self::config($slug);
        $password = trim((string) ($config['shop_password'] ?? ''));

        if ('' === $password) {
            $password = self::generatePassword();
            $config['shop_password'] = $password;
            self::writeConfig($slug, $config);
        }

        return $password;
    }

    public static function generatePassword(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';

        for ($index = 0; $index < 12; ++$index) {
            $password .= $characters[random_int(0, \strlen($characters) - 1)];
        }

        return $password;
    }

    public static function slugify(string $name): string
    {
        $slug = mb_strtolower(trim($name));

        if (\function_exists('transliterator_transliterate')) {
            $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $slug) ?: $slug;
        }

        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');

        if ('' === $slug) {
            throw new \InvalidArgumentException('The shop name must contain at least one letter or digit.');
        }

        return $slug;
    }
}
