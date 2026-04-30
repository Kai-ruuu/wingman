<?php

namespace Wingman\Core\App;

use DateTime;
use Wingman\Core\App\Response;

/**
 * Validator
 *
 * A static utility class for validating and sanitizing incoming request values.
 * All methods accept a human-readable label used in error messages, and a raw
 * string value (typically from request body or params).
 *
 * Validators come in two flavors:
 *
 * - Optional  (e.g. int, string, email)    → returns null if the value is empty
 * - Required  (e.g. requiredInt, requiredString) → responds with 422 and halts if empty
 *
 * On a validation failure, methods call Response::unprocessableEntity() which
 * terminates the request with a 422 response and a descriptive error message.
 * No exception is thrown — execution stops immediately.
 *
 * Usage:
 *
 *   // Optional — returns null if not provided
 *   $age = Validator::int('age', $request->fromBody('age'), 1, 120);
 *
 *   // Required — halts with 422 if not provided or invalid
 *   $email = Validator::requiredEmail('email', $request->fromBody('email'));
 */
class Validator
{
    /**
     * Validates an optional integer value.
     *
     * Returns null if the value is empty. Responds with 422 if the value
     * is not a valid integer or falls outside the optional min/max bounds.
     *
     * @param  string   $label   Field name used in error messages (e.g. 'user ID')
     * @param  ?string  $value   Raw input value to validate
     * @param  ?int     $minimum Minimum allowed value (inclusive), or null to skip
     * @param  ?int     $maximum Maximum allowed value (inclusive), or null to skip
     * @return ?int              Validated integer, or null if empty
     */
    public static function int(string $label, ?string $value, ?int $minimum = null, ?int $maximum = null): ?int
    {
        if (empty($value))
            return null;

        $value = filter_var($value, FILTER_VALIDATE_INT);

        if ($value === false)
            Response::unprocessableEntity(['message' => $label . ' should be a valid whole number.']);

        if ($minimum !== null && $value < $minimum)
            Response::unprocessableEntity(['message' => $label . ' should be at least ' . $minimum]);

        if ($maximum !== null && $value > $maximum)
            Response::unprocessableEntity(['message' => $label . ' should not exceed ' . $maximum]);

        return $value;
    }

    /**
     * Validates an optional float value.
     *
     * Returns null if the value is empty. Responds with 422 if the value
     * is not a valid float or falls outside the optional min/max bounds.
     *
     * @param  string   $label   Field name used in error messages (e.g. 'price')
     * @param  ?string  $value   Raw input value to validate
     * @param  ?float   $minimum Minimum allowed value (inclusive), or null to skip
     * @param  ?float   $maximum Maximum allowed value (inclusive), or null to skip
     * @return ?float            Validated float, or null if empty
     */
    public static function float(string $label, ?string $value, ?float $minimum = null, ?float $maximum = null): ?float
    {
        if (empty($value))
            return null;

        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($value === false)
            Response::unprocessableEntity(['message' => $label . ' should be a valid number.']);

        if ($minimum !== null && $value < $minimum)
            Response::unprocessableEntity(['message' => $label . ' should be at least ' . $minimum]);

        if ($maximum !== null && $value > $maximum)
            Response::unprocessableEntity(['message' => $label . ' should not exceed ' . $maximum]);

        return $value;
    }

    /**
     * Validates an optional boolean-like value.
     *
     * Returns null if the value is empty. Accepts a range of truthy and
     * falsy string representations for flexibility.
     *
     * Accepted truthy values:  'true', '1', 'yes', 'high'
     * Accepted falsy values:   'false', '0', 'no', 'low'
     *
     * Responds with 422 if the value does not match any accepted string.
     *
     * @param  string  $label Field name used in error messages (e.g. 'is active')
     * @param  ?string $value Raw input value to validate
     * @return ?bool          True, false, or null if empty
     */
    public static function bool(string $label, ?string $value): ?bool
    {
        if (empty($value))
            return null;

        $value     = strtolower($value);
        $trueVals  = ['true', '1', 'yes', 'high'];
        $falseVals = ['false', '0', 'no', 'low'];

        if (!in_array($value, $trueVals) && !in_array($value, $falseVals))
            Response::unprocessableEntity(['message' => $label . ' should be a boolean-like value.']);

        if (in_array($value, $trueVals))
            return true;

        if (in_array($value, $falseVals))
            return false;

        Response::unprocessableEntity(['message' => $label . ' should be a boolean-like value.']);
        return null;
    }

