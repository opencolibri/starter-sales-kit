<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Castor;

/**
 * Human-readable names for the castor-api runs shown in the admin.
 */
final class RunDescription
{
    private const TASK_LABELS = [
        'sylius:import:existing:fetch' => 'Fetch site',
        'sylius:import:ai:build' => 'Build AI catalog',
        'sylius:import:fixtures:generate' => 'Generate fixtures',
        'sylius:import:fixtures:load' => 'Load into Sylius',
        'sylius:import:delete' => 'Delete shop',
        'sylius:import:dataset:build' => 'Build dataset',
        'sylius:import:dataset:images' => 'Store dataset images',
    ];

    public static function label(string $task): string
    {
        return self::TASK_LABELS[$task] ?? $task;
    }

    /**
     * What the run works on: the shop, the theme or the dataset.
     *
     * @param array<string, mixed> $payload
     */
    public static function subject(array $payload): string
    {
        foreach (['project', 'theme', 'dataset', 'name'] as $key) {
            if (\is_scalar($payload[$key] ?? null) && '' !== trim((string) $payload[$key])) {
                return trim((string) $payload[$key]);
            }
        }

        return '';
    }
}
