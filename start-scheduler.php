<?php
/**
 * Railway Scheduler Runner
 * 
 * Since Railway doesn't support cron jobs, this script runs Laravel's scheduler
 * continuously in the background. You can deploy this as a separate service.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

echo "🚀 Laravel Scheduler starting...\n";

while (true) {
    echo "[" . date('Y-m-d H:i:s') . "] Running scheduled tasks...\n";
    
    try {
        // Run the Laravel scheduler
        $exitCode = $app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->call('schedule:run', [], null);
        
        if ($exitCode === 0) {
            echo "✅ Scheduled tasks completed successfully\n";
        } else {
            echo "⚠️ Scheduled tasks completed with exit code: $exitCode\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Scheduler error: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    // Sleep for 60 seconds before next run
    sleep(60);
}