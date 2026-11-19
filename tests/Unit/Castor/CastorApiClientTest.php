<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Tests\Unit\Castor;

use OpenColibri\Starter\SalesKit\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKit\Castor\RunConflictException;
use OpenColibri\Starter\SalesKit\Tests\Support\FakeCastorApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CastorApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        FakeCastorApi::reset();
    }

    public function testItRunsATaskWithItsPayloadAndToken(): void
    {
        FakeCastorApi::client()->run('sales-kit:shop:theme', ['project' => 'cellar', 'theme' => 'drive']);

        static::assertSame([[
            'method' => 'POST',
            'task' => 'sales-kit:shop:theme',
            'action' => 'run',
            'payload' => ['project' => 'cellar', 'theme' => 'drive'],
            'token' => 'secret-token',
        ]], FakeCastorApi::calls());
    }

    public function testItSendsNoTokenWhenNoneIsConfigured(): void
    {
        FakeCastorApi::client(null)->listShops();
        FakeCastorApi::client('')->listShops();

        static::assertSame([null, null], array_column(FakeCastorApi::calls(), 'token'));
    }

    public function testItReportsTheOutputOfAFailedTask(): void
    {
        FakeCastorApi::fail('sales-kit:theme:header', 'The "drive" theme does not support the "inline" menu placement.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "drive" theme does not support the "inline" menu placement.');

        FakeCastorApi::client()->run('sales-kit:theme:header', ['theme' => 'drive', 'menu' => 'inline']);
    }

    public function testItStartsARun(): void
    {
        static::assertSame(['id' => 'run-1', 'task' => 'sylius:import:fixtures:load', 'status' => 'pending'], FakeCastorApi::client()->start('sylius:import:fixtures:load', ['project' => 'cellar']));
    }

    public function testItReportsTheRunThatHoldsTheLock(): void
    {
        $client = new CastorApiClient(new MockHttpClient(new MockResponse(json_encode([
            'error' => 'A task is running.',
            'conflict' => ['task' => 'sylius:import:fixtures:load', 'payload' => ['project' => 'cellar']],
        ], \JSON_THROW_ON_ERROR), ['http_code' => 409])), 'http://castor-api.test', null);

        try {
            $client->start('sylius:import:delete', ['project' => 'cellar']);
            static::fail('No conflict reported.');
        } catch (RunConflictException $exception) {
            static::assertSame('sylius:import:fixtures:load', $exception->conflict['task']);
        }
    }

    public function testItReportsAnUnreachableCastorApi(): void
    {
        $client = new CastorApiClient(new MockHttpClient(static fn() => throw new TransportException('Connection refused')), 'http://castor-api.test/', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to reach castor-api at http://castor-api.test: Connection refused');

        $client->listShops();
    }

    public function testItReadsTheJsonAfterTheNoticesOfCastor(): void
    {
        FakeCastorApi::output('sylius:import:dataset:list', "A new Castor version is available.\n[{\"slug\": \"cellar\"}]");

        static::assertSame([['slug' => 'cellar']], FakeCastorApi::client()->listDatasets());
    }

    public function testItReadsTheThemesAndTheirHeaderLayout(): void
    {
        $catalog = FakeCastorApi::client()->themeCatalog();

        static::assertSame(['default', 'canvas', 'cellar', 'drive'], $catalog['themes']);
        static::assertSame(['menu' => 'bar', 'search' => 'inline', 'menus' => ['bar'], 'searches' => ['none', 'inline', 'below', 'icon']], $catalog['headers']['drive']);
    }

    public function testItIgnoresTheInvalidHeaderLayouts(): void
    {
        FakeCastorApi::output('sales-kit:theme:list', '{"themes": ["canvas", 3], "headers": {"canvas": {"menu": "inline"}, "drive": "bar"}}');

        static::assertSame(['themes' => ['canvas'], 'headers' => []], FakeCastorApi::client()->themeCatalog());
    }

    public function testItAcceptsAThemeListWithoutHeaderLayouts(): void
    {
        FakeCastorApi::output('sales-kit:theme:list', '{"themes": ["default", "canvas"]}');

        static::assertSame(['default', 'canvas'], FakeCastorApi::client()->listThemes());
    }
}
