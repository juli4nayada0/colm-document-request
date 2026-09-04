<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Notification Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Services\NotificationService;
use Colm\Helpers\ResponseHelper;

class NotificationController {
    private array $currentUser;
    private NotificationService $notificationService;

    public function __construct() {
        $auth = new AuthMiddleware();
        $this->currentUser = $auth->handle();
        $this->notificationService = new NotificationService();
    }

    /**
     * GET /api/v1/notifications
     */
    public function index(): void {
        $userId = $this->currentUser['user_id'];
        $notifications = $this->notificationService->getUserNotifications($userId, 40);
        $unreadCount = $this->notificationService->getUnreadCount($userId);

        ResponseHelper::success([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications
        ], 'Notifications retrieved.');
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     */
    public function markRead(int $id): void {
        $userId = $this->currentUser['user_id'];
        $this->notificationService->markRead($id, $userId);
        ResponseHelper::success(null, 'Notification marked as read.');
    }

    /**
     * PATCH /api/v1/notifications/read-all
     */
    public function markAllRead(): void {
        $userId = $this->currentUser['user_id'];
        $this->notificationService->markAllRead($userId);
        ResponseHelper::success(null, 'All notifications marked as read.');
    }
}
