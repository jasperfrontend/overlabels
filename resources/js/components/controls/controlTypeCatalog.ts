import type { Component } from 'vue';
import { CalendarClock, Gauge, Hash, ListPlus, Sigma, Timer, ToggleLeft, Type } from '@lucide/vue';
import type { OverlayControl } from '@/types';

/**
 * Everything the "Add a control" picker needs to sell one control type before
 * the user commits to it: what it is, what it is good for, and a small demo of
 * what it actually looks like once it is on screen.
 *
 * This lives next to `controlPresets.ts` on purpose. That file is the catalog
 * of ready-made service controls; this one is the catalog of the eight kinds
 * you can build yourself. The picker renders both.
 *
 * Accent class strings are FULL LITERALS, never interpolated. Tailwind scans
 * source text, so `text-sky-500` has to appear verbatim or the class is never
 * generated. Same rule as `useEventColors.ts`.
 */

export interface ControlAccent {
  /** Icon tile: tinted background plus the icon color. */
  icon: string;
  /** Resting border color. */
  ring: string;
  /** Border color on hover, applied through the card's `group`. */
  ringHover: string;
  /** Border color when the card is the current choice, with no hover needed. */
  ringSelected: string;
  /** Focus ring, for keyboard selection. */
  ringFocus: string;
  /** Accent-colored text, for headings and the selected badge. */
  text: string;
}

export type ControlTypeDemo =
  | { kind: 'text'; value: string }
  | { kind: 'stat'; label: string; value: string }
  | { kind: 'counter'; label: string; value: number }
  | { kind: 'timer' }
  | { kind: 'datetime'; value: string }
  | { kind: 'boolean'; label: string }
  | { kind: 'formula'; expression: string; result: string }
  | { kind: 'pipe'; from: string; to: string };

export interface ControlTypeMeta {
  type: OverlayControl['type'];
  name: string;
  /** One line, shown on the card in the picker grid. */
  tagline: string;
  /**
   * A name someone would plausibly give this kind of control, used as the
   * placeholder in the form. The key placeholder is slugified from it at the
   * call site with the same function that derives the real key, so the pair
   * always demonstrates the actual derivation rather than a hand-written guess.
   */
  exampleName: string;
  /** The longer pitch, shown in the rail once the type is chosen. */
  blurb: string;
  /** Three concrete things streamers actually use this for. */
  goodFor: string[];
  icon: Component;
  accent: ControlAccent;
  demo: ControlTypeDemo;
}

