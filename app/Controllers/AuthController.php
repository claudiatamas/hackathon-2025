<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        $this->logger->info('Register page requested');
        return $this->render($response, 'auth/register.twig');
    }

  public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $confirmPassword = trim($data['confirm_password'] ?? '');

        $errors = [];

        // Validate
        if (strlen($username) < 4) {
            $errors['username'] = 'Username must be at least 4 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'username' => $username,
                'password' => $password,
                'confirm_password' => $confirmPassword,
            ]);
        }

        try {
            $this->authService->register($username, $password);
            return $response->withHeader('Location', '/login')->withStatus(302);

        } catch (\InvalidArgumentException $e) {
            $errors['username'] = $e->getMessage();

            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'username' => $username,
                'password' => $password,
                'confirm_password' => $confirmPassword,
            ]);
        } catch (\Exception $e) {
            $errors['general'] = 'An unexpected error occurred. Please try again later.';

            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'username' => $username,
                'password' => $password,
                'confirm_password' => $confirmPassword,
            ]);
        }
    }



   public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        $errors = [];

        if ($username === '') {
            $errors['username'] = 'Please enter your username.';
        }

        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }

        if (!empty($errors)) {
            return $this->render($response, 'auth/login.twig', [
                'errors' => $errors,
                'username' => $username,
            ]);
        }

        $success = $this->authService->attempt($username, $password);

        if ($success) {
            return $response->withHeader('Location', '/')->withStatus(302);
        } else {
            $errors['general'] = 'Invalid username or password.';
            return $this->render($response, 'auth/login.twig', [
                'errors' => $errors,
                'username' => $username,
            ]);
        }
    }

  public function logout(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        // Delete cookies from session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();

        // Redirect to login
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

}
