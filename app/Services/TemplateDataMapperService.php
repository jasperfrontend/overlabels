<?php

namespace App\Services;

/**
 * TemplateDataMapperService
 * 
 * CENTRALIZED service for all template tag mapping and data transformation.
 * This consolidates the mapping logic from JsonTemplateParserService to avoid duplication.
 * 
 * For Laravel beginners:
 * - This service is the SINGLE SOURCE OF TRUTH for template tag mappings
 * - Both template generation AND template parsing use this same mapping logic
 * - This ensures consistency between database tags and runtime template parsing
 */
class TemplateDataMapperService
{
    /**
     * MASTER MAPPING - Single source of truth for all template tag mappings
     * Format: 'json_path' => 'template_tag_name'
     * This is used by BOTH JsonTemplateParserService AND template parsing
     */
    private function getTemplateMappings(): array
    {
        return [
            // User data mappings
            'user.id' => 'user_id',
            'user.login' => 'user_login', 
            'user.display_name' => 'user_name',
            'user.type' => 'user_type',
            'user.broadcaster_type' => 'user_broadcaster_type',
            'user.description' => 'user_description',
            'user.profile_image_url' => 'user_avatar',
            'user.offline_image_url' => 'user_offline_banner',
            'user.view_count' => 'user_view_count',
            'user.email' => 'user_email',
            'user.created_at' => 'user_created',

            // Channel data mappings
            'channel.broadcaster_id' => 'channel_id',
            'channel.broadcaster_login' => 'channel_login', 
            'channel.broadcaster_name' => 'channel_name',
            'channel.broadcaster_language' => 'channel_language',
            'channel.game_id' => 'channel_game_id',
            'channel.game_name' => 'channel_game',
            'channel.title' => 'channel_title',
            'channel.delay' => 'channel_delay',
            'channel.tags' => 'channel_tags',
            'channel.content_classification_labels' => 'channel_content_labels',
            'channel.is_branded_content' => 'channel_is_branded',
            
            // Followers mappings
            'channel_followers.total' => 'followers_total',
            'channel_followers.data.0.user_id' => 'followers_latest_user_id',
            'channel_followers.data.0.user_login' => 'followers_latest_user_login',
            'channel_followers.data.0.user_name' => 'followers_latest_user_name',
            'channel_followers.data.0.followed_at' => 'followers_latest_date',
            
            // Followed channels mappings
            'followed_channels.total' => 'followed_total',
            'followed_channels.data.0.broadcaster_id' => 'followed_latest_id',
            'followed_channels.data.0.broadcaster_login' => 'followed_latest_login',
            'followed_channels.data.0.broadcaster_name' => 'followed_latest_name',
            'followed_channels.data.0.followed_at' => 'followed_latest_date',
            
            // Subscribers mappings
            'subscribers.points' => 'subscribers_points',
            'subscribers.total' => 'subscribers_total',
            'subscribers.data.0.broadcaster_id' => 'subscribers_latest_broadcaster_id',
            'subscribers.data.0.broadcaster_login' => 'subscribers_latest_broadcaster_login',
            'subscribers.data.0.broadcaster_name' => 'subscribers_latest_broadcaster_name',
            'subscribers.data.0.gifter_id' => 'subscribers_latest_gifter_id',
            'subscribers.data.0.gifter_login' => 'subscribers_latest_gifter_login',
            'subscribers.data.0.gifter_name' => 'subscribers_latest_gifter_name',
            'subscribers.data.0.is_gift' => 'subscribers_latest_is_gift',
            'subscribers.data.0.plan_name' => 'subscribers_latest_plan_name',
            'subscribers.data.0.tier' => 'subscribers_latest_tier',
            'subscribers.data.0.user_id' => 'subscribers_latest_user_id',
            'subscribers.data.0.user_name' => 'subscribers_latest_user_name',
            'subscribers.data.0.user_login' => 'subscribers_latest_user_login',

            // Goals mappings
            'goals.data.0.type' => 'goals_latest_type',
            'goals.data.0.target' => 'goals_latest_target',
            'goals.data.0.current' => 'goals_latest_current',
            'goals.data.0.description' => 'goals_latest_description',
            'goals.data.0.created_at' => 'goals_latest_created_at',
        ];
    }