export const CONTROL_TYPES: ControlTypeMeta[] = [
  {
    type: 'text',
    name: 'Text',
    tagline: 'Any words you want on screen, changed whenever you like.',
    exampleName: 'Now playing',
    blurb:
      'A free-form line of text. Type a new value in your dashboard and every overlay showing it updates straight away. No reload, no restarting the browser source.',
    goodFor: ['What you are playing right now', 'A shoutout to the last raider', 'The current chapter, quest or task'],
    icon: Type,
    accent: {
      icon: 'bg-sky-500/12 text-sky-600 dark:bg-sky-400/12 dark:text-sky-300',
      ring: 'border-sky-500/25 dark:border-sky-400/20',
      ringHover: 'hover:border-sky-500/60 dark:hover:border-sky-400/50',
      ringSelected: 'border-sky-500/60 dark:border-sky-400/50',
      ringFocus: 'focus-visible:ring-sky-500/40 dark:focus-visible:ring-sky-400/30',
      text: 'text-sky-600 dark:text-sky-300',
    },
    demo: { kind: 'text', value: 'Now playing: Hollow Knight' },
  },
  {
    type: 'number',
    name: 'Number',
    tagline: 'A single number, with limits if you want them.',
    exampleName: 'Sub goal',
    blurb:
      'A number you type in. Set a minimum, a maximum and a step so you cannot fat-finger it while you are live. Switch on random mode and it rolls a fresh value on a timer instead.',
    goodFor: ['A follower or sub goal', 'Your personal best, in seconds', 'A random value that keeps a scene alive'],
    icon: Gauge,
    accent: {
      icon: 'bg-indigo-500/12 text-indigo-600 dark:bg-indigo-400/12 dark:text-indigo-300',
      ring: 'border-indigo-500/25 dark:border-indigo-400/20',
      ringHover: 'hover:border-indigo-500/60 dark:hover:border-indigo-400/50',
      ringSelected: 'border-indigo-500/60 dark:border-indigo-400/50',
      ringFocus: 'focus-visible:ring-indigo-500/40 dark:focus-visible:ring-indigo-400/30',
      text: 'text-indigo-600 dark:text-indigo-300',
    },
    demo: { kind: 'stat', label: 'Sub goal', value: '250' },
  },
  {
    type: 'counter',
    name: 'Counter',
    tagline: 'One number, one click. Up, down, reset.',
    exampleName: 'Death counter',
    blurb:
      'A number built for using while you are live. Your dashboard gives it plus and minus buttons and a reset, so you are one click away from bumping it mid-sentence.',
    goodFor: ['Deaths, wins, attempts', 'Times you said the thing', 'Anything chat likes to keep score of'],
    icon: Hash,
    accent: {
      icon: 'bg-emerald-500/12 text-emerald-600 dark:bg-emerald-400/12 dark:text-emerald-300',
      ring: 'border-emerald-500/25 dark:border-emerald-400/20',
      ringHover: 'hover:border-emerald-500/60 dark:hover:border-emerald-400/50',
      ringSelected: 'border-emerald-500/60 dark:border-emerald-400/50',
      ringFocus: 'focus-visible:ring-emerald-500/40 dark:focus-visible:ring-emerald-400/30',
      text: 'text-emerald-600 dark:text-emerald-300',
    },
    demo: { kind: 'counter', label: 'Deaths', value: 12 },
  },
  {
    type: 'timer',
    name: 'Timer',
    tagline: 'Counts up, counts down, or counts to a moment.',
    exampleName: 'Subathon clock',
    blurb:
      'A clock you start, stop and reset from your dashboard. Count up from zero, count down from a duration you set, or count towards a date and time you pick.',
    goodFor: ['A subathon clock', 'A "back in five" break timer', 'Time left until your next big stream'],
    icon: Timer,
    accent: {
      icon: 'bg-amber-500/12 text-amber-600 dark:bg-amber-400/12 dark:text-amber-300',
      ring: 'border-amber-500/25 dark:border-amber-400/20',
      ringHover: 'hover:border-amber-500/60 dark:hover:border-amber-400/50',
      ringSelected: 'border-amber-500/60 dark:border-amber-400/50',
      ringFocus: 'focus-visible:ring-amber-500/40 dark:focus-visible:ring-amber-400/30',
      text: 'text-amber-600 dark:text-amber-300',
    },
    demo: { kind: 'timer' },
  },
  {
    type: 'datetime',
    name: 'Date and time',
    tagline: 'A fixed moment, formatted however you like.',
    exampleName: 'Next stream',
    blurb:
      'One date and time, stored once. Format it per tag with the pipe syntax, so the same value can read as "Sat 14 Jun" in one corner of your overlay and "14-06-2026 20:00" in another.',
    goodFor: ['When your next stream starts', 'A release or launch date', 'The day the channel turned one'],
    icon: CalendarClock,
    accent: {
      icon: 'bg-rose-500/12 text-rose-600 dark:bg-rose-400/12 dark:text-rose-300',
      ring: 'border-rose-500/25 dark:border-rose-400/20',
      ringHover: 'hover:border-rose-500/60 dark:hover:border-rose-400/50',
      ringSelected: 'border-rose-500/60 dark:border-rose-400/50',
      ringFocus: 'focus-visible:ring-rose-500/40 dark:focus-visible:ring-rose-400/30',
      text: 'text-rose-600 dark:text-rose-300',
    },
    demo: { kind: 'datetime', value: 'Sat 14 Jun, 20:00' },
  },
  {
    type: 'boolean',
    name: 'Switch',
    tagline: 'On or off, for showing and hiding parts of your overlay.',
    exampleName: 'Be right back',
    blurb:
      'A single switch. On its own it reads as on or off, but its real job is driving conditional blocks: wrap anything in an if block and this switch shows or hides it live.',
    goodFor: ['A "be right back" banner', 'Spoiler mode during a boss fight', 'Hiding your donation bar on a chill stream'],
    icon: ToggleLeft,
    accent: {
      icon: 'bg-violet-500/12 text-violet-600 dark:bg-violet-400/12 dark:text-violet-300',
      ring: 'border-violet-500/25 dark:border-violet-400/20',
      ringHover: 'hover:border-violet-500/60 dark:hover:border-violet-400/50',
      ringSelected: 'border-violet-500/60 dark:border-violet-400/50',
      ringFocus: 'focus-visible:ring-violet-500/40 dark:focus-visible:ring-violet-400/30',
      text: 'text-violet-600 dark:text-violet-300',
    },
    demo: { kind: 'boolean', label: 'Be right back' },
  },
  {
    type: 'expression',
    name: 'Expression',
    tagline: 'A formula over your other controls and your live Twitch data.',
    exampleName: 'Win rate',
    blurb:
      'Write a formula once and it recalculates itself the moment anything it references changes. Reach any other control plus every live Twitch value, with functions for maths, time, and picking the largest or most recent of a set.',
    goodFor: ['Win rate as a percentage', 'How far you still are from a goal', 'Speed, pace or distance from GPS'],
    icon: Sigma,
    accent: {
      icon: 'bg-fuchsia-500/12 text-fuchsia-600 dark:bg-fuchsia-400/12 dark:text-fuchsia-300',
      ring: 'border-fuchsia-500/25 dark:border-fuchsia-400/20',
      ringHover: 'hover:border-fuchsia-500/60 dark:hover:border-fuchsia-400/50',
      ringSelected: 'border-fuchsia-500/60 dark:border-fuchsia-400/50',
      ringFocus: 'focus-visible:ring-fuchsia-500/40 dark:focus-visible:ring-fuchsia-400/30',
      text: 'text-fuchsia-600 dark:text-fuchsia-300',
    },
    demo: { kind: 'formula', expression: 'c.wins / (c.wins + c.losses) * 100', result: '68' },
  },
  {
    type: 'list_writer',
    name: 'List writer',
    tagline: 'Quietly logs every new value into one of your Lists.',
    exampleName: 'Supporter log',
    blurb:
      'Point it at another control and at a List. Every time that control changes, the new value is appended to the List. You never touch this one again, it just keeps the history for you.',
    goodFor: ['A running roll of everyone who tipped', 'Every song that played tonight', 'A log of every raid you got'],
    icon: ListPlus,
    accent: {
      icon: 'bg-teal-500/12 text-teal-600 dark:bg-teal-400/12 dark:text-teal-300',
      ring: 'border-teal-500/25 dark:border-teal-400/20',
      ringHover: 'hover:border-teal-500/60 dark:hover:border-teal-400/50',
      ringSelected: 'border-teal-500/60 dark:border-teal-400/50',
      ringFocus: 'focus-visible:ring-teal-500/40 dark:focus-visible:ring-teal-400/30',
      text: 'text-teal-600 dark:text-teal-300',
    },
    demo: { kind: 'pipe', from: 'latest_donor_name', to: 'Supporters tonight' },
  },
];

