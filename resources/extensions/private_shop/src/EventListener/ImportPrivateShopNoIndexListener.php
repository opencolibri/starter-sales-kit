<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Channel\ImportPrivateShopAwareInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps search engines away from private demo shops, login page included.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class ImportPrivateShopNoIndexListener
{
    public function __construct(
        #[Autowire(service: 'sylius.context.channel')]
        private readonly ChannelContextInterface $channelContext,
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return;
        }

        if ($channel instanceof ImportPrivateShopAwareInterface && null !== $channel->getPrivateShopUserEmail()) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
