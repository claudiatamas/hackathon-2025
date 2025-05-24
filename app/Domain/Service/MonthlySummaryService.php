<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(User $user, int $year, int $month): float
    {
      $criteria = [
            'user_id' => $user->getId(),
            'year' => $year,
            'month' => $month,
        ];


        return $this->expenses->sumAmounts($criteria);
    }

 
    public function computePerCategoryTotals(User $user, int $year, int $month): array
    {
       $criteria = [
            'user_id' => $user->getId(),
            'year' => $year,
            'month' => $month,
        ];


        $rawTotals = $this->expenses->sumAmountsByCategory($criteria);

        $totals = [];
        foreach ($rawTotals as $category => $value) {
            $totals[$category] = ['value' => (float)$value];
        }

        return $totals;
    }


    public function computePerCategoryAverages(User $user, int $year, int $month): array
    {
       

       $criteria = [
            'user_id' => $user->getId(),
            'year' => $year,
            'month' => $month,
        ];


        return $this->expenses->averageAmountsByCategory($criteria);
    }

    
    public function listExpenditureYears(User $user): array
        {
            return $this->expenses->listExpenditureYears($user);
        }
    }