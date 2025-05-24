<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

abstract class BaseController
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(
        protected Twig $view,
        UserRepositoryInterface $userRepository,
    ) {
        $this->userRepository = $userRepository;
    }

    // Retrieve currently logged-in user
    protected function getCurrentUser(): ?User
    {
        if (isset($_SESSION['user_id'])) {
            return $this->userRepository->find((int) $_SESSION['user_id']);
        }

        return null;
    }

    // Render a Twig template with optional data and current user info
    protected function render(Response $response, string $template, array $data = []): Response
    {
        $data['currentUserId'] = $_SESSION['user_id'] ?? null;
        $data['currentUserName'] = $_SESSION['username'] ?? null;

        return $this->view->render($response, $template, $data);
    }
}
