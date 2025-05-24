<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $username, string $password): User
    {
        if (strlen($username) < 4) {
            throw new \InvalidArgumentException('Username must be at least 4 characters.');
        }

        if (strlen($password) < 8 || !preg_match('/\d/', $password)) {
            throw new \InvalidArgumentException('Password must be at least 8 characters and contain at least one number.');
        }

        $existingUser = $this->users->findByUsername($username);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException('Username already taken.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(null, $username, $passwordHash, new \DateTimeImmutable());
        $this->users->save($user);

        return $user;
    }


    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return false;
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        return true;
    }
}
