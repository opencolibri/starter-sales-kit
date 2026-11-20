<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit;

use OpenColibri\Starter\Core\Util\Log;
use OpenColibri\Starter\Core\Workspace;
use Symfony\Component\Dotenv\Dotenv;

use function Castor\io;
use function Castor\run;

/**
 * Copies the directory of a shop (its catalog, images and project.yaml) to the stack of another server, with rsync
 * over SSH, configured by the IMPORT_VAR_PUSH_* variables of the project .env files.
 */
final class VarPush
{
    /**
     * @return array{host: string, user: string, port: int, identityFile: ?string, remoteDir: string}
     */
    public static function config(): array
    {
        self::loadEnv();
        $host = self::env('IMPORT_VAR_PUSH_SSH_HOST') ?? throw new \RuntimeException('IMPORT_VAR_PUSH_SSH_HOST is not set. Add it to .env.local at the project root.');
        $remoteDir = self::env('IMPORT_VAR_PUSH_REMOTE_DIR') ?? throw new \RuntimeException('IMPORT_VAR_PUSH_REMOTE_DIR is not set (remote project root, e.g. /home/deploy/htdocs/sales-kit/prod/current).');
        $port = (int) (self::env('IMPORT_VAR_PUSH_SSH_PORT') ?? '22');
        $identityFile = self::env('IMPORT_VAR_PUSH_IDENTITY_FILE');

        if ($port < 1 || $port > 65535) {
            throw new \RuntimeException(\sprintf('Invalid IMPORT_VAR_PUSH_SSH_PORT "%d".', $port));
        }

        if (null !== $identityFile && str_starts_with($identityFile, '~/') && \is_string($home = getenv('HOME')) && '' !== $home) {
            $identityFile = $home . substr($identityFile, 1);
        }

        return [
            'host' => $host,
            'user' => self::env('IMPORT_VAR_PUSH_SSH_USER') ?? 'debian',
            'port' => $port,
            'identityFile' => $identityFile,
            'remoteDir' => rtrim($remoteDir, '/\\'),
        ];
    }

    public static function remoteDir(string $shop, string $remoteProjectRoot): string
    {
        return rtrim($remoteProjectRoot, '/\\') . '/.castor/import/var/' . $shop;
    }

    /**
     * @param array{host: string, user: string, port: int, identityFile: ?string, remoteDir: string} $config
     *
     * @return list<string>
     */
    public static function sshArguments(array $config): array
    {
        $arguments = ['-p', (string) $config['port'], '-o', 'BatchMode=yes'];

        if (null !== $config['identityFile'] && '' !== $config['identityFile']) {
            array_push($arguments, '-i', $config['identityFile']);
        }

        return $arguments;
    }

    /**
     * @param array{host: string, user: string, port: int, identityFile: ?string, remoteDir: string} $config
     */
    public static function sshCommand(array $config): string
    {
        return implode(' ', ['ssh', ...array_map(escapeshellarg(...), self::sshArguments($config))]);
    }

    public static function push(string $shop, bool $dryRun = false, bool $delete = false): void
    {
        $localDir = Shops::dir(Shops::get($shop));
        $config = self::config();
        $remoteDir = self::remoteDir($shop, $config['remoteDir']);
        $destination = \sprintf('%s@%s:%s/', $config['user'], $config['host'], $remoteDir);
        $mkdir = ['ssh', ...self::sshArguments($config), \sprintf('%s@%s', $config['user'], $config['host']), 'mkdir', '-p', $remoteDir];

        Log::comment(\sprintf('Pushing shop %s: %s/ → %s', $shop, $localDir, $destination));

        if ($dryRun) {
            Log::comment('Dry run: would run: ' . implode(' ', array_map(escapeshellarg(...), $mkdir)));
        } else {
            run($mkdir);
        }

        run(['rsync', '-avz', ...($dryRun ? ['-n'] : []), ...($delete ? ['--delete'] : []), '-e', self::sshCommand($config), $localDir . '/', $destination]);

        if ($dryRun) {
            io()->comment('Dry run completed (no files transferred).');
        } else {
            io()->success(\sprintf('Shop "%s" pushed to %s.', $shop, $remoteDir));
        }
    }

    private static function loadEnv(): void
    {
        $root = Workspace::root();
        $dotenv = new Dotenv();

        foreach (['.env', '.env.local'] as $file) {
            if (is_file($root . '/' . $file)) {
                $dotenv->overload($root . '/' . $file);
            }
        }
    }

    private static function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
