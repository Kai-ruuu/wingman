<?php

namespace Wingman\Core\Bases;

use Exception;
use mysqli;
use Wingman\Core\App\Logger;
use Wingman\Core\CLI\Colorizer;

/**
 * BaseSeeder
 *
 * The base class for all Wingman seeders. Provides the core seeding
 * mechanism — inserting predefined rows into a model's table — and
 * defines the describe() lifecycle hook that concrete seeders override
 * to configure their model and seed data.
 *
 * Seeders are intended to be used in development and staging environments
 * to populate the database with initial or test data.
 *
 * Lifecycle:
 *   1. Seeder is instantiated with a mysqli connection
 *   2. describe() is called — the concrete seeder calls setup() here
 *      to register the target model class and seed data
 *   3. seed() is called — iterates over the seed data and inserts
 *      each row into the model's table via the QueryableModel::insert() method
 *
 * Usage (in a concrete seeder):
 *
 *   class UserSeeder extends BaseSeeder
 *   {
 *       public function describe(): void
 *       {
 *           $this->setup(UserModel::class, [
 *               ['username' => 'admin', 'email' => 'admin@example.com'],
 *               ['username' => 'guest', 'email' => 'guest@example.com'],
 *           ]);
 *       }
 *   }
 *
 * Running via CLI:
 *   php wing seed --name=User      ← runs UserSeeder only
 *   php wing seed --all            ← runs all registered seeders
 */
class BaseSeeder
{
    /** The mysqli database connection passed to the model on instantiation */
    private mysqli $db;

    /** Fully-qualified model class name to seed (e.g. 'Wingman\Models\UserModel') */
    private string $model;

    /**
     * The seed data — an array of associative arrays, each representing
     * one row to insert into the model's table.
     * e.g. [['username' => 'admin', 'email' => 'admin@example.com'], ...]
     */
    private array $seeds = [];

    /**
     * @param mysqli $db The database connection to use for seeding
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Configures the seeder with the target model and seed data.
     * Must be called inside describe() by the concrete seeder class.
     *
     * @param string $model Fully-qualified model class name (e.g. UserModel::class)
     * @param array  $seeds Array of rows to insert, each as an associative array
     */
    protected function setup(string $model, array $seeds): void
    {
        $this->model = $model;
        $this->seeds = $seeds;
    }

    /**
     * Lifecycle hook — override this in your concrete seeder to call setup()
     * with the target model and seed data.
     *
     * This method is intentionally left empty in the base class.
     * It is called automatically before seed() by the CLI and Seeders config.
     */
    public function describe(): void
    {
        // Override in concrete seeder to call $this->setup(ModelClass::class, [...])
    }

    /**
     * Executes the seeding process by inserting all seed rows into the model's table.
     *
     * Instantiates the configured model with the database connection, then
     * iterates over the seed data and calls insert() for each row. Prints a
     * green success message if all rows are inserted, or a red error message
     * with the exception details if any insert fails.
     *
     * Requires describe() to have been called first to configure the model and seeds.
     */
    public function seed(): void
    {
        $model = new $this->model($this->db);

        try {
            foreach ($this->seeds as $seed)
            {
                $model->insert($seed);
            }

            Logger::success("All seeds for model table '{$model->table}' has been seeded.");
        } catch (Exception $e) {
            Logger::error('Failed to seed all seeds - ' . $e->getMessage());
        }
    }
}