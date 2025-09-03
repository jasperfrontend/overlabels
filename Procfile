# Railway Procfile - defines multiple processes
# Each process runs in a separate Railway service

# Main web application
web: php -S 0.0.0.0:$PORT public/index.php

# Queue worker for background jobs (EventSub setup, etc.)
worker: php artisan queue:work --verbose --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600

# Scheduler for automated tasks (EventSub monitoring, cleanup)
scheduler: bash railway-scheduler.sh
