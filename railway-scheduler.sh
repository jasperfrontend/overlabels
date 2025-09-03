#!/bin/bash

# Railway Scheduler Service Script
# This runs Laravel's scheduler in the background on Railway

echo "🚀 Starting Laravel Scheduler for Railway..."

while true; do
    echo "⏰ Running scheduled tasks at $(date)"
    
    # Run Laravel scheduler
    php artisan schedule:run --verbose
    
    # Wait 60 seconds before next run
    sleep 60
done