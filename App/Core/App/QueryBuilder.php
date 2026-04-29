<?php

namespace Wingman\Core\App;

use mysqli;

class QueryBuilder
{
    private string  $table;
    private array   $selects  = [];
    private array   $wheres   = [];
    private array   $orWheres = [];
    private array   $joins    = [];
    private array   $groupBys = [];
    private array   $havings  = [];
    private array   $orderBys = [];
    private ?int    $limitVal  = null;
    private ?int    $offsetVal = null;
    private array   $bindings  = [];
    private array   $bindTypes = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function select(string ...$columns): self
    {
        $this->selects = $columns;
        return $this;
    }


    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);
        $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function crossJoin(string $table): self
    {
        $this->joins[] = "CROSS JOIN {$table}";
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[]   = "{$column} {$operator} ?";
        $this->bindings[] = $value;
        $this->bindTypes[] = $this->resolveType($value);
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $this->orWheres[]  = "{$column} {$operator} ?";
        $this->bindings[]  = $value;
        $this->bindTypes[] = $this->resolveType($value);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        foreach ($values as $value) {
            $this->bindings[]  = $value;
            $this->bindTypes[] = $this->resolveType($value);
        }
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} NOT IN ({$placeholders})";
        foreach ($values as $value) {
            $this->bindings[]  = $value;
            $this->bindTypes[] = $this->resolveType($value);
        }
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[]    = "{$column} BETWEEN ? AND ?";
        $this->bindings[]  = $min;
        $this->bindTypes[] = $this->resolveType($min);
        $this->bindings[]  = $max;
        $this->bindTypes[] = $this->resolveType($max);
        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    public function groupBy(string ...$columns): self
    {
        $this->groupBys = $columns;
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[]   = "{$column} {$operator} ?";
        $this->bindings[]  = $value;
        $this->bindTypes[] = $this->resolveType($value);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        $this->orderBys[] = "{$column} {$direction}";
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): self
    {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetVal = $offset;
        return $this;
    }

    public function paginate(int $page, int $perPage = 10): self
    {
        $this->limitVal  = $perPage;
        $this->offsetVal = ($page - 1) * $perPage;
        return $this;
    }

    public function toSQL(): string
    {
        $select  = empty($this->selects)
            ? 'SELECT *'
            : 'SELECT ' . implode(', ', $this->selects);

        $from    = "FROM {$this->table}";

        $joins   = empty($this->joins)
            ? ''
            : implode(' ', $this->joins);

        $where   = $this->buildWhereClause();

        $groupBy = empty($this->groupBys)
            ? ''
            : 'GROUP BY ' . implode(', ', $this->groupBys);

        $having  = empty($this->havings)
            ? ''
            : 'HAVING ' . implode(' AND ', $this->havings);

        $orderBy = empty($this->orderBys)
            ? ''
            : 'ORDER BY ' . implode(', ', $this->orderBys);

        $limit   = $this->limitVal  !== null ? "LIMIT {$this->limitVal}"   : '';
        $offset  = $this->offsetVal !== null ? "OFFSET {$this->offsetVal}" : '';

        return trim(implode(' ', array_filter([
            $select,
            $from,
            $joins,
            $where,
            $groupBy,
            $having,
            $orderBy,
            $limit,
            $offset,
        ])));
    }

    private function buildWhereClause(): string
    {
        if (empty($this->wheres) && empty($this->orWheres)) return '';

        $parts = [];

        if (!empty($this->wheres))
            $parts[] = implode(' AND ', $this->wheres);

        if (!empty($this->orWheres))
            $parts[] = implode(' OR ', $this->orWheres);

        return 'WHERE ' . implode(' OR ', $parts);
    }

    public function get(mysqli $db): array
    {
        $stmt = $this->prepare($db);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function first(mysqli $db): array|null
    {
        $this->limit(1);
        $stmt = $this->prepare($db);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function count(mysqli $db): int
    {
        $original      = $this->selects;
        $this->selects = ['COUNT(*) as aggregate'];
        $stmt          = $this->prepare($db);
        $stmt->execute();
        $result        = $stmt->get_result()->fetch_assoc();
        $this->selects = $original;
        return (int) ($result['aggregate'] ?? 0);
    }

    public function paginateResult(mysqli $db, int $page, int $perPage = 10): array
    {
        $total     = $this->count($db);
        $data      = $this->paginate($page, $perPage)->get($db);
        $lastPage  = (int) ceil($total / $perPage);

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => ($page - 1) * $perPage + 1,
            'to'           => min($page * $perPage, $total),
        ];
    }

    public function insert(mysqli $db, array $data): int
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql          = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $types  = implode('', array_map(fn($v) => $this->resolveType($v), array_values($data)));
        $stmt   = $db->prepare($sql);
        $stmt->bind_param($types, ...array_values($data));
        $stmt->execute();

        return $db->insert_id;
    }

    public function insertMany(mysqli $db, array $rows): int
    {
        if (empty($rows)) return 0;

        $columns      = implode(', ', array_keys($rows[0]));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($rows[0]), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($rows), $rowPlaceholder));
        $sql          = "INSERT INTO {$this->table} ({$columns}) VALUES {$placeholders}";

        $bindings = [];
        $types    = '';

        foreach ($rows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
                $types     .= $this->resolveType($value);
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$bindings);
        $stmt->execute();

        return $stmt->affected_rows; // returns number of inserted rows
    }

    public function update(mysqli $db, array $data): int
    {
        $sets     = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $where    = $this->buildWhereClause();
        $sql      = trim("UPDATE {$this->table} SET {$sets} {$where}");

        // update bindings = data values first, then where bindings
        $dataTypes    = implode('', array_map(fn($v) => $this->resolveType($v), array_values($data)));
        $whereTypes   = implode('', $this->bindTypes);
        $types        = $dataTypes . $whereTypes;
        $bindings     = array_merge(array_values($data), $this->bindings);

        $stmt = $db->prepare($sql);

        if (!empty($bindings))
            $stmt->bind_param($types, ...$bindings);

        $stmt->execute();

        return $stmt->affected_rows; // returns number of updated rows
    }


    public function delete(mysqli $db): int
    {
        $where = $this->buildWhereClause();
        $sql   = trim("DELETE FROM {$this->table} {$where}");
        $stmt  = $db->prepare($sql);

        if (!empty($this->bindings)) {
            $types = implode('', $this->bindTypes);
            $stmt->bind_param($types, ...$this->bindings);
        }

        $stmt->execute();

        return $stmt->affected_rows; // returns number of deleted rows
    }

    private function prepare(mysqli $db): \mysqli_stmt
    {
        $sql  = $this->toSQL();
        $stmt = $db->prepare($sql);

        if (!empty($this->bindings)) {
            $types = implode('', $this->bindTypes);
            $stmt->bind_param($types, ...$this->bindings);
        }

        return $stmt;
    }

    private function resolveType(mixed $value): string
    {
        return match (true) {
            is_int($value)   => 'i',
            is_float($value) => 'd',
            default          => 's',
        };
    }

    public function reset(): self
    {
        $this->selects  = [];
        $this->wheres   = [];
        $this->orWheres = [];
        $this->joins    = [];
        $this->groupBys = [];
        $this->havings  = [];
        $this->orderBys = [];
        $this->limitVal  = null;
        $this->offsetVal = null;
        $this->bindings  = [];
        $this->bindTypes = [];
        return $this;
    }
}