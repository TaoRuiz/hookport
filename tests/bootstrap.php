<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

function runTestConsoleCommand(string $command): void
{
    passthru(sprintf('php "%s/bin/console" --env=test --quiet %s', dirname(__DIR__), $command), $exitCode);

    if (0 !== $exitCode) {
        throw new RuntimeException(sprintf('Test database setup failed on "%s".', $command));
    }
}

runTestConsoleCommand('doctrine:database:drop --force --if-exists');
runTestConsoleCommand('doctrine:database:create');
runTestConsoleCommand('doctrine:migrations:migrate --no-interaction');
