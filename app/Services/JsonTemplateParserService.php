<?php

namespace App\Services;

use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LaravelIdea\Helper\App\Models\_IH_TemplateTagCategory_C;

/**
 * JsonTemplateParserService
 *
 * Seeds a user's template tag rows from the catalogue in
 * TemplateDataMapperService, which is the one place a static tag is declared.
 *
 * Until Aug 2026 this class walked the Twitch JSON payload and invented tag
 * names from the paths it happened to find. That made a user's tag list a
 * function of what their account contained at the moment they signed up: a
 * streamer with no subscribers that night never got subscribers_latest_* at
 * all, and everybody got artefacts like `channel_count` (a count of the keys
 * on the channel object) and `channel_followers_pagination_cursor`. A second
 * job, CleanupRedundantTags, existed purely to delete a subset of the mess
 * this one made.
 *
 * Seeding from the catalogue instead makes the result deterministic: the same
 * account always produces the same tags, tailoring is declared per tag via
 * `requires` rather than emerging from data shape, and pruning is inherent to
 * the sync rather than a separate pass.
 */
class JsonTemplateParserService
{
    public function __construct(
        private readonly TemplateDataMapperService $templateDataMapper,
    ) {}

    /**
     * Bring a user's tag rows in line with the catalogue.
     *
     * Creates the categories and tags the user qualifies for, refreshes the
     * sample data on the ones that already exist, and deletes any row that is
     * no longer in the catalogue (or that the user no longer qualifies for).
     *
     * @param  array  $twitchData  the payload from TwitchApiService::getExtendedUserData()
     * @return array{categories: int, tags: int, updated: int, removed: int, removed_names: array<int, string>}
     */
    public function syncTagsForUser(User $user, array $twitchData): array
    {
        $eligible = TemplateDataMapperService::tagCatalog();

        return DB::transaction(function () use ($user, $eligible, $twitchData) {
            $categoryIds = $this->syncCategories($user, $eligible);
            $result = $this->syncTags($user, $eligible, $twitchData, $categoryIds);
            $result['categories'] = count($categoryIds);

            return $result;
        });
    }

    /**
     * Create any category the eligible tags need. Categories the user no longer
     * has tags for are dropped in syncTags() once their rows are gone.
     *
     * @param  array<string, array<string, mixed>>  $eligible
     * @return array<string, int> category name => id
     */
    private function syncCategories(User $user, array $eligible): array
    {
        $meta = TemplateDataMapperService::tagCategoryMeta();
        $needed = array_values(array_unique(array_column($eligible, 'category')));

        $ids = [];
        $sortOrder = 0;

        foreach ($meta as $name => $info) {
            if (! in_array($name, $needed, true)) {
                continue;
            }

            $category = TemplateTagCategory::updateOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                [
                    'display_name' => $info['display_name'],
                    'description' => $info['description'],
                    'is_group' => false,
                    'sort_order' => $sortOrder++,
                ]
            );

            $ids[$name] = $category->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, array<string, mixed>>  $eligible
     * @param  array<string, int>  $categoryIds
     * @return array{tags: int, updated: int, removed: int, removed_names: array<int, string>}
     */
    private function syncTags(User $user, array $eligible, array $twitchData, array $categoryIds): array
    {
        $existing = TemplateTag::where('user_id', $user->id)->get()->keyBy('tag_name');

        // Render the payload once through the same mapping the overlay uses, so
        // a tag's stored sample is exactly the string it will put on screen -
        // channel_tags joined rather than a raw array, dates as unix seconds.
        $rendered = $this->templateDataMapper->mapTwitchDataForTemplates($twitchData, '');

        $created = 0;
        $updated = 0;

        foreach ($eligible as $tagName => $spec) {
            $row = $this->buildRow($tagName, $spec, $twitchData, $rendered) + [
                'user_id' => $user->id,
                'category_id' => $categoryIds[$spec['category']],
            ];

            $current = $existing->get($tagName);

            if ($current) {
                $current->update($row);
                $updated++;

                continue;
            }

            TemplateTag::create($row + ['tag_name' => $tagName]);
            $created++;
        }

        // Anything left is not in the catalogue any more, or the user no longer
        // qualifies for it. This is what CleanupRedundantTags used to do, except
        // it only ever matched the `_data_\d` shape and left the rest behind.
        $stale = $existing->keys()->diff(array_keys($eligible));

        if ($stale->isNotEmpty()) {
            TemplateTag::where('user_id', $user->id)
                ->whereIn('tag_name', $stale->all())
                ->delete();
        }

        // Drop categories that ended up with nothing in them.
        TemplateTagCategory::where('user_id', $user->id)
            ->doesntHave('templateTags')
            ->delete();

        return [
            'tags' => $created,
            'updated' => $updated,
            'removed' => $stale->count(),
            'removed_names' => $stale->values()->all(),
        ];
    }

    /**
     * Build the persisted row for one catalogue entry. Sample data is the
     * account's real value when the path resolves, so the tag browser shows
     * "1,234 followers" rather than a made-up number, and falls back to the
     * catalogue's sample when the account has nothing there yet.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function buildRow(string $tagName, array $spec, array $twitchData, array $rendered): array
    {
        $path = $spec['path'] ?? null;

        // valueAtPath decides whether the account HAS this field; $rendered
        // decides how it looks. mapTwitchDataForTemplates() substitutes a
        // default for anything missing, so it cannot answer the first question.
        $hasLiveValue = $path !== null
            && $this->templateDataMapper->valueAtPath($twitchData, $path) !== null;

        $live = $hasLiveValue ? ($rendered[$tagName] ?? null) : null;

        return [
            'display_tag' => "[[[$tagName]]]",
            'json_path' => $path ?? $tagName,
            'data_type' => $spec['type'],
            'display_name' => $spec['label'],
            'description' => $spec['desc'],
            'sample_data' => $live ?? $spec['sample'],
            'formatting_options' => $this->formattingOptions($spec['type']),
            'tag_type' => 'standard',
            'version' => '1.0',
            'is_editable' => false,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formattingOptions(string $dataType): ?array
    {
        if ($dataType === 'datetime') {
            return [
                'date_format' => 'd-m-Y H:i',
                'available_formats' => [
                    'd-m-Y H:i' => 'DD-MM-YYYY HH:MM',
                    'Y-m-d H:i:s' => 'YYYY-MM-DD HH:MM:SS',
                    'M j, Y' => 'Month Day, Year',
                    'D, M j Y' => 'Day, Month Day Year',
                ],
            ];
        }

        if (in_array($dataType, ['integer', 'float'], true)) {
            return [
                'number_format' => [
                    'decimals' => $dataType === 'float' ? 2 : 0,
                    'thousands_separator' => ',',
                ],
            ];
        }

        return null;
    }

    /**
     * Get all template tags organised by category for a specific user
     */
    public function getOrganizedTemplateTagsForUser(int $userId): array
    {
        $categories = TemplateTagCategory::with('activeTemplateTags')
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        return $this->extracted_categories($categories);
    }

    public function extracted_categories(_IH_TemplateTagCategory_C|Collection|array $categories): array
    {
        $organized = [];

        foreach ($categories as $category) {
            $organized[$category->name] = [
                'category' => $category,
                'tags' => $category->activeTemplateTags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag_name' => $tag->tag_name,
                        'display_tag' => $tag->display_tag,
                        'display_name' => $tag->display_name,
                        'description' => $tag->description,
                        'data_type' => $tag->data_type,
                        'sample_data' => $tag->sample_data,
                        'json_path' => $tag->json_path,
                    ];
                }),
            ];
        }

        return $organized;
    }
}
