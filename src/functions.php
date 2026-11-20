<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Service\SyliusService;
use OpenColibri\Starter\Core\Task\TaskProviderRegistry;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensions;
use OpenColibri\Starter\SalesKit\Extension\ChannelAdminExtension;
use OpenColibri\Starter\SalesKit\Extension\PrivateShopExtension;
use OpenColibri\Starter\SalesKit\Extension\ShopCustomerExtension;
use OpenColibri\Starter\SalesKit\Extension\ShopThemeExtension;
use OpenColibri\Starter\SalesKit\Tasks\ShopTasks;

TaskProviderRegistry::register(
    'sales-kit',
    static fn(SyliusService $service): iterable => (new ShopTasks(new App($service->getName(), $service->getDirectory(), $service->getDomains()[0] ?? null)))(),
);

// Every demo shop gets a demo customer, its own theme, can be private and gets an admin restricted to its channel.
FixturesExtensions::register(ShopCustomerExtension::NAME, new ShopCustomerExtension());
FixturesExtensions::register(ShopThemeExtension::NAME, new ShopThemeExtension());
FixturesExtensions::register(PrivateShopExtension::NAME, new PrivateShopExtension());
FixturesExtensions::register(ChannelAdminExtension::NAME, new ChannelAdminExtension());
