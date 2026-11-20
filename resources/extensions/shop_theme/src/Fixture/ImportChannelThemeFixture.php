<?php

declare(strict_types=1);

namespace App\Fixture;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sets the storefront theme of an imported shop channel (a theme of opencolibri/starter-themes installed with the
 * theme-bundle target, null for the Sylius look). Loaded after the channel fixture, which recreates the channel.
 *
 * Copied into the app by the shop_theme import extension (OpenColibri\Starter\Import\Extension\ShopThemeExtension).
 */
final class ImportChannelThemeFixture extends AbstractFixture
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.channel')]
        private readonly ChannelRepositoryInterface $channelRepository,
        #[Autowire(service: 'sylius.manager.channel')]
        private readonly ObjectManager $channelManager,
    ) {}

    public function getName(): string
    {
        return 'import_channel_theme';
    }

    public function load(array $options): void
    {
        foreach ($options['custom'] as $item) {
            $channel = $this->channelRepository->findOneByCode($item['channel']);

            if (!$channel instanceof ChannelInterface) {
                continue;
            }

            $channel->setThemeName($item['theme_name']);
        }

        $this->channelManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('custom')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('channel')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('theme_name')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
