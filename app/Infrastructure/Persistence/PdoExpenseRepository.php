<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch(PDO::FETCH_ASSOC);
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    /**
     * @throws Exception
     */
    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description) 
                      VALUES (:user_id, :date, :category, :amount_cents, :description)';
            
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ]);
            
            $expense->id = (int) $this->pdo->lastInsertId();
        } else {
            $query = 'UPDATE expenses 
                      SET user_id = :user_id, date = :date, category = :category, 
                          amount_cents = :amount_cents, description = :description
                      WHERE id = :id';
            
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'id' => $expense->id,
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        $conditions = [];
        $params = [];
        
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }
        
        if (isset($criteria['year'])) {
            $conditions[] = 'strftime("%Y", date) = :year';
            $params['year'] = (string) $criteria['year'];
        }
        
        if (isset($criteria['month'])) {
            $conditions[] = 'strftime("%m", date) = :month';
            $params['month'] = sprintf('%02d', $criteria['month']);
        }
        
        if (isset($criteria['category'])) {
            $conditions[] = 'category = :category';
            $params['category'] = $criteria['category'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $query = "SELECT * FROM expenses {$whereClause} ORDER BY date DESC, id DESC LIMIT :limit OFFSET :offset";
        $statement = $this->pdo->prepare($query);
        
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $from, PDO::PARAM_INT);
        
        $statement->execute();
        
        $expenses = [];
        while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
            $expenses[] = $this->createExpenseFromData($data);
        }
        
        return $expenses;
    }

    public function countBy(array $criteria): int
    {
        $conditions = [];
        $params = [];
        
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }
        
        if (isset($criteria['year'])) {
            $conditions[] = 'strftime("%Y", date) = :year';
            $params['year'] = (string) $criteria['year'];
        }
        
        if (isset($criteria['month'])) {
            $conditions[] = 'strftime("%m", date) = :month';
            $params['month'] = sprintf('%02d', $criteria['month']);
        }
        
        if (isset($criteria['category'])) {
            $conditions[] = 'category = :category';
            $params['category'] = $criteria['category'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $query = "SELECT COUNT(*) FROM expenses {$whereClause}";
        
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        
        return (int) $statement->fetchColumn();
    }

    public function list(User $user, ?int $year, ?int $month, int $offset, int $limit): array
    {
        $params = [':user_id' => $user->id];
        $where = 'WHERE user_id = :user_id';

        if ($year !== null) {
            $where .= ' AND strftime("%Y", date) = :year';
            $params[':year'] = (string) $year;
        }

        if ($month !== null) {
            $where .= ' AND strftime("%m", date) = :month';
            $params[':month'] = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        }

        $sql = "SELECT * FROM expenses $where ORDER BY date DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listExpenditureYears(User $user): array
    {
        $query = 'SELECT DISTINCT strftime("%Y", date) as year FROM expenses WHERE user_id = :user_id ORDER BY year DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id' => $user->id]);
        
        $years = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $years[] = (int) $row['year'];
        }
        
        return $years;
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $conditions = [];
        $params = [];
        
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }
        
        if (isset($criteria['year'])) {
            $conditions[] = 'strftime("%Y", date) = :year';
            $params['year'] = (string) $criteria['year'];
        }
        
        if (isset($criteria['month'])) {
            $conditions[] = 'strftime("%m", date) = :month';
            $params['month'] = sprintf('%02d', $criteria['month']);
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $query = "SELECT category, SUM(amount_cents) as total_cents FROM expenses {$whereClause} GROUP BY category ORDER BY total_cents DESC";
        
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        
        $results = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['category']] = (float) ($row['total_cents'] / 100);
        }
        
        return $results;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $conditions = [];
        $params = [];
        
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }
        
        if (isset($criteria['year'])) {
            $conditions[] = 'strftime("%Y", date) = :year';
            $params['year'] = (string) $criteria['year'];
        }
        
        if (isset($criteria['month'])) {
            $conditions[] = 'strftime("%m", date) = :month';
            $params['month'] = sprintf('%02d', $criteria['month']);
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $query = "SELECT category, AVG(amount_cents) as avg_cents FROM expenses {$whereClause} GROUP BY category ORDER BY avg_cents DESC";
        
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        
        $results = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['category']] = (float) ($row['avg_cents'] / 100);
        }
        
        return $results;
    }

    public function sumAmounts(array $criteria): float
    {
        $conditions = [];
        $params = [];
        
        if (isset($criteria['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }
        
        if (isset($criteria['year'])) {
            $conditions[] = 'strftime("%Y", date) = :year';
            $params['year'] = (string) $criteria['year'];
        }
        
        if (isset($criteria['month'])) {
            $conditions[] = 'strftime("%m", date) = :month';
            $params['month'] = sprintf('%02d', $criteria['month']);
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $query = "SELECT SUM(amount_cents) as total_cents FROM expenses {$whereClause}";
        
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        
        $total = $statement->fetchColumn();
        return $total !== false ? (float) ($total / 100) : 0.0;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
    public function count(User $user, ?int $year, ?int $month): int
{
    $params = ['user_id' => $user->id];
    $conditions = ['user_id = :user_id'];

    if ($year !== null) {
        $conditions[] = 'strftime("%Y", date) = :year';
        $params['year'] = (string) $year;
    }

    if ($month !== null) {
        $conditions[] = 'strftime("%m", date) = :month';
        $params['month'] = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $sql = "SELECT COUNT(*) FROM expenses $whereClause";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

    /**
     * Helper to create Expense entity from DB data
     * 
     * @throws Exception
     */
    private function createExpenseFromData(array $data): Expense
    {
        return new Expense(
            id: (int) $data['id'],
            userId: (int) $data['user_id'],
            date: new DateTimeImmutable($data['date']),
            category: $data['category'],
            amountCents: (int) $data['amount_cents'],
            description: $data['description'] ?? ''
        );
    }
}
