<?php

namespace Wingman\Core\Bases;

use mysqli;
use Wingman\Core\App\QueryBuilder;

class QueryableModel extends BaseModel
{
    private QueryBuilder $query;

    public function __construct(mysqli $db)
    {
        parent::__construct($db);
        $this->query = new QueryBuilder($this->table);
    }

    private function fresh(): QueryBuilder
    {
        $this->query->reset();
        return $this->query;
    }

    public function select(string ...$columns): static
    {
        $this->query->select(...$columns);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): static
    {
        $this->query->join($table, $first, $operator, $second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->query->leftJoin($table, $first, $operator, $second);
        return $this;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->query->rightJoin($table, $first, $operator, $second);
        return $this;
    }

    public function crossJoin(string $table): static
    {
        $this->query->crossJoin($table);
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): static
    {
        $this->query->orWhere($column, $operator, $value);
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->query->whereNull($column);
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->query->whereNotNull($column);
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->query->whereNotIn($column, $values);
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->query->whereBetween($column, $min, $max);
        return $this;
    }

    public function whereLike(string $column, string $pattern): static
    {
        $this->query->whereLike($column, $pattern);
        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->query->groupBy(...$columns);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $this->query->having($column, $operator, $value);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        $this->query->orderByDesc($column);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->query->limit($limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->query->offset($offset);
        return $this;
    }

    public function paginate(int $page, int $perPage = 10): static
    {
        $this->query->paginate($page, $perPage);
        return $this;
    }

    public function all(): array
    {
        $result = $this->fresh()->get($this->db);
        $this->fresh();
        return $result;
    }

    public function get(): array
    {
        $result = $this->query->get($this->db);
        $this->fresh();
        return $result;
    }

    public function first(): array|null
    {
        $result = $this->query->first($this->db);
        $this->fresh();
        return $result;
    }

    public function find(int $id, string $primaryKey = 'id'): array|null
    {
        $this->fresh();
        return $this->where($primaryKey, '=', $id)->first();
    }

    public function count(): int
    {
        $result = $this->query->count($this->db);
        $this->fresh();
        return $result;
    }

    public function paginateResult(int $page, int $perPage = 10): array
    {
        $result = $this->query->paginateResult($this->db, $page, $perPage);
        $this->fresh();
        return $result;
    }

    public function insert(array $data): int
    {
        return $this->fresh()->insert($this->db, $data);
    }

    public function insertMany(array $rows): int
    {
        return $this->fresh()->insertMany($this->db, $rows);
    }

    public function update(array $data): int
    {
        $result = $this->query->update($this->db, $data);
        $this->fresh();
        return $result;
    }

    public function delete(): int
    {
        $result = $this->query->delete($this->db);
        $this->fresh();
        return $result;
    }

    public function toSQL(): string
    {
        return $this->query->toSQL();
    }
}