<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Date and Overdue Calculation Helper
 */

declare(strict_types=1);

namespace Colm\Helpers;

use DateTime;
use DateTimeZone;

class DateHelper {
    public const TIMEZONE = 'Asia/Manila';

    /**
     * Get current timestamp in Manila timezone
     */
    public static function now(): string {
        $dt = new DateTime('now', new DateTimeZone(self::TIMEZONE));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Format a MySQL date/datetime string to friendly display
     */
    public static function formatDisplay(?string $dateTimeStr, string $format = 'M d, Y h:i A'): string {
        if (empty($dateTimeStr)) {
            return '—';
        }
        try {
            $dt = new DateTime($dateTimeStr, new DateTimeZone(self::TIMEZONE));
            return $dt->format($format);
        } catch (\Exception $e) {
            return $dateTimeStr;
        }
    }

    /**
     * Calculate expected completion date adding processing days (excluding weekends if requested)
     */
    public static function calculateTargetDate(string $submittedAt, int $processingDays): string {
        $dt = new DateTime($submittedAt, new DateTimeZone(self::TIMEZONE));
        $addedDays = 0;

        while ($addedDays < $processingDays) {
            $dt->modify('+1 day');
            // Skip Saturday (6) and Sunday (7) if institutional policy counts business days
            $dayOfWeek = (int)$dt->format('N');
            if ($dayOfWeek <= 5) {
                $addedDays++;
            }
        }

        return $dt->format('Y-m-d');
    }

    /**
     * Compute operational overdue state
     * Returns: 'On Time' | 'Due Soon' | 'Overdue' | 'Completed'
     */
    public static function getOverdueStatus(string $submittedAt, int $processingDays, ?string $completedAt = null, string $currentStatus = ''): string {
        if (in_array($currentStatus, ['RELEASED', 'READY FOR RELEASE', 'REJECTED/CANCELLED'], true) || !empty($completedAt)) {
            return 'Completed';
        }

        $targetDateStr = self::calculateTargetDate($submittedAt, $processingDays);
        $targetDt = new DateTime($targetDateStr . ' 23:59:59', new DateTimeZone(self::TIMEZONE));
        $nowDt = new DateTime('now', new DateTimeZone(self::TIMEZONE));

        if ($nowDt > $targetDt) {
            return 'Overdue';
        }

        $diffHours = ($targetDt->getTimestamp() - $nowDt->getTimestamp()) / 3600;
        if ($diffHours <= 24) {
            return 'Due Soon';
        }

        return 'On Time';
    }
}
