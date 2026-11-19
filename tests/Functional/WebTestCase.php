<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Tests\Functional;

use OpenColibri\Starter\SalesKit\Tests\Application\Kernel;
use OpenColibri\Starter\SalesKit\Tests\Support\FakeCastorApi;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class WebTestCase extends BaseWebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        FakeCastorApi::reset();
    }

    protected static function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_USER']));

        return $client;
    }
}
