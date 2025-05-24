<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class BaseController
{
    public function __construct(
        protected Twig $view,
    ) {}

    protected function render(Response $response, string $template, array $data = []): Response
        {
            $currentUserId = $_SESSION['user_id'] ?? null;
            $currentUserName = null;

            if ($currentUserId !== null) {
              
                $currentUserName = $_SESSION['username'] ?? null;
            }
       
            $data['currentUserId'] = $currentUserId;
            $data['currentUserName'] = $currentUserName;

            return $this->view->render($response, $template, $data);
        }
}