    /**
     * Validates an optional string value.
     *
     * Returns null if the value is empty. Responds with 422 if the string
     * length falls outside the optional min/max length bounds.
     *
     * @param  string  $label     Field name used in error messages (e.g. 'username')
     * @param  ?string $value     Raw input value to validate
     * @param  ?int    $minLength Minimum character length (inclusive), or null to skip
     * @param  ?int    $maxLength Maximum character length (inclusive), or null to skip
     * @return ?string            Validated string, or null if empty
     */
    public static function string(string $label, ?string $value, ?int $minLength = null, ?int $maxLength = null): ?string
    {
        if (empty($value))
            return null;

        if ($minLength !== null && strlen($value) < $minLength)
            Response::unprocessableEntity(['message' => $label . ' should have at least ' . $minLength . ' characters.']);

        if ($maxLength !== null && strlen($value) > $maxLength)
            Response::unprocessableEntity(['message' => $label . ' should not exceed ' . $maxLength . ' characters.']);

        return $value;
    }

    /**
     * Validates an optional email address.
     *
     * Returns null if the value is empty. Sanitizes the value first using
     * FILTER_SANITIZE_EMAIL, then validates the format with FILTER_VALIDATE_EMAIL.
     * Responds with 422 if the sanitized value is not a valid email.
     *
     * @param  string  $label Field name used in error messages (e.g. 'email')
     * @param  ?string $value Raw input value to validate
     * @return ?string        Sanitized and validated email, or null if empty
     */
    public static function email(string $label, ?string $value): ?string
    {
        if (empty($value))
            return null;

        $value = filter_var($value, FILTER_SANITIZE_EMAIL);

        if (filter_var($value, FILTER_VALIDATE_EMAIL))
            return $value;

        Response::unprocessableEntity(['message' => $label . ' should be a valid email address.']);
        return null;
    }

    /**
     * Validates an optional value against a PHP backed enum.
     *
     * Returns null if the value is empty. Uses the enum's tryFrom() method
     * to match the raw value against valid enum cases. Responds with 422
     * and lists all valid values if no match is found.
     *
     * @param  string $label     Field name used in error messages (e.g. 'role')
     * @param  ?string $value    Raw input value to validate
     * @param  string $enumClass Fully-qualified backed enum class name (e.g. Role::class)
     * @return mixed             Matched enum case, or null if empty
     */
    public static function enum(string $label, ?string $value, string $enumClass): mixed
    {
        if (empty($value))
            return null;

        $result = $enumClass::tryFrom($value);

        if ($result === null) {
            $valid = implode(', ', array_column($enumClass::cases(), 'value'));
            Response::unprocessableEntity(['message' => $label . ' must be one of: ' . $valid . '.']);
        }

        return $result;
    }

    /**
     * Validates an optional JSON string.
     *
     * Returns null if the value is empty. Decodes the JSON string into an
     * associative array. Responds with 422 if the string is not valid JSON.
     *
     * @param  string  $label Field name used in error messages (e.g. 'metadata')
     * @param  ?string $value Raw JSON string to validate
     * @return ?array         Decoded associative array, or null if empty
     */
    public static function json(string $label, ?string $value): ?array
    {
        if (empty($value))
            return null;

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE)
            Response::unprocessableEntity(['message' => $label . ' should be a valid JSON string.']);

