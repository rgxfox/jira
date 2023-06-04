<?php

namespace Rgxfox\Jira;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/jira.php', 'foxjira');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
