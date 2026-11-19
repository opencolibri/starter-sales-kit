<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Tests\Functional\Component;

use OpenColibri\Starter\SalesKit\Tests\Support\FakeCastorApi;
use OpenColibri\Starter\SalesKit\Twig\Components\RunMonitor;

final class RunActionsTest extends ComponentTestCase
{
    public function testItStartsTheFixturesLoadAndHandsTheRunToTheMonitor(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ShopActions', ['shop' => ['slug' => 'cellar', 'mode' => 'ai', 'hasProducts' => true, 'hasFixtures' => true]])
            ->call('load');

        static::assertSame([['project' => 'cellar']], array_column(FakeCastorApi::calls('sylius:import:fixtures:load'), 'payload'));
        $this->assertComponentEmitEvent($component, RunMonitor::STARTED_EVENT)->withData(['runId' => 'run-1', 'task' => 'sylius:import:fixtures:load']);
    }

    public function testItGeneratesTheFixturesOfAnExistingSiteWithItsLimit(): void
    {
        $this->createAdminLiveComponent('SalesKit:ShopActions', ['shop' => ['slug' => 'atelier', 'mode' => 'existing', 'hasProducts' => true]])
            ->set('limit', 30)
            ->call('generate');

        static::assertSame([['mode' => 'existing', 'project' => 'atelier', 'subdomain' => 'atelier', 'limit' => 30]], array_column(FakeCastorApi::calls('sylius:import:fixtures:generate'), 'payload'));
    }

    public function testItChecksTheShopBeforeStartingARun(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ShopActions', ['shop' => ['slug' => 'atelier', 'mode' => 'existing', 'hasProducts' => true, 'hasFixtures' => false]]);

        static::assertStringContainsString('Generate fixtures before loading them into Sylius.', (string) $component->call('load')->render());
        static::assertStringContainsString('between 1 and 500', (string) $component->set('limit', 0)->call('generate')->render());
        static::assertSame([], FakeCastorApi::calls());
    }

    public function testItNamesTheRunThatHoldsTheLock(): void
    {
        FakeCastorApi::conflict('sylius:import:delete', 'sylius:import:fixtures:load', ['project' => 'cellar']);

        $component = $this->createAdminLiveComponent('SalesKit:DeleteShop', ['slug' => 'cellar'])->call('delete');

        $this->assertComponentNotEmitEvent($component, RunMonitor::STARTED_EVENT);
        static::assertStringContainsString('&quot;Load into Sylius&quot; (cellar) is still running. Wait for it to finish before starting &quot;Delete shop&quot;.', (string) $component->render());
    }

    public function testItChangesTheVisibilityOfAShop(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ShopVisibility', ['project' => 'cellar', 'private' => false])->call('makePrivate');

        static::assertSame([['project' => 'cellar', 'visibility' => 'private']], array_column(FakeCastorApi::calls('sales-kit:shop:visibility'), 'payload'));
        static::assertTrue($component->component()->private);
    }
}
