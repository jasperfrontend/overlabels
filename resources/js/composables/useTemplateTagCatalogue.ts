/**
 * The static tag catalogue (`GET /api/template-tags`), fetched once per page
 * and shared between the Tags tab and the editor's autocomplete.
 *
 * State is module-level on purpose: both consumers mount on the same page,
 * and before this composable existed the Tags tab owned the fetch and its
 * localStorage cache. Moving it here means the editor does not issue a second
 * request for the same constant list.
 *
 * Cached copies are shown immediately and revalidated in the background. An
 * empty response is never cached - the catalogue is a constant, so empty means
 * the request failed, and caching that would hide working tags for an hour.
 */

import type { CatalogueTag } from '@/utils/tagCompletions';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

export interface CatalogueTagRow {
  tag_name?: string;
  display_tag: string;
  display_name?: string;
  description: string;
  sample_data?: string;
  data_type?: string;
  is_live?: boolean;
}

export interface CatalogueCategory {
  category?: {
    display_name: string;
    description?: string;
  };
  tags?: CatalogueTagRow[];
  active_template_tags?: CatalogueTagRow[];
}

export type TagCatalogue = Record<string, CatalogueCategory>;

interface CatalogueResponse {
  tags: TagCatalogue;
  event_tags?: string[];
}

interface CachedCatalogue {
  tags: TagCatalogue;
  eventTags: string[];
  timestamp: number;
  version: string;
}

const CACHE_DURATION = 60 * 60 * 1000;
// v4 dropped per-user tag rows (payload lost `id`, gained `is_live`).
// v5 added `event_tags` alongside the catalogue.
const CURRENT_CACHE_VERSION = 'v5';

const catalogue = ref<TagCatalogue>({});
const eventTags = ref<string[]>([]);
let inflight: Promise<void> | null = null;

function cacheKeyFor(userId: number | string | undefined): string {
  return userId ? `template_tags_cache_user_${userId}` : 'template_tags_cache_anon';
}

function readCache(key: string): CachedCatalogue | null {
  try {
    const cached = localStorage.getItem(key);
    const version = localStorage.getItem(`${key}_version`);
    if (!cached || version !== CURRENT_CACHE_VERSION) return null;

    const data: CachedCatalogue = JSON.parse(cached);
    if (Date.now() - data.timestamp > CACHE_DURATION) {
      localStorage.removeItem(key);
      return null;
    }
    return data;
  } catch (error) {
    console.error('Error reading cache:', error);
    localStorage.removeItem(key);
    return null;
  }
}

function writeCache(key: string, tags: TagCatalogue, events: string[]): void {
  try {
    const data: CachedCatalogue = { tags, eventTags: events, timestamp: Date.now(), version: CURRENT_CACHE_VERSION };
    localStorage.setItem(key, JSON.stringify(data));
    localStorage.setItem(`${key}_version`, CURRENT_CACHE_VERSION);
  } catch (error) {
    console.error('Error setting cache:', error);
  }
}

/**
 * Flatten the grouped catalogue into the list the completer consumes, tagging
 * each entry with its category's display name for the section header.
 */
export function flattenCatalogue(grouped: TagCatalogue): CatalogueTag[] {
  const flat: CatalogueTag[] = [];

  for (const [key, group] of Object.entries(grouped)) {
    const category = group.category?.display_name ?? key;
    for (const row of group.active_template_tags ?? group.tags ?? []) {
      const name = row.tag_name ?? row.display_tag.replace(/^\[\[\[|]]]$/g, '');
      flat.push({ tag_name: name, description: row.description, data_type: row.data_type, category });
    }
  }

  return flat;
}

export function useTemplateTagCatalogue() {
  const page = usePage();
  const userId = (page.props.auth as { user?: { id?: number | string } } | undefined)?.user?.id;
  const cacheKey = cacheKeyFor(userId);

  function load(): Promise<void> {
    if (inflight) return inflight;

    const cached = readCache(cacheKey);
    if (cached) {
      catalogue.value = cached.tags;
      eventTags.value = cached.eventTags ?? [];
    }

    inflight = axios
      .get<CatalogueResponse>(route('tags.api.all'))
      .then((response) => {
        const tags = response.data.tags;
        if (tags && Object.keys(tags).length > 0) {
          catalogue.value = tags;
          eventTags.value = response.data.event_tags ?? [];
          writeCache(cacheKey, tags, eventTags.value);
        }
      })
      .catch(() => {
        console.error('Error retrieving tags from api');
      })
      .finally(() => {
        inflight = null;
      });

    return inflight;
  }

  return { catalogue, eventTags, load };
}
