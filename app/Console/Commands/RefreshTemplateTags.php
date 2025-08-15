<?php

namespace App\Console\Commands;

use App\Models\OverlayTemplate;
use Illuminate\Console\Command;

class RefreshTemplateTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:refresh-tags {--type=} {--id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-extract template tags for overlay templates to include event.* tags';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = OverlayTemplate::query();
        
        // Filter by type if specified
        if ($type = $this->option('type')) {
            if (!in_array($type, ['static', 'alert'])) {
                $this->error('Invalid type. Must be "static" or "alert".');
                return 1;
            }
            $query->where('type', $type);
            $this->info("Filtering by type: $type");
        }
        
        // Filter by ID if specified
        if ($id = $this->option('id')) {
            $query->where('id', $id);
            $this->info("Filtering by ID: $id");
        }
        
        $templates = $query->get();
        $count = $templates->count();
        
        if ($count === 0) {
            $this->warn('No templates found matching the criteria.');
            return 0;
        }
        
        $this->info("Found $count template(s) to update.");
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        $updated = 0;
        foreach ($templates as $template) {
            $oldTags = $template->template_tags ?? [];
            $newTags = $template->extractTemplateTags();
            
            if ($oldTags !== $newTags) {
                $template->template_tags = $newTags;
                $template->save();
                $updated++;
                
                $this->line('');
                $this->info("Updated template #{$template->id} ({$template->name}):");
                $this->line('  Old tags: ' . json_encode($oldTags));
                $this->line('  New tags: ' . json_encode($newTags));
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->line('');
        $this->info("Successfully updated $updated template(s).");
        
        return 0;
    }
}
