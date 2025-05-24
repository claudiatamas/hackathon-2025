<?php

declare(strict_types=1);

namespace App\Controllers;

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
    // Predefined expense categories
    private const CATEGORIES = [
        'Transport',
        'Utilities',
        'Groceries',
        'Entertainment'
    ];

    /**
     * Constructor to inject dependencies.
     */
    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        UserRepositoryInterface $userRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
    ) {
        parent::__construct($view, $userRepository);
        $this->userRepository = $userRepository;
    }

    /**
     * Display paginated list of expenses for the current user filtered by year and month.
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get filters from query parameters or use defaults
        $queryParams = $request->getQueryParams();
        $year = isset($queryParams['year']) ? (int)$queryParams['year'] : (int)date('Y');
        $month = isset($queryParams['month']) ? (int)$queryParams['month'] : (int)date('m');
        $page = isset($queryParams['page']) && (int)$queryParams['page'] > 0 ? (int)$queryParams['page'] : 1;
        $pageSize = 10;

        // Fetch paginated expense data and available years for filtering
        $result = $this->expenseService->list($user, $year, $month, $page, $pageSize);
        $years = $this->expenseService->listExpenditureYears($user);

        return $this->render($response, 'expenses/index.twig', [
            'expenses'   => $result['data'],
            'total'      => $result['total'],
            'year'       => $year,
            'month'      => $month,
            'page'       => $page,
            'pageSize'   => $pageSize,
            'totalPages' => $result['totalPages'],
            'years'      => $years,
        ]);
    }

    
     // Show the form to create a new expense.
    public function create(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        return $this->render($response, 'expenses/create.twig', [
            'categories' => self::CATEGORIES,
            'errors'     => [],
            'old'        => [],
        ]);
    }

    /**
     * Handle storing a new expense submitted via form.
     */
    public function store(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $errors = [];

        try {
            // Validate and parse input data
            $amount = (float) ($data['amount'] ?? 0);
            $description = trim($data['description'] ?? '');
            $category = trim($data['category'] ?? '');
            $dateString = trim($data['date'] ?? '');

            $date = empty($dateString) ? new DateTimeImmutable() : new DateTimeImmutable($dateString);

            // Call service to create the expense
            $this->expenseService->create($user, $amount, $description, $date, $category);

            // Redirect to expenses list on success
            return $response->withHeader('Location', '/expenses')->withStatus(302);

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $this->render($response, 'expenses/create.twig', [
            'categories' => self::CATEGORIES,
            'errors'     => $errors,
            'old'        => $data,
        ]);
    }


    // Show the form to edit an existing expense.
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

        // Ensure expense belongs to current user
        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        return $this->render($response, 'expenses/edit.twig', [
            'expense'    => $expense,
            'categories' => self::CATEGORIES,
            'errors'     => [],
            'old'        => [],
        ]);
    }

    // Update Expense
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $id = (int) $args['id'];
        $expense = $this->expenseRepository->find($id);

        if (!$expense) {
            return $response->withStatus(404);
        }

        // Ensure expense belongs to current user
        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();

        $expense->date = new DateTimeImmutable($data['date']);
        $expense->category = $data['category'];
        $expense->amountCents = (int) round(floatval($data['amount']) * 100);
        $expense->description = $data['description'] ?? '';

        $this->expenseRepository->save($expense);

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    
    //  Delete an expense by its ID.
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

        // Authorization: ensure expense belongs to current user
        if ($expense->userId !== $user->id) {
            return $response->withStatus(403);
        }

        $this->expenseRepository->delete($expenseId);

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

   
     // Handle CSV file import of expenses.
    public function import(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['csv'])) {
            // Redirect with error if CSV not uploaded
            return $response->withHeader('Location', '/expenses?error=upload_failed')->withStatus(302);
        }

        $csvFile = $uploadedFiles['csv'];

        try {
            // Import expenses from CSV and get count of imported records
            $importedCount = $this->expenseService->importFromCsv($user, $csvFile);

            return $response->withHeader('Location', '/expenses?success=imported_' . $importedCount)->withStatus(302);

        } catch (Exception $e) {
            return $response->withHeader('Location', '/expenses?error=import_failed')->withStatus(302);
        }
    }
}