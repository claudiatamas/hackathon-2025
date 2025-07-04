<?php

declare(strict_types=1);

namespace App;

use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\PdoExpenseRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PDO;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use function DI\autowire;
use function DI\factory;

class Kernel
{
    public static function createApp(): App
    {
        // Configure the DI container builder and build the DI container
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);  // Enable autowiring explicitly

        $builder->addDefinitions([
            // Define a factory for the Monolog logger with a stream handler that writes to var/app.log
            LoggerInterface::class            => function () {
                $logger = new Logger('app');
                $logger->pushHandler(new StreamHandler(__DIR__.'/../var/app.log', Level::Debug));

                return $logger;
            },

            // Define a factory for Twig view renderer
            Twig::class                       => function () {
                return Twig::create(__DIR__.'/../templates', ['cache' => false]);
            },

            // Define a factory for PDO database connection
            PDO::class                        => factory(function () {
                static $pdo = null;
                if ($pdo === null) {
                    $pdo = new PDO('sqlite:' . __DIR__ . '/../database/db.sqlite');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                }

                return $pdo;
            }),

            // Map interfaces to concrete implementations
            UserRepositoryInterface::class    => autowire(PdoUserRepository::class),
            ExpenseRepositoryInterface::class => autowire(PdoExpenseRepository::class),
        ]);
        $container = $builder->build();

        // Create an application instance and configure
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->add(TwigMiddleware::createFromContainer($app, Twig::class));
        (require __DIR__.'/../config/settings.php')($app);
        (require __DIR__.'/../config/routes.php')($app);


        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $loggedInUserId = $_SESSION['user_id'] ?? null;

        $twig = $container->get(Twig::class);
        $twig->getEnvironment()->addGlobal('currentUserId', $loggedInUserId);
        return $app;
    }
}
