<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Private File Storage & Secure Delivery Service
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Helpers\ResponseHelper;
use InvalidArgumentException;
use RuntimeException;

class FileService {
    private string $basePrivateDir;
    private array $allowedMimes;
    private array $allowedExtensions;
    private int $maxSizeMb;

    public function __construct() {
        $config = require dirname(__DIR__) . '/config/config.php';
        $storage = $config['storage'];

        $this->basePrivateDir    = $storage['private_dir'];
        $this->allowedMimes      = $storage['allowed_mimes'];
        $this->allowedExtensions = $storage['allowed_extensions'];
        $this->maxSizeMb         = $storage['max_file_size_mb'];

        $this->ensureDirectories();
    }

    /**
     * Ensure storage directories exist with access protection
     */
    private function ensureDirectories(): void {
        $subdirs = ['requirements', 'payments', 'completed_docs'];

        if (!is_dir($this->basePrivateDir)) {
            mkdir($this->basePrivateDir, 0750, true);
        }

        // Write .htaccess in private directory to prevent direct web access
        $htaccessFile = $this->basePrivateDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Require all denied\nDeny from all\n");
        }

        foreach ($subdirs as $dir) {
            $path = $this->basePrivateDir . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0750, true);
            }
        }
    }

    /**
     * Store uploaded file securely
     */
    public function storeUploadedFile(array $fileArray, string $category = 'requirements'): array {
        if (!isset($fileArray['error']) || is_array($fileArray['error'])) {
            throw new InvalidArgumentException('Invalid file upload payload.', 400);
        }

        switch ($fileArray['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new InvalidArgumentException('No file was uploaded.', 400);
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new InvalidArgumentException("File exceeded maximum allowed size limit ({$this->maxSizeMb} MB).", 400);
            default:
                throw new RuntimeException('File upload error code: ' . $fileArray['error'], 500);
        }

        // Validate file size
        $maxBytes = $this->maxSizeMb * 1024 * 1024;
        if ($fileArray['size'] > $maxBytes) {
            throw new InvalidArgumentException("File size exceeds {$this->maxSizeMb} MB limit.", 422);
        }

        $originalFilename = basename($fileArray['name']);
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        // Reject forbidden extensions
        $forbidden = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'vbs'];
        if (in_array($ext, $forbidden, true) || !in_array($ext, $this->allowedExtensions, true)) {
            throw new InvalidArgumentException("File extension .{$ext} is not permitted.", 422);
        }

        // Validate MIME type via FileInfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detectedMime, $this->allowedMimes, true)) {
            throw new InvalidArgumentException("Invalid file type detected ({$detectedMime}). Only official documents and images are allowed.", 422);
        }

        // Generate randomized secure stored filename
        $storedFilename = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(16)), $ext);
        $targetDir = $this->basePrivateDir . '/' . $category;
        $targetPath = $targetDir . '/' . $storedFilename;

        if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to persist uploaded file to secure storage.', 500);
        }

        return [
            'original_filename' => $originalFilename,
            'stored_filename'   => $storedFilename,
            'file_path'         => $category . '/' . $storedFilename,
            'mime_type'         => $detectedMime,
            'file_size'         => (int)$fileArray['size'],
            'full_path'         => $targetPath
        ];
    }

    /**
     * Get absolute path for stored file
     */
    public function getAbsolutePath(string $relativePath): string {
        // Prevent path traversal
        $normalized = str_replace(['../', '..\\'], '', $relativePath);
        return $this->basePrivateDir . '/' . ltrim($normalized, '/\\');
    }
}
