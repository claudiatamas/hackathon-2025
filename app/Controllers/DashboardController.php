<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Domain\Service\AlertGenerator;
use App\Domain\Service\MonthlySummaryService;
use App\Domain\Repository\UserRepositoryInterface;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private MonthlySummaryService $monthlySummaryService,
        private AlertGenerator $alertGenerator,
        UserRepositoryInterface $userRepository,
    ) {
        parent::__construct($view, $userRepository);
    }

    
    public function index(Request $request, Response $response): Response
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Get year and month from query params or use current
        $queryParams = $request->getQueryParams();
        $year = isset($queryParams['year']) ? (int)$queryParams['year'] : (int)date('Y');
        $month = isset($queryParams['month']) ? (int)$queryParams['month'] : (int)date('n');

        // Fetch available years with expenditure data
        $years = $this->monthlySummaryService->listExpenditureYears($user);

        // Calculate total, per-category totals and averages for selected month/year
        $totalForMonth = $this->monthlySummaryService->computeTotalExpenditure($user, $year, $month);
        $categoryTotals = $this->monthlySummaryService->computePerCategoryTotals($user, $year, $month);
        $averagesForCategories = $this->monthlySummaryService->computePerCategoryAverages($user, $year, $month);

        // Generate alerts only for current month/year
        $alerts = ($year === (int)date('Y') && $month === (int)date('n'))
            ? $this->alertGenerator->generate($categoryTotals)
            : [];

        return $this->render($response, 'dashboard.twig', [
            'year'                  => $year,
            'month'                 => $month,
            'years'                 => $years,
            'alerts'                => $alerts,
            'totalExpenditure'      => $totalForMonth,
            'totalsForCategories'   => $categoryTotals,
            'averagesForCategories' => $averagesForCategories,
        ]);
    }

}
