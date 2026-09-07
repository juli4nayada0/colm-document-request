<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Notification Service
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Models\Notification;

class NotificationService {
    private Notification $notificationModel;

    public function __construct() {
        $this->notificationModel = new Notification();
    }

    /**
     * Send notification to user
     */
    public function notify(int $userId, string $title, string $message, string $type = 'info', ?int $requestId = null): int {
        return $this->notificationModel->create($userId, $title, $message, $type, $requestId);
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, int $limit = 30): array {
        return $this->notificationModel->getByUserId($userId, $limit);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId): int {
        return $this->notificationModel->countUnread($userId);
    }

    /**
     * Mark single notification read
     */
    public function markRead(int $notificationId, int $userId): bool {
        return $this->notificationModel->markAsRead($notificationId, $userId);
    }

    /**
     * Mark all read
     */
    public function markAllRead(int $userId): bool {
        return $this->notificationModel->markAllAsRead($userId);
    }

    /**
     * Delete a notification owned by a user
     */
    public function delete(int $notificationId, int $userId): bool {
        return $this->notificationModel->deleteForUser($notificationId, $userId);
    }
}