    /**
    * Wraps the provided HTML body and CSS into a complete HTML document.
    * Used to generate a full HTML page for overlay rendering.
    * 
    * @param string $bodyHtml The HTML content to include in the body.
    * @param string $css The CSS styles to inject into the document.
    * @param string $title The title of the HTML document (default: 'Overlay').
    * @return string The complete HTML document as a string.
    **/
    public function wrapHtmlAndCssIntoDocument(string $bodyHtml, string $css, string $title = 'Overlay'): string
    {
        return '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <style>' . $css . '</style>
    </head>
    <body>
    ' . $bodyHtml . '
    </body>
    </html>';
    }

    /**
     * Get standardized template tag name from JSON path
     * This replaces JsonTemplateParserService::getStandardizedTagName()
     */
    public function getStandardizedTagName(string $jsonPath): string
    {
        $mappings = $this->getTemplateMappings();
        
        // First, try exact match
        if (isset($mappings[$jsonPath])) {
            return $mappings[$jsonPath];
        }

        // If no exact match, build logical name from path
        $parts = explode('.', $jsonPath);
        
        // Handle array access like "data.0.field_name" -> prefix with parent + "latest_" + field
        if (count($parts) >= 3 && $parts[count($parts) - 2] === '0' && $parts[count($parts) - 3] === 'data') {
            $parentObject = $parts[count($parts) - 4] ?? 'unknown';
            $fieldName = $parts[count($parts) - 1];
            return $parentObject . '_latest_' . $fieldName;
        }
        
        // For simple paths, convert dots to underscores
        return str_replace('.', '_', $jsonPath);
    }

