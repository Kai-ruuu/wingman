<?php

namespace Wingman\Config;

use mysqli;

class Models
{
    private mysqli $db;
    private array $models = [
        // registering a model
        // SampleModel::class
    ];

    private function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public static function withDatabase(mysqli $db): self
    {
        return new self($db);
    }

    public function buildAll(bool $showSchema = false): void
    {
        foreach ($this->models as $model)
        {
            $modelInstance = new $model($this->db);
            $modelInstance->describe();
            $modelInstance->build($showSchema);
        }
    }

    public function demolishAll(): void
    {
        foreach ($this->models as $model)
        {
            $modelInstance = new $model($this->db);
            $modelInstance->demolish();
        }
    }
}