# Railway Procfile
# Web app + queue worker (EventSub monitoring via external cron service)

# Main web application
web: php -S 0.0.0.0:$PORT public/index.php

# Queue worker for background jobs (EventSub setup, etc.)
worker: php artisan queue:work --verbose --tries=3 --timeout=300 --sleep=3 --max-jobs=1000 --max-time=3600
