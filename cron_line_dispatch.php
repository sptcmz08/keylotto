<?php
/**
 * Dedicated LINE scheduled dispatcher.
 *
 * Run this from crontab every minute so scheduled text/image sends do not
 * wait for long-running scraper jobs to finish.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/line/common.php';

date_default_timezone_set('Asia/Bangkok');

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));

echo "=======================================\n";
echo "LINE Dispatch - " . $now->format('Y-m-d H:i:s') . "\n";
echo "=======================================\n\n";

try {
    $scheduledTextStats = lineSendDueScheduledMessages($pdo, $now);
    if (!empty($scheduledTextStats['sent_messages'])) {
        echo "Scheduled LINE text: {$scheduledTextStats['sent_messages']} items / {$scheduledTextStats['sent_groups']} groups ({$scheduledTextStats['time']})\n";
    } elseif (!empty($scheduledTextStats['due_messages'])) {
        echo "Scheduled LINE text due: {$scheduledTextStats['due_messages']} items but not delivered ({$scheduledTextStats['time']}, grace {$scheduledTextStats['grace_minutes']}m)\n";
    } elseif (!empty($scheduledTextStats['skipped'])) {
        echo "Scheduled LINE text skipped: " . (string) ($scheduledTextStats['reason'] ?? 'unknown') . "\n";
    } else {
        echo "Scheduled LINE text: nothing due\n";
    }
} catch (Throwable $scheduledTextError) {
    echo "Scheduled LINE text failed: " . $scheduledTextError->getMessage() . "\n";
    lineLog('Scheduled LINE text failed (cron_line_dispatch): ' . $scheduledTextError->getMessage());
}

echo "\n";

try {
    $scheduledImageStats = lineSendDueScheduledImages($pdo, $now);
    if (!empty($scheduledImageStats['sent_messages'])) {
        echo "Scheduled LINE image: {$scheduledImageStats['sent_messages']} items / {$scheduledImageStats['sent_groups']} groups ({$scheduledImageStats['time']})\n";
    } elseif (!empty($scheduledImageStats['due_messages'])) {
        echo "Scheduled LINE image due: {$scheduledImageStats['due_messages']} items but not delivered ({$scheduledImageStats['time']}, grace {$scheduledImageStats['grace_minutes']}m)\n";
    } elseif (!empty($scheduledImageStats['skipped'])) {
        echo "Scheduled LINE image skipped: " . (string) ($scheduledImageStats['reason'] ?? 'unknown') . "\n";
    } else {
        echo "Scheduled LINE image: nothing due\n";
    }
} catch (Throwable $scheduledImageError) {
    echo "Scheduled LINE image failed: " . $scheduledImageError->getMessage() . "\n";
    lineLog('Scheduled LINE image failed (cron_line_dispatch): ' . $scheduledImageError->getMessage());
}

echo "\nDone.\n";
