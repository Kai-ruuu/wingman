<?php

namespace Wingman\Core\App;

/**
 * Response
 *
 * A static utility class for sending JSON HTTP responses.
 * Every method sets the appropriate HTTP status code, the
 * Content-Type header, encodes the payload as JSON, and
 * terminates execution immediately via die.
 *
 * All public methods accept an optional $data array. If omitted
 * or empty, a default message matching the status is used instead.
 *
 * Since every method halts execution, no return value is needed
 * and callers do not need to return after invoking a Response method.
 *
 * Usage:
 *
 *   Response::ok(['id' => 1, 'username' => 'john']);
 *   Response::notFound(['message' => 'User not found.']);
 *   Response::unprocessableEntity(['message' => 'Email is required.']);
 */
class Response
{
    /**
     * Encodes the payload as JSON, sets the status code and Content-Type
     * header, outputs the response, and terminates execution.
     *
     * This is the single exit point for all HTTP responses in Wingman.
     *
     * @param array $payload    The response body to encode as JSON
     * @param int   $statusCode HTTP status code (default: 200)
     */
    private static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        die;
    }

    /**
     * 200 OK
     * Responds with the provided data. Use for successful reads,
     * updates, and deletes.
     *
     * @param array $data The response payload
     */
    public static function ok(array $data): void
    {
        self::json($data);
    }

    /**
     * 201 Created
     * Responds with the provided data. Use for a successful creation.
     *
     * @param array $data The response payload
     */
    public static function created(array $data): void
    {
        self::json($data, 201);
    }

    /**
     * 401 Unauthorized
     * The request lacks valid authentication credentials.
     * Use when a user is not logged in or their token is invalid/expired.
     *
     * @param array $data Custom response payload (optional)
     */
    public static function unauthorized(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Unauthorized.'] : $data, 401);
    }

    /**
     * 422 Unprocessable Entity
     * The request was well-formed but contains invalid or missing fields.
     * Use for validation failures.
     *
     * @param array $data Custom response payload (optional)
     */
    public static function unprocessableEntity(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Unprocessable.'] : $data, 422);
    }

    /**
     * 403 Forbidden
     * The request is authenticated but the user lacks permission
     * to access the requested resource.
     *
     * @param array $data Custom response payload (optional)
     */
    public static function forbidden(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Forbidden.'] : $data, 403);
    }

    /**
     * 400 Bad Request
     * The server could not understand the request due to malformed
     * syntax or invalid parameters.
     *
     * @param array $data Custom response payload (optional)
     */
    public static function badRequest(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Bad request.'] : $data, 400);
    }

    /**
     * 409 Conflict
     * The request conflicts with the current state of the resource.
     * Use when a unique constraint is violated (e.g. duplicate email or username).
     *
     * @param array $data Custom response payload (optional)
     */
    public static function conflict(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Conflict.'] : $data, 409);
    }

    /**
     * 404 Not Found
     * The requested resource could not be found.
     *
     * @param array $data Custom response payload (optional)
     */
    public static function notFound(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Not found.'] : $data, 404);
    }

    /**
     * 500 Internal Server Error
     * An unexpected server-side error occurred. Use as a catch-all
     * for unhandled exceptions or missing controller/method references.
     *
     * @param array $data Custom response payload (optional)
     */
    public static function internalServerError(array $data = []): void
    {
        self::json(empty($data) ? ['message' => 'Internal server error.'] : $data, 500);
    }
}