<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Channel\ImportPrivateShopAwareInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'sylius:import:channel:private',
    description: 'Make one imported shop channel private (only its demo customer sees the storefront) or public again',
)]
final class ImportPrivateShopCommand extends Command
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'Channel code (e.g. COCORICO)')
            ->addOption('shop-user-email', null, InputOption::VALUE_REQUIRED, 'The only shop user allowed on the storefront (omit to make the shop public)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = (string) $input->getArgument('code');
        $channel = $this->channelRepository->findOneByCode($code);

        if (!$channel instanceof ImportPrivateShopAwareInterface) {
            // Not loaded yet: the import_private_shop fixture sets it when the fixtures load.
            $io->note(\sprintf('Channel "%s" not found yet: its visibility is set when the fixtures load.', $code));

            return Command::SUCCESS;
        }

        $email = $input->getOption('shop-user-email');
        $channel->setPrivateShopUserEmail(\is_string($email) ? $email : null);

        // Channels requiring account verification refuse the login of an unverified demo customer.
        $shopUser = \is_string($email) ? $this->shopUserRepository->findOneByEmail($email) : null;

        if ($shopUser instanceof ShopUserInterface && !$shopUser->isVerified()) {
            $shopUser->setVerifiedAt(new \DateTime());
        }

        $this->channelManager->flush();

        $io->success(null === $channel->getPrivateShopUserEmail()
            ? \sprintf('Channel "%s" is public.', $code)
            : \sprintf('Channel "%s" is private to %s.', $code, $channel->getPrivateShopUserEmail()));

        return Command::SUCCESS;
    }
}
