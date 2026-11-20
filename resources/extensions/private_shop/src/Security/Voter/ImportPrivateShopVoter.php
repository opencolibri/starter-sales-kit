<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Channel\ImportPrivateShopAwareInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Keeps a private demo shop for its demo customer: on the storefront and the shop API of a private channel, only
 * the shop user it names gets in (access_control rules of config/packages/security.yaml). Public channels are open.
 *
 * @extends Voter<string, mixed>
 */
final class ImportPrivateShopVoter extends Voter
{
    public const ACCESS = 'import_private_shop_access';

    public function __construct(
        #[Autowire(service: 'sylius.context.channel')]
        private readonly ChannelContextInterface $channelContext,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return true;
        }

        $email = $channel instanceof ImportPrivateShopAwareInterface ? $channel->getPrivateShopUserEmail() : null;

        if (null === $email) {
            return true;
        }

        $user = $token->getUser();

        return $user instanceof ShopUserInterface
            && 0 === strcasecmp((string) $user->getEmail(), $email);
    }
}
