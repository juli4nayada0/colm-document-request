<?php
/**
 * COLM Registrar Document Request and Tracking System
 * JSON Response Helper
 */

declare(strict_types=1);

namespace Colm\Helpers;

class ResponseHelper {
    /**
     * Send standard success JSON response
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $meta = []): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        $response = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send standard error JSON response
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null, ?string $errorCode = null): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Stream file download directly to client with safety headers
     */
    public static function streamFile(string $filePath, string $downloadFilename, string $mimeType): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            self::error('Requested file not found or inaccessible.', 404);
        }

        // Clean output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($downloadFilename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }
}