    /**
     * Transform Twitch API data structure into template-friendly flat structure
     * This uses the same mappings as getStandardizedTagName for consistency
     */
    public function mapTwitchDataForTemplates(array $twitchData, string $overlayName): array
    {
        $mappings = $this->getTemplateMappings();
        $templateData = [
            'overlay_name' => $overlayName,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        // Apply all mappings with error handling
        foreach ($mappings as $jsonPath => $templateTag) {
            $value = $this->getNestedValue($twitchData, $jsonPath);
            
            if ($value !== null) {
                // Apply formatting based on tag type
                $templateData[$templateTag] = $this->formatValueForTemplate($value, $templateTag);
            } else {
                // Provide default values for missing data
                $templateData[$templateTag] = $this->getDefaultValue($templateTag);
            }
        }

        return $templateData;
    }
    
    /**
     * Get sample data for template previews and testing
     * This matches the structure of the real mapped data
     */
    public function getSampleTemplateData(): array
    {
        return [
            'overlay_name' => 'My Awesome Overlay',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            
            // User information
            'user_id' => '123456789',
            'user_login' => 'streamername',
            'user_name' => 'StreamerName',
            'user_type' => '',
            'user_broadcaster_type' => 'partner',
            'user_description' => 'Welcome to my awesome stream!',
            'user_avatar' => 'https://static-cdn.jtvnw.net/user-default-pictures.png',
            'user_offline_banner' => '',
            'user_view_count' => '123,456',
            'user_email' => 'streamer@example.com',
            'user_created' => '2 years ago',
            
            // Channel information
            'channel_id' => '123456789',
            'channel_login' => 'streamername',
            'channel_name' => 'StreamerName',
            'channel_language' => 'en',
            'channel_game_id' => '509658',
            'channel_game' => 'Just Chatting',
            'channel_title' => 'Playing Awesome Game - Come Join!',
            'channel_delay' => '0',
            'channel_tags' => 'Gaming, Fun, Community',
            'channel_content_labels' => 'None',
            'channel_is_branded' => 'No',
            
            // Followers information
            'followers_total' => '1,234',
            'followers_latest_id' => '987654321',
            'followers_latest_login' => 'newfollower123',
            'followers_latest_name' => 'NewFollower123',
            'followers_latest_date' => '2 hours ago',
            
            // Followed channels information
            'followed_total' => '567',
            'followed_latest_id' => '111222333',
            'followed_latest_login' => 'coolstreamer',
            'followed_latest_name' => 'CoolStreamer',
            'followed_latest_date' => '1 day ago',
            
            // Subscribers information
            'subscribers_total' => '89',
            'subscribers_points' => '12,345',
            'subscribers_latest_user_id' => '444555666',
            'subscribers_latest_user_login' => 'newsubscriber',
            'subscribers_latest_user_name' => 'NewSubscriber',
            'subscribers_latest_broadcaster_id' => '123456789',
            'subscribers_latest_broadcaster_login' => 'streamername',
            'subscribers_latest_broadcaster_name' => 'StreamerName',
            'subscribers_latest_is_gift' => 'No',
            'subscribers_latest_tier' => '1000',
            'subscribers_latest_plan_name' => 'Tier 1',
            'subscribers_latest_gifter_id' => 'N/A',
            'subscribers_latest_gifter_login' => 'N/A',
            'subscribers_latest_gifter_name' => 'N/A',
            
            // Goals information
            'goals_latest_type' => 'follower',
            'goals_latest_target' => '2,000',
            'goals_latest_current' => '1,234',
            'goals_latest_description' => 'Road to 2K followers!',
            'goals_latest_created_at' => '1 week ago',
        ];
    }
    
    /**
     * Get list of all available template tags with descriptions
     * This helps with documentation and validation
     */
    public function getAvailableTemplateTags(): array
    {
        return [
            // Overlay metadata
            'overlay_name' => 'Name of the overlay',
            'timestamp' => 'Current timestamp',
            
            // User information
            'user_id' => 'User ID',
            'user_login' => 'User login name',
            'user_name' => 'User display name',
            'user_type' => 'User type',
            'user_broadcaster_type' => 'Broadcaster type (partner, affiliate, etc.)',
            'user_description' => 'User bio/description',
            'user_avatar' => 'Profile image URL',
            'user_offline_banner' => 'Offline image URL',
            'user_view_count' => 'Total view count',
            'user_email' => 'User email',
            'user_created' => 'Account creation date',
            
            // Channel information
            'channel_id' => 'Channel ID',
            'channel_login' => 'Channel login name',
            'channel_name' => 'Channel display name',
            'channel_language' => 'Channel language',
            'channel_game_id' => 'Current game/category ID',
            'channel_game' => 'Current game/category name',
            'channel_title' => 'Stream title',
            'channel_delay' => 'Stream delay in seconds',
            'channel_tags' => 'Channel tags',
            'channel_content_labels' => 'Content classification labels',
            'channel_is_branded' => 'Whether channel has branded content',
            
            // Followers information
            'followers_total' => 'Total number of followers',
            'followers_latest_id' => 'Latest follower ID',
            'followers_latest_login' => 'Latest follower login',
            'followers_latest_name' => 'Latest follower name',
            'followers_latest_date' => 'Latest follow date',
            
            // Followed channels information
            'followed_total' => 'Total followed channels',
            'followed_latest_id' => 'Latest followed channel ID',
            'followed_latest_login' => 'Latest followed channel login',
            'followed_latest_name' => 'Latest followed channel name',
            'followed_latest_date' => 'Latest follow date',
            
            // Subscribers information
            'subscribers_total' => 'Total number of subscribers',
            'subscribers_points' => 'Subscriber points',
            'subscribers_latest_user_id' => 'Latest subscriber user ID',
            'subscribers_latest_user_login' => 'Latest subscriber login',
            'subscribers_latest_user_name' => 'Latest subscriber name',
            'subscribers_latest_broadcaster_id' => 'Broadcaster ID',
            'subscribers_latest_broadcaster_login' => 'Broadcaster login',
            'subscribers_latest_broadcaster_name' => 'Broadcaster name',
            'subscribers_latest_is_gift' => 'Whether subscription is a gift',
            'subscribers_latest_tier' => 'Subscription tier',
            'subscribers_latest_plan_name' => 'Subscription plan name',
            'subscribers_latest_gifter_id' => 'Gift giver ID (if applicable)',
            'subscribers_latest_gifter_login' => 'Gift giver login (if applicable)',
            'subscribers_latest_gifter_name' => 'Gift giver name (if applicable)',
            
            // Goals information
            'goals_latest_type' => 'Goal type',
            'goals_latest_target' => 'Goal target',
            'goals_latest_current' => 'Current progress',
            'goals_latest_description' => 'Goal description',
            'goals_latest_created_at' => 'Goal creation date',
        ];
    }

    /**
     * Get category mappings for organizing template tags
     * This is used by JsonTemplateParserService for database organization
     */
    public function getTagCategories(): array
    {
        return [
            'user' => [
                'display_name' => 'User Information',
                'description' => 'Basic user account information',
                'tags' => ['user_id', 'user_login', 'user_name', 'user_type', 'user_broadcaster_type', 'user_description', 'user_avatar', 'user_offline_banner', 'user_view_count', 'user_email', 'user_created']
            ],
            'channel' => [
                'display_name' => 'Channel Information', 
                'description' => 'Channel settings and current stream info',
                'tags' => ['channel_id', 'channel_login', 'channel_name', 'channel_language', 'channel_game_id', 'channel_game', 'channel_title', 'channel_delay', 'channel_tags', 'channel_content_labels', 'channel_is_branded']
            ],
            'followers' => [
                'display_name' => 'Followers',
                'description' => 'Follower statistics and latest follower info',
                'tags' => ['followers_total', 'followers_latest_id', 'followers_latest_login', 'followers_latest_name', 'followers_latest_date']
            ],
            'followed' => [
                'display_name' => 'Followed Channels',
                'description' => 'Channels that this user follows',
                'tags' => ['followed_total', 'followed_latest_id', 'followed_latest_login', 'followed_latest_name', 'followed_latest_date']
            ],
            'subscribers' => [
                'display_name' => 'Subscribers',
                'description' => 'Subscriber statistics and latest subscriber info',
                'tags' => ['subscribers_total', 'subscribers_points', 'subscribers_latest_user_id', 'subscribers_latest_user_login', 'subscribers_latest_user_name', 'subscribers_latest_broadcaster_id', 'subscribers_latest_broadcaster_login', 'subscribers_latest_broadcaster_name', 'subscribers_latest_is_gift', 'subscribers_latest_tier', 'subscribers_latest_plan_name', 'subscribers_latest_gifter_id', 'subscribers_latest_gifter_login', 'subscribers_latest_gifter_name']
            ],
            'goals' => [
                'display_name' => 'Goals',
                'description' => 'Channel goals and progress',
                'tags' => ['goals_latest_type', 'goals_latest_target', 'goals_latest_current', 'goals_latest_description', 'goals_latest_created_at']
            ],
            'overlay' => [
                'display_name' => 'Overlay Metadata',
                'description' => 'Information about the overlay itself',
                'tags' => ['overlay_name', 'timestamp']
            ]
        ];
    }
    
    /**
     * Helper: Get nested value from array using dot notation
     * Enhanced with better error handling for missing data
     */
    private function getNestedValue(array $data, string $key)
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $nestedKey) {
            if (is_array($value) && isset($value[$nestedKey])) {
                $value = $value[$nestedKey];
            } else {
                // Log missing data for debugging but don't fail
                if ($nestedKey !== '0') { // Don't log missing array indices, those are expected
                    \Illuminate\Support\Facades\Log::debug("Missing nested value for key: {$key} at {$nestedKey}");
                }
                return null;
            }
        }

