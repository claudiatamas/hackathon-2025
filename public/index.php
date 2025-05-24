<?php

declare(strict_types=1);

// initializing class autoloader
require __DIR__.'/../vendor/autoload.php';

session_start();

use App\Kernel;
use Dotenv\Dotenv;

// loading .env file variables
$dotenv = Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

// creating and running web application
$app = Kernel::createApp();
$app->run();
