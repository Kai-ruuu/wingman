<?php

namespace Wingman\Core\Bases;

use mysqli;
use Wingman\Core\App\Logger;
use Wingman\Core\CLI\Colorizer;

/**
 * BaseModel provides a fluent interface for defining and managing database table schemas.
 * 
 * Subclasses should set $this->table and call column definition methods
 * (e.g. primaryKey, characters, integer) chained together, then finalize
 * with build() to execute the CREATE TABLE statement.
 * 
 * Example usage in a subclass:
 * 
 *   $this->table = 'users';
 *   $this->primaryKey()
 *        ->characters('name')->required()
 *        ->characters('email')->unique()->required()
 *        ->boolean('is_active', true)
 *        ->timestamps()
 *        ->build();
 */
class BaseModel
{
    /** The active MySQLi database connection. */
    protected mysqli $db;

    /** The name of the table this model manages. Set by the subclass. */
    public string $table;

    /** Accumulates column definition strings to be joined into the CREATE TABLE query. */
    private array $columns = [];

    /**
     * @param mysqli $db An active MySQLi database connection.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Instantiates a BaseModel with the given database connection.
     * Intended as a static factory for use in contexts where 'new self()' isn't convenient.
     * 
     * @param mysqli $db An active MySQLi database connection.
     */
    protected static function withDatabase(mysqli $db): self
    {
        return new self($db);
    }

