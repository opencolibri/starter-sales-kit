<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Twig\Components;

use OpenColibri\Starter\SalesKit\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class DeleteShop
{
    use DefaultActionTrait;
    use StartsRunTrait;

    #[LiveProp]
    public string $slug = '';

    /** Size of the button: "sm" in tables, "default" on the shop page. */
    #[LiveProp]
    public string $size = 'sm';

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    #[LiveAction]
    public function delete(): void
    {
        $this->startRun('sylius:import:delete', ['project' => $this->slug]);
    }
}
