<?php

declare(strict_types=1);

namespace App\Fixture;

use App\Entity\Channel\ImportPrivateShopAwareInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Makes a demo shop channel private (only its demo customer sees the storefront) or public again. Loaded after
 * the channel and shop user fixtures: the channel is recreated, and the demo customer is marked as verified, as
 * channels requiring account verification refuse the login of an unverified one.
 */
final class ImportPrivateShopFixture extends AbstractFixture
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param UserRepositoryInterface<ShopUserInterface>  $shopUserRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.channel')]
        private readonly ChannelRepositoryInterface $channelRepository,
        #[Autowire(service: 'sylius.manager.channel')]
        private readonly ObjectManager $channelManager,
        #[Autowire(service: 'sylius.repository.shop_user')]
        private readonly UserRepositoryInterface $shopUserRepository,
    ) {}

    public function getName(): string
    {
        return 'import_private_shop';
    }

    public function load(array $options): void
    {
        foreach ($options['custom'] as $item) {
            $channel = $this->channelRepository->findOneByCode($item['channel']);

            if (!$channel instanceof ImportPrivateShopAwareInterface) {
                continue;
            }

            $channel->setPrivateShopUserEmail($item['shop_user_email']);
            $this->verifyShopUser($item['shop_user_email']);
        }

        $this->channelManager->flush();
    }

    private function verifyShopUser(?string $email): void
    {
        $shopUser = null !== $email ? $this->shopUserRepository->findOneByEmail($email) : null;

        if ($shopUser instanceof ShopUserInterface && !$shopUser->isVerified()) {
            $shopUser->setVerifiedAt(new \DateTime());
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('custom')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('channel')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('shop_user_email')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
