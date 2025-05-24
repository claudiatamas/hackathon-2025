<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use Exception;

class ExpenseService
{
    
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function list(User $user, ?int $year, ?int $month, int $page, int $pageSize): array
    {
        $offset = ($page - 1) * $pageSize;
        $expenses = $this->expenses->list($user, $year, $month, $offset, $pageSize);
        $total = $this->expenses->count($user, $year, $month);

        return [
            'data' => $expenses,
            'total' => $total,
            'totalPages' => (int)ceil($total / $pageSize),
        ];
    }

    public function listExpenditureYears(User $user): array
    {
        return $this->expenses->listExpenditureYears($user);
    }
    public function create(
        User $user,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        $this->validateExpenseData($amount, $description, $category);
        
        $amountCents = (int) round($amount * 100);
        
        $expense = new Expense(null, $user->id, $date, $category, $amountCents, $description);
        $this->expenses->save($expense);
    }

   public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        $this->validateExpenseData($amount, $description, $category);
        
        $amountCents = (int) round($amount * 100);
        
        $expense->amountCents = $amountCents;
        $expense->description = $description;
        $expense->date = $date;
        $expense->category = $category;
        
        $this->expenses->save($expense);
    }

 public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
{
    if ($csvFile->getError() !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }

    $stream = $csvFile->getStream();
    $content = $stream->getContents();
    $lines = explode("\n", $content);

    $importedCount = 0;

    $this->expenses->beginTransaction();

    try {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $data = str_getcsv($line);

            if (count($data) < 4) {
                continue;
            }

            try {
                $date = new DateTimeImmutable($data[0]);
            } catch (Exception $e) {
               
                continue;
            }

            $amount = (float) $data[1];
            $description = trim($data[2]);
            $category = trim($data[3]);

            try {
                $this->validateExpenseData($amount, $description, $category);
                $this->create($user, $amount, $description, $date, $category);
                $importedCount++;
            } catch (Exception $e) {
             
                continue;
            }
        }

        $this->expenses->commit();
        return $importedCount;

    } catch (Exception $e) {
        $this->expenses->rollback();
        throw $e;
    }
}

    private function validateExpenseData(float $amount, string $description, string $category): void
    {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        
        if (empty(trim($description))) {
            throw new Exception('Description is required');
        }
        
        if (empty(trim($category))) {
            throw new Exception('Category is required');
        }
        
        if (strlen($description) > 255) {
            throw new Exception('Description is too long');
        }
    }
}
