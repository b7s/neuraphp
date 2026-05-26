<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Console;

use B7s\Neuraphp\Console\Commands\DoctorCommand;
use B7s\Neuraphp\Console\Commands\InfoCommand;
use B7s\Neuraphp\Console\Commands\InstallCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    private const NAME = 'neuraphp';

    private const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
    }

    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new DoctorCommand;
        $commands[] = new InfoCommand;
        $commands[] = new InstallCommand;

        return $commands;
    }
}
