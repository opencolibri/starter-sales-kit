<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Removes the admins of an imported channel when sylius:import:channel:reset flushes it away
 * (GenericEvent subject: the channel code; arguments: "prefix" and "keep_channel").
 */
#[AsEventListener(event: 'import.channel_reset', method: 'removeImportAdminUsers')]
final class ImportChannelResetListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function removeImportAdminUsers(GenericEvent $event): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('adminUser')
            ->from(AdminUser::class, 'adminUser')
            ->andWhere('adminUser.channelCode = :channelCode')
            ->setParameter('channelCode', (string) $event->getSubject())
        ;

        if ($event->hasArgument('keep_channel') && true === $event->getArgument('keep_channel')) {
            $queryBuilder
                ->andWhere('adminUser.importCodePrefix = :prefix')
                ->setParameter('prefix', (string) $event->getArgument('prefix'))
            ;
        }

        foreach ($queryBuilder->getQuery()->getResult() as $adminUser) {
            $this->entityManager->remove($adminUser);
        }
    }
}