const BY_TYPE = new Map(CONTROL_TYPES.map((meta) => [meta.type, meta]));

export function controlTypeMeta(type: OverlayControl['type']): ControlTypeMeta {
  // Every member of the union is in the catalog, so the fallback is only a
  // guard against a type added to the DB before it is added here.
  return BY_TYPE.get(type) ?? CONTROL_TYPES[0];
}

/**
 * Neutral accent for the ready-made service controls. They are not one of the
 * eight buildable types, so they get their own identity rather than borrowing
 * whichever type the preset happens to be.
 */
export const SERVICE_ACCENT: ControlAccent = {
  icon: 'bg-violet-500/12 text-violet-600 dark:bg-violet-400/12 dark:text-violet-300',
  ring: 'border-violet-500/25 dark:border-violet-400/20',
  ringHover: 'hover:border-violet-500/60 dark:hover:border-violet-400/50',
  ringSelected: 'border-violet-500/60 dark:border-violet-400/50',
  ringFocus: 'focus-visible:ring-violet-500/40 dark:focus-visible:ring-violet-400/30',
  text: 'text-violet-600 dark:text-violet-300',
};

export interface PresetGroupMeta {
  source: string;
  label: string;
  /** One line on what this group is, shown under the group heading. */
  blurb: string;
  /**
   * Connected-service key this group needs, or null when the group needs no
   * integration at all (Twitch is always there, alerts is a system namespace).
   */
  requiresService: string | null;
}

export const PRESET_GROUPS: PresetGroupMeta[] = [
  {
    source: 'twitch',
    label: 'Twitch',
    blurb: 'Counters that fill themselves from your channel, and reset when you go live.',
    requiresService: null,
  },
  {
    source: 'alerts',
    label: 'Overlabels alerts',
    blurb: 'The alert state your overlay can read, so it can react to it.',
    requiresService: null,
  },
  { source: 'kofi', label: 'Ko-fi', blurb: 'Tips and supporters, straight from Ko-fi.', requiresService: 'kofi' },
  {
    source: 'streamlabs',
    label: 'Streamlabs',
    blurb: 'Donations as they land in Streamlabs.',
    requiresService: 'streamlabs',
  },
  {
    source: 'fourthwall',
    label: 'Fourthwall',
    blurb: 'Donations and support from your Fourthwall shop.',
    requiresService: 'fourthwall',
  },
  {
    source: 'bmac',
    label: 'Buy Me a Coffee',
    blurb: 'Supporters, memberships and one-off coffees.',
    requiresService: 'bmac',
  },
  {
    source: 'throne',
    label: 'Throne',
    blurb: 'Gifts from your Throne wishlist, including the item itself.',
    requiresService: 'throne',
  },
  {
    source: 'gps',
    label: 'Overlabels GPS',
    blurb: 'Live telemetry from the Overlabels mobile app.',
    requiresService: 'gps',
  },
];
