<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Canonical response envelope. Every controller result is shaped like this
 * (either explicitly via these helpers, or implicitly by the Server wrapper).
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): array
    {
        $response = [
            'success' => true,
            'data'    => $data,
            'message' => $message,
            'error'   => false,
            'status'  => $status,
        ];
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        return $response;
    }

    public static function error(string|array $message = 'Error', int $status = 400, mixed $data = null, array $errors = []): array
    {
        $response = [
            'success' => false,
            'data'    => $data,
            'message' => $message,
            'error'   => true,
            'status'  => $status,
        ];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        return $response;
    }

    public static function created(mixed $data = null, string $message = 'Created'): array
    {
        return self::success($data, $message, 201);
    }

    public static function updated(mixed $data = null, string $message = 'Updated'): array
    {
        return self::success($data, $message, 200);
    }

    public static function deleted(string $message = 'Deleted'): array
    {
        return self::success(null, $message, 200);
    }

    public static function notFound(string $message = 'Not found'): array
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): array
    {
        return self::error($message, 403);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): array
    {
        return self::error($message, 422, null, $errors);
    }

    public static function serverError(string $message = 'Internal server error'): array
    {
        return self::error($message, 500);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage, string $message = 'OK'): array
    {
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        return self::success($items, $message, 200, [
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => ($page - 1) * $perPage + 1,
                'to'           => min($page * $perPage, $total),
            ],
        ]);
    }

    public static function collection(array $items, string $message = 'OK'): array
    {
        return self::success($items, $message, 200, ['count' => count($items)]);
    }
}
