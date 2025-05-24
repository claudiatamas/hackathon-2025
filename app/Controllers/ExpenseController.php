<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Service\ExpenseService;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;
    
    private const CATEGORIES = [
        'Transport',
        'Utilities', 
        'Groceries',
        'Entertainment'
    ];

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
    ) {
        parent::__construct($view);
    }
public function index(Request $request, Response $response): Response
{
    $user = $this->getCurrentUser();
    if (!$user) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    $queryParams = $request->getQueryParams();

    $year = isset($queryParams['year']) ? (int)$queryParams['year'] : (int)date('Y');
    $month = isset($queryParams['month']) ? (int)$queryParams['month'] : (int)date('m');

    $page = isset($queryParams['page']) && (int)$queryParams['page'] > 0 ? (int)$queryParams['page'] : 1;

    $pageSize = 20;

    $result = $this->expenseService->list($user, $year, $month, $page, $pageSize);
    $years = $this->expenseService->listExpenditureYears($user);

    return $this->render($response, 'expenses/index.twig', [
        'expenses' => $result['data'],
        'total' => $result['total'],
        'year' => $year,
        'month' => $month,
        'page' => $page,
        'pageSize' => $pageSize,
        'totalPages' => $result['totalPages'],
        'years' => $years,
    ]);
}




    public function create(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        return $this->render($response, 'expenses/create.twig', [
            'categories' => self::CATEGORIES,
            'errors' => [],
            'old' => []
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $errors = [];
        
        try {
            $amount = (float) ($data['amount'] ?? 0);
            $description = trim($data['description'] ?? '');
            $category = trim($data['category'] ?? '');
            $dateString = trim($data['date'] ?? '');
            
            if (empty($dateString)) {
                $date = new DateTimeImmutable();
            } else {
                $date = new DateTimeImmutable($dateString);
            }
            
            $this->expenseService->create($user, $amount, $description, $date, $category);
            
            return $response->withHeader('Location', '/expenses')->withStatus(302);
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $this->render($response, 'expenses/create.twig', [
            'categories' => self::CATEGORIES,
            'errors' => $errors,
            'old' => $data
        ]);
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int) $routeParams['id'];
        $expense = $this->expenseRepository->find($expenseId);
        
        if (!$expense) {
            return $response->withStatus(404);
        }
        
        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => self::CATEGORIES,
            'errors' => [],
            'old' => []
        ]);
    }

    public function update($request, $response, $args)
        {
            $id = (int)$args['id'];
            $expense = $this->expenseRepository->find($id);

            if (!$expense) {

                return $response->withStatus(404);
            }

            $data = $request->getParsedBody();

            $expense->date = new DateTimeImmutable($data['date']);
            $expense->category = $data['category'];
            $expense->amountCents = (int)round(floatval($data['amount']) * 100);
            $expense->description = $data['description'] ?? '';

            $this->expenseRepository->save($expense);

            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }


    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $expenseId = (int) $routeParams['id'];
        $expense = $this->expenseRepository->find($expenseId);
        
        if (!$expense) {
            return $response->withStatus(404);
        }
        
        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        $this->expenseRepository->delete($expenseId);
        
        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    public function import(Request $request, Response $response): Response
        {
            $user = $this->getCurrentUser(); 
            $uploadedFiles = $request->getUploadedFiles();

            if (!isset($uploadedFiles['csv'])) {
                return $response->withHeader('Location', '/expenses?error=upload_failed')->withStatus(302);
            }

            $csvFile = $uploadedFiles['csv'];

            try {
                $importedCount = $this->expenseService->importFromCsv($user, $csvFile);

                return $response->withHeader('Location', '/expenses?success=imported_' . $importedCount)->withStatus(302);

            } catch (Exception $e) {
                return $response->withHeader('Location', '/expenses?error=import_failed')->withStatus(302);
            }
        }

    private function getCurrentUser(): ?User
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_id'])) {
            return $this->userRepository->find((int) $_SESSION['user_id']);
        }

        return null;
    }
}