        return $decoded;
    }

    /**
     * Validates an optional date string.
     *
     * Returns null if the value is empty. Validates the date against the
     * provided format using DateTime::createFromFormat(). Responds with 422
     * if the value does not match the expected format or is not a real date.
     *
     * @param  string      $label   Field name used in error messages (e.g. 'birth date')
     * @param  ?string     $value   Raw input value to validate (e.g. '2000-12-31')
     * @param  string      $format  Expected date format (default: 'Y-m-d')
     * @return string|null          Validated date string, or null if empty
     */
    public static function date(string $label, ?string $value, string $format = 'Y-m-d'): ?string
    {
        if (empty($value))
            return null;

        $parsed = DateTime::createFromFormat($format, $value);

        if (!$parsed || $parsed->format($format) !== $value)
            Response::unprocessableEntity(['message' => $label . ' should be a valid date in the format ' . $format . '.']);

        return $value;
    }

    // -------------------------------------------------------------------------
    // Required variants
    // Behave identically to their optional counterparts but respond with 422
    // immediately if the value is empty, before any further validation runs.
    // -------------------------------------------------------------------------

    /**
     * Validates a required integer value.
     * Responds with 422 if the value is empty or invalid.
     *
     * @param  string  $label   Field name used in error messages
     * @param  ?string $value   Raw input value to validate
     * @param  ?int    $minimum Minimum allowed value (inclusive), or null to skip
     * @param  ?int    $maximum Maximum allowed value (inclusive), or null to skip
     * @return int              Validated integer
     */
    public static function requiredInt(string $label, ?string $value, ?int $minimum = null, ?int $maximum = null): int
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::int($label, $value, $minimum, $maximum);
    }

    /**
     * Validates a required float value.
     * Responds with 422 if the value is empty or invalid.
     *
     * @param  string  $label   Field name used in error messages
     * @param  ?string $value   Raw input value to validate
     * @param  ?float  $minimum Minimum allowed value (inclusive), or null to skip
     * @param  ?float  $maximum Maximum allowed value (inclusive), or null to skip
     * @return float            Validated float
     */
    public static function requiredFloat(string $label, ?string $value, ?float $minimum = null, ?float $maximum = null): float
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::float($label, $value, $minimum, $maximum);
    }

    /**
     * Validates a required boolean-like value.
     * Responds with 422 if the value is null or invalid.
     *
     * @param  string  $label Field name used in error messages
     * @param  ?string $value Raw input value to validate
     * @return bool           Validated boolean
     */
    public static function requiredBool(string $label, ?string $value): bool
    {
        if ($value === null)
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::bool($label, $value);
    }

    /**
     * Validates a required string value.
     * Responds with 422 if the value is empty or invalid.
     *
     * @param  string  $label     Field name used in error messages
     * @param  ?string $value     Raw input value to validate
     * @param  ?int    $minLength Minimum character length (inclusive), or null to skip
     * @param  ?int    $maxLength Maximum character length (inclusive), or null to skip
     * @return string             Validated string
     */
    public static function requiredString(string $label, ?string $value, ?int $minLength = null, ?int $maxLength = null): string
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::string($label, $value, $minLength, $maxLength);
    }

    /**
     * Validates a required email address.
     * Responds with 422 if the value is empty or not a valid email.
     *
     * @param  string  $label Field name used in error messages
     * @param  ?string $value Raw input value to validate
     * @return string         Sanitized and validated email
     */
    public static function requiredEmail(string $label, ?string $value): string
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::email($label, $value);
    }

    /**
     * Validates a required backed enum value.
     * Responds with 422 if the value is empty or does not match any enum case.
     *
     * @param  string  $label     Field name used in error messages
     * @param  ?string $value     Raw input value to validate
     * @param  string  $enumClass Fully-qualified backed enum class name
     * @return mixed              Matched enum case
     */
    public static function requiredEnum(string $label, ?string $value, string $enumClass): mixed
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::enum($label, $value, $enumClass);
    }

    /**
     * Validates a required JSON string.
     * Responds with 422 if the value is empty or not valid JSON.
     *
     * @param  string  $label Field name used in error messages
     * @param  ?string $value Raw JSON string to validate
     * @return array          Decoded associative array
     */
    public static function requiredJson(string $label, ?string $value): array
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::json($label, $value);
    }

    /**
     * Validates a required date string.
     * Responds with 422 if the value is empty or does not match the expected format.
     *
     * @param  string  $label   Field name used in error messages
     * @param  ?string $value   Raw input value to validate
     * @param  string  $format  Expected date format (default: 'Y-m-d')
     * @return string           Validated date string
     */
    public static function requiredDate(string $label, ?string $value, string $format = 'Y-m-d'): string
    {
        if (empty($value))
            Response::unprocessableEntity(['message' => $label . ' is required.']);

        return self::date($label, $value, $format);
    }
}