    /**
     * Adds an auto-incrementing integer primary key column.
     * 
     * @param string $columnName The column name. Defaults to 'id'.
     */
    protected function primaryKey(string $columnName = 'id'): self
    {
        $this->columns[] = "{$columnName} INT AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Adds a FOREIGN KEY constraint referencing another table's column.
     * 
     * @param string $columnName The local column that holds the foreign key value.
     * @param string $from       The referenced column in "table.column" dot notation (e.g. "users.id").
     * @param string $onDelete   The ON DELETE behavior (e.g. 'CASCADE', 'SET NULL', 'RESTRICT').
     */
    protected function foreignKey(string $columnName, string $from, string $onDelete): self
    {
        [$table, $column] = explode('.', $from);
        $onDelete = strtoupper($onDelete);
        $this->columns[] = "FOREIGN KEY ({$columnName}) REFERENCES {$table}({$column}) ON DELETE {$onDelete}";
        return $this;
    }

    /**
     * Adds an INT column.
     * 
     * @param string $columnName The column name.
     * @param ?int $default      Optional default value.
     */
    protected function integer(string $columnName, ?int $default = null): self
    {
        $default = $default !== null ? "DEFAULT {$default}" : '';
        $this->columns[] = rtrim("{$columnName} INT " . $default, ' ');
        return $this;
    }

    /**
     * Adds a FLOAT column.
     * 
     * @param string $columnName The column name.
     * @param ?float $default    Optional default value.
     */
    protected function float(string $columnName, ?float $default = null): self
    {
        $default = $default !== null ? "DEFAULT " . sprintf('%F', $default) : '';
        $this->columns[] = rtrim("{$columnName} FLOAT " . $default, ' ');
        return $this;
    }

    /**
     * Adds a VARCHAR column.
     * 
     * @param string $columnName The column name.
     * @param int    $maxLength  Maximum character length. Defaults to 255.
     * @param string $default    Optional default string value.
     */
    protected function characters(string $columnName, int $maxLength = 255, string $default = ''): self
    {
        $default = $default ? "DEFAULT '{$default}'" : '';
        $this->columns[] = rtrim("{$columnName} VARCHAR({$maxLength}) " . $default, ' ');
        return $this;
    }

    /**
     * Adds a TEXT column.
     * 
     * @param string $columnName The column name.
     * @param string $default    Optional default string value.
     */
    protected function text(string $columnName, string $default = ''): self
    {
        $default = $default ? "DEFAULT '{$default}'" : '';
        $this->columns[] = rtrim("{$columnName} TEXT " . $default, ' ');
        return $this;
    }

    /**
     * Adds a BOOLEAN column.
     * 
     * @param string $columnName The column name.
     * @param bool   $default    Default value. Defaults to FALSE.
     */
    protected function boolean(string $columnName, bool $default = false): self
    {
        $default = "DEFAULT " . ($default ? 'TRUE' : 'FALSE');
        $this->columns[] = rtrim("{$columnName} BOOLEAN " . $default, ' ');
        return $this;
    }

    /**
     * Adds a generic TIMESTAMP column with an optional custom default value.
     * For automatic timestamp management, prefer createdAt() or updatedAt().
     * 
     * @param string $columnName The column name. Defaults to 'created_at'.
     * @param string $default    Optional default value (e.g. a datetime string).
     */
    protected function timestamp(string $columnName = 'created_at', string $default = ''): self
    {
        $default = $default ? "DEFAULT '{$default}'" : '';
        $this->columns[] = rtrim("{$columnName} TIMESTAMP " . $default, ' ');
        return $this;
    }

    /**
     * Adds a TIMESTAMP column that automatically records when the row was inserted.
     * Defaults to CURRENT_TIMESTAMP.
     * 
     * @param string $columnName The column name. Defaults to 'created_at'.
     */
    protected function createdAt(string $columnName = 'created_at'): self
    {
        $this->columns[] = "{$columnName} TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        return $this;
    }

    /**
     * Adds a TIMESTAMP column that automatically updates to the current time on every row update.
     * Defaults to CURRENT_TIMESTAMP and refreshes ON UPDATE CURRENT_TIMESTAMP.
     * 
     * @param string $columnName The column name. Defaults to 'updated_at'.
     */
    protected function updatedAt(string $columnName = 'updated_at'): self
    {
        $this->columns[] = "{$columnName} TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    /**
     * Adds both a createdAt() and an updatedAt() column as a convenience shorthand.
     * Equivalent to calling createdAt() then updatedAt() individually.
     */
    protected function timestamps(): self
    {
        $this->createdAt();
        $this->updatedAt();
        return $this;
    }

    /**
     * Adds an ENUM column restricted to the provided set of options.
     * 
     * @param string   $columnName The column name.
     * @param string[] $options    The allowed enum values.
     * @param string   $default    The default enum value. Must be one of $options.
     */
    protected function enum(string $columnName, array $options, string $default): self
    {
        $default = "DEFAULT '{$default}'";
        $joinedOptions = "'" . implode("', '", $options) . "'";
        $this->columns[] = rtrim("{$columnName} ENUM(" . $joinedOptions . ") " . $default, ' ');
        return $this;
    }

    /**
     * Appends UNIQUE to the most recently defined column.
     * Must be called immediately after the column definition it applies to.
     */
    protected function unique(): self
    {
        $targetIndex = count($this->columns) - 1;
        $targetColumn = $this->columns[$targetIndex];
        $this->columns[$targetIndex] = $targetColumn . " UNIQUE";
        return $this;
    }

    /**
     * Appends NOT NULL to the most recently defined column.
     * Must be called immediately after the column definition it applies to.
     */
    protected function required(): self
    {
        $targetIndex = count($this->columns) - 1;
        $targetColumn = $this->columns[$targetIndex];
        $this->columns[$targetIndex] = $targetColumn . " NOT NULL";
        return $this;
    }

    /**
     * Appends NULL to the most recently defined column, explicitly allowing null values.
     * Must be called immediately after the column definition it applies to.
     */
    protected function optional(): self
    {
        $targetIndex = count($this->columns) - 1;
        $targetColumn = $this->columns[$targetIndex];
        $this->columns[$targetIndex] = $targetColumn . " NULL";
        return $this;
    }

    /**
     * Define the table schema here by chaining column definition methods.
     * Override this method in subclasses to describe the table structure.
     * 
     * Example:
     * 
     *   $this->table = 'users';
     *   $this->primaryKey()
     *        ->characters('email')->unique()->required()
     *        ->boolean('is_active', true)
     *        ->timestamps()
     *        ->build();
     */
    public function describe(): void
    {
        
    }

    /**
     * Executes the CREATE TABLE IF NOT EXISTS statement using all defined columns.
     * Optionally prints the full schema to stdout for debugging or logging.
     * 
     * @param bool $showSchema If true, prints the formatted CREATE TABLE statement to stdout.
     */
    public function build(bool $showSchema = false): void
    {
        $joinedColumns = join(',', $this->columns);
        $fullQuery = "CREATE TABLE IF NOT EXISTS {$this->table} (" . $joinedColumns . ");";
        $sql = $this->db->prepare($fullQuery);
        $sql->execute();
        
        if ($showSchema)
        {
            echo Colorizer::green("Model table '{$this->table}' has been created with schema:") . Colorizer::reset(PHP_EOL);
            echo "CREATE TABLE IF NOT EXISTS {$this->table} (" . PHP_EOL;
            
            $columnIndex = 0;
            
            foreach ($this->columns as $column)
            {
                $comma = $columnIndex !== count($this->columns) - 1 ? ',' : '';
                echo '    ' . $column . $comma . PHP_EOL;
                $columnIndex++;
            }
                    
            echo ");" . PHP_EOL;
            return;
        }

        Logger::success("Model table '{$this->table}' has been created");
    }

    /**
     * Executes a DROP TABLE IF EXISTS statement for this model's table.
     * Use with caution — this permanently destroys the table and all its data.
     */
    public function demolish(): void
    {
        $fullQuery = "DROP TABLE IF EXISTS {$this->table};";
        $sql = $this->db->prepare($fullQuery);
        $sql->execute();
        Logger::success("Model table '{$this->table}' has been dropped.");
    }
}