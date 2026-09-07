<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Notification Model
 */

declare(strict_types=1);

namespace Colm\Models;

class Notification extends BaseModel {
    /**
     * Get notifications for a user
     */
    public function getByUserId(int $userId, int $limit = 30): array {
        $sql = "SELECT n.*, r.tracking_number 
                FROM notifications n 
                LEFT JOIN document_requests r ON n.request_id = r.request_id 
                WHERE n.user_id = :user_id 
                ORDER BY n.created_at DESC 
                LIMIT {$limit}";
        return $this->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Count unread notifications
     */
    public function countUnread(int $userId): int {
        $sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $row = $this->fetchOne($sql, ['user_id' => $userId]);
        return (int)($row['unread_count'] ?? 0);
    }

    /**
     * Create notification
     */
    public function create(int $userId, string $title, string $message, string $type = 'info', ?int $requestId = null): int {
        $sql = "INSERT INTO notifications (user_id, request_id, title, message, notification_type, is_read, created_at)
                VALUES (:user_id, :request_id, :title, :message, :type, 0, NOW())";
        
        $this->execute($sql, [
            'user_id'    => $userId,
            'request_id' => $requestId,
            'title'      => $title,
            'message'    => $message,
            'type'       => $type
        ]);

        return $this->lastInsertId();
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool {
        $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND user_id = :user_id";
        return $this->execute($sql, ['id' => $notificationId, 'user_id' => $userId]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): bool {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        return $this->execute($sql, ['user_id' => $userId]);
    }

    /**
     * Delete a notification owned by a user
     */
    public function deleteForUser(int $notificationId, int $userId): bool {
        $sql = "DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id";
        return $this->execute($sql, ['id' => $notificationId, 'user_id' => $userId]);
    }
}
