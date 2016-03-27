#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

(new Dotenv\Dotenv(__DIR__))->load();

$application = new Application();
$application->add(new Command\TestCommand());
$application->add(new Command\EC2HostsCommand());
$application->run();