        return $value;
    }

    /**
     * Format value for template display
     */
    private function formatValueForTemplate($value, string $templateTag): string
    {
        // Handle different data types
        if (is_array($value)) {
            if (in_array($templateTag, ['channel_tags'])) {
                return implode(', ', $value);
            }
            return json_encode($value);
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_numeric($value) && in_array($templateTag, ['followers_total', 'followed_total', 'subscribers_total', 'subscribers_points', 'user_view_count', 'goals_latest_target', 'goals_latest_current'])) {
            return number_format($value);
        }

        // Format dates
        if (str_contains($templateTag, '_date') || str_contains($templateTag, '_created')) {
            return $this->formatDate($value);
        }
        
        return (string) $value;
    }

    /**
     * Get default value for missing data
     */
    private function getDefaultValue(string $templateTag): string
    {
        if (str_contains($templateTag, 'total') || str_contains($templateTag, 'count') || str_contains($templateTag, 'points')) {
            return '0';
        }
        
        if (str_contains($templateTag, 'is_') || str_contains($templateTag, '_gift')) {
            return 'No';
        }
        
        return 'N/A';
    }

    /**
     * Format date for display
     */
    private function formatDate(?string $dateString): string
    {
        if (!$dateString) {
            return 'N/A';
        }
        
        try {
            return \Carbon\Carbon::parse($dateString)->diffForHumans();
        } catch (\Exception $e) {
            return $dateString; // Return original if parsing fails
        }
    }
}