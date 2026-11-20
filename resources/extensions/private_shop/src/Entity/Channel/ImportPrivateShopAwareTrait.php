<?php

declare(strict_types=1);

namespace App\Entity\Channel;

use Doctrine\ORM\Mapping as ORM;

trait ImportPrivateShopAwareTrait
{
    #[ORM\Column(name: 'import_private_shop_user_email', length: 255, nullable: true)]
    private ?string $privateShopUserEmail = null;

    public function getPrivateShopUserEmail(): ?string
    {
        return $this->privateShopUserEmail;
    }

    public function setPrivateShopUserEmail(?string $privateShopUserEmail): void
    {
        $this->privateShopUserEmail = null === $privateShopUserEmail || '' === trim($privateShopUserEmail) ? null : trim($privateShopUserEmail);
    }
}
