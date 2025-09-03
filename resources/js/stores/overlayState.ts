import { defineStore } from 'pinia';
import { normalizeEvent } from '@/composables/useNormalizeEvent';
import { EVENT_RULES } from '@/composables/useTwitchEventRules';
import type { NormalizedEvent } from '@/types';

type TagValue = string | number | boolean | null;
type StateTags = Record<string, TagValue>;

export interface NotificationSettings {
  enabled: boolean;
  duration: number;
  position: string;
  size: string;
  customProps?: Record<string, any>;
}

export const useEventsStore = defineStore('events', {
  state: () => ({
    tags: {} as StateTags,
    events: [] as NormalizedEvent[],
    notificationSettings: {
      enabled: true,
      duration: 5000,
      position: 'top-center',
      size: 'medium',
    } as NotificationSettings,
    recentEvents: [] as NormalizedEvent[],
    maxRecentEvents: 50,
  }),

  getters: {
    getEventsByType: (state) => (eventType: string) => {
      return state.events.filter(event => event.type === eventType);
    },

    getRecentEventsByType: (state) => (eventType: string, limit = 10) => {
      return state.recentEvents
        .filter(event => event.type === eventType)
        .slice(0, limit);
    },

    getStatsByType: (state) => (eventType: string) => {
      const events = state.events.filter(event => event.type === eventType);
      return {
        total: events.length,
        recent: events.filter(event => Date.now() - event.ts < 3600000).length,
        latest: events[events.length - 1] || null,
      };
    },
  },

  actions: {
    /**
     * Apply a normalized event to the store using EVENT_RULES.
     */
    processEventRules(ev: NormalizedEvent) {
      const rules = EVENT_RULES[ev.type];
      if (!rules) return;

      rules.forEach(rule => {
        const { op, tag, value, from, by, byPath } = rule;
        let val: any = value;

        if (from) {
          val = from.split('.').reduce((acc:any, key:any) => acc?.[key], ev.raw);
        }

        if (byPath) {
          const byVal = byPath.split('.').reduce((acc:any, key:any) => acc?.[key], ev.raw);
          if (typeof byVal === 'number') {
            this.tags[tag] = (Number(this.tags[tag]) || 0) + byVal;
            return;
          }
        }

        switch (op) {
          case 'set':
            this.tags[tag] = val;
            break;
          case 'inc':
            this.tags[tag] = (Number(this.tags[tag]) || 0) + (by ?? 1);
            break;
          case 'max':
            this.tags[tag] = Math.max(Number(this.tags[tag]) || 0, val ?? 0);
            break;
          case 'do':
            this.tags[tag] = val ?? true;
            break;
          default:
            console.warn(`Unknown op: ${op}`);
        }
      });
    },

    /**
     * Add event to the store and trigger notifications.
     */
    addEvent(ev: NormalizedEvent) {
      if (this.events.find(e => e.id === ev.id)) {
        return;
      }

      this.events.push(ev);
      this.recentEvents.unshift(ev);

      if (this.recentEvents.length > this.maxRecentEvents) {
        this.recentEvents = this.recentEvents.slice(0, this.maxRecentEvents);
      }

      this.processEventRules(ev);

      if (this.notificationSettings.enabled) {
        window.dispatchEvent(new CustomEvent('twitch-event-normalized', {
          detail: ev
        }));
      }

    },

    /**
     * Public method: feed raw EventSub payload into the system.
     */
    handleRawEvent(raw: any) {
      const ev = normalizeEvent(raw);

      this.addEvent(ev);
    },

    /**
     * Update notification settings.
     */
    updateNotificationSettings(settings: Partial<NotificationSettings>) {
      this.notificationSettings = { ...this.notificationSettings, ...settings };
    },

    /**
     * Clear old events (keep only recent ones).
     */
    clearOldEvents(olderThanHours = 24) {
      const cutoff = Date.now() - (olderThanHours * 60 * 60 * 1000);
      this.events = this.events.filter(event => event.ts > cutoff);
    },

    /**
     * Clear specific tag overlay triggers.
     */
    clearOverlayTriggers() {
      Object.keys(this.tags).forEach(key => {
        if (key.endsWith('_overlay')) {
          this.tags[key] = false;
        }
      });
    },

    /**
     * Export the current state as JSON.
     */
    exportState() {
      return JSON.stringify({
        tags: this.tags,
        events: this.events.slice(-20),
        settings: this.notificationSettings,
      });
    },

    /**
     * Get statistics for dashboard.
     */
    getStatistics() {
      const now = Date.now();
      const oneHour = 60 * 60 * 1000;
      const oneDay = 24 * oneHour;

      const recentEvents = this.events.filter(event => (now - event.ts) < oneHour);
      const todayEvents = this.events.filter(event => (now - event.ts) < oneDay);

      const stats = {
        total: this.events.length,
        lastHour: recentEvents.length,
        today: todayEvents.length,
        byType: {} as Record<string, { total: number; lastHour: number; today: number }>,
      };

      const eventTypes = [...new Set(this.events.map(e => e.type))];
      eventTypes.forEach(type => {
        const allOfType = this.events.filter(e => e.type === type);
        const recentOfType = recentEvents.filter(e => e.type === type);
        const todayOfType = todayEvents.filter(e => e.type === type);

        stats.byType[type] = {
          total: allOfType.length,
          lastHour: recentOfType.length,
          today: todayOfType.length,
        };
      });

      return stats;
    },
  }
});
