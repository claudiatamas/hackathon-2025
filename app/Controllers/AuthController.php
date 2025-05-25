<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use App\Domain\Repository\UserRepositoryInterface;

class AuthController extends BaseController
{
    // Inject dependencies: view, user repo, auth service, logger
    public function __construct(
        Twig $view,
        UserRepositoryInterface $userRepository,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view, $userRepository);
    }


    public function showRegister(Request $request, Response $response): Response
    {
        $this->logger->info('Register page requested');
        return $this->render($response, 'auth/register.twig');
    }

    // Registration form submission
    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $confirmPassword = trim($data['confirm_password'] ?? '');

        $errors = [];

        // Validation
        if (strlen($username) < 4) {
            $errors['username'] = 'Username must be at least 4 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        // If validation errors, show form with errors
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
            $errors['general'] = 'An unexpected error occurred.';
            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'username' => $username,
                'password' => $password,
                'confirm_password' => $confirmPassword,
            ]);
        }
    }

    // Display login page
    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    // Login form submission
    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        $errors = [];

        // Validate inputs
        if ($username === '') {
            $errors['username'] = 'Please enter your username.';
        }

        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }

        // Show form again if validation fails
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

    // Log out user and destroy session
    public function logout(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }


        $_SESSION = [];


        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

}
