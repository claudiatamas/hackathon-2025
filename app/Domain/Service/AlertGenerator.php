<?php

declare(strict_types=1);

namespace App\Domain\Service;

class AlertGenerator
{
    private array $categoryBudgets = [
        'Groceries' => 300.00,
        'Transport' => 100.00,
        'Utilities' => 250.00,
        'Entertainment' => 150.00,
    ];

    public function generate(array $categoryTotals): array
    {
        $alerts = [];

        foreach ($categoryTotals as $category => $data) {
            if (isset($this->categoryBudgets[$category]) && $data['value'] > $this->categoryBudgets[$category]) {
                $excess = $data['value'] - $this->categoryBudgets[$category];
                $alerts[] = [
                    'category' => $category,
                    'amount' => $excess,
                ];
            }
        }

        return $alerts;
    }

}
