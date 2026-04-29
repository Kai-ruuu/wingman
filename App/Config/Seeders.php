<?php

namespace Wingman\Config;

use mysqli;

class Seeders
{
    private mysqli $db;
    private array $seeders = [
        // registering a seeder
        // SampleSeeder::class
    ];

    private function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public static function withDatabase(mysqli $db): self
    {
        return new self($db);
    }

    public function seedAll(): void
    {
        foreach ($this->seeders as $seeder)
        {
            $seederInstance = new $seeder($this->db);
            $seederInstance->describe();
            $seederInstance->seed();
        }
    }
}