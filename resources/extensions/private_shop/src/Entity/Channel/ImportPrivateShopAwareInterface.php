<?php

declare(strict_types=1);

namespace App\Entity\Channel;

/**
 * A demo shop channel can be private: only its demo customer, once logged in, sees the storefront.
 */
interface ImportPrivateShopAwareInterface
{
    /**
     * The email of the only shop user allowed on the storefront, or null when the shop is public.
     */
    public function getPrivateShopUserEmail(): ?string;

    public function setPrivateShopUserEmail(?string $privateShopUserEmail): void;
}
