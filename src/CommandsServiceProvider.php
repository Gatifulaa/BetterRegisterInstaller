<?php

namespace Gatifulaa\BetterRegister;

use Illuminate\Support\ServiceProvider;
use Gatifulaa\BetterRegister\Commands\InstallCommand;

class CommandsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            InstallCommand::class,
        ]);
    }
}