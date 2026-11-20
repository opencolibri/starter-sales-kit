<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Channel\ImportPrivateShopAwareInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * On a private demo shop, the login page only asks for the access of the demo customer (no header, menu, footer
 * or register link), and the pages a customer reaches before logging in (register, account verification, password
 * reset) are closed: no account but the demo customer gets in anyway.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class ImportPrivateShopLoginListener
{
    public const LOGIN_TEMPLATE = 'shop/private_shop/login.html.twig';

    private const CLOSED_ROUTES = [
        'sylius_shop_register',
        'sylius_shop_register_after_checkout',
        'sylius_shop_register_thank_you',
        'sylius_shop_user_verification',
        'sylius_shop_resend_verification_email',
        'sylius_shop_request_password_reset_token',
        'sylius_shop_password_reset',
        'sylius_shop_request_password_reset_token_redirect',
    ];

    public function __construct(
        #[Autowire(service: 'sylius.context.channel')]
        private readonly ChannelContextInterface $channelContext,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ('sylius_shop_login' !== $route && !\in_array($route, self::CLOSED_ROUTES, true)) {
            return;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return;
        }

        if (!$channel instanceof ImportPrivateShopAwareInterface || null === $channel->getPrivateShopUserEmail()) {
            return;
        }

        if ('sylius_shop_login' !== $route) {
            throw new NotFoundHttpException();
        }

        // Sylius' SecurityController renders the template of the route options.
        $request->attributes->set('_sylius', ['template' => self::LOGIN_TEMPLATE] + $request->attributes->get('_sylius', []));
    }
}
