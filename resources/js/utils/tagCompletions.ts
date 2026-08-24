/**
 * Autocomplete for Overlabels tags inside the template editor.
 *
 * The pure part is `suggest()`: hand it the document text up to the cursor plus
 * the data the page already has (the static catalogue, this account's controls
 * and Lists, the template type) and it answers with candidate completions and
 * the offset they replace from. It knows nothing about CodeMirror, which is
 * what keeps it testable under Vitest. `overlabelsCompletion()` is the thin
 * CodeMirror wrapper around it.
 *
 * Context is decided from the text before the cursor on the current line, in
 * this order:
 *
 *   [[[tag|          -> formatters
 *   [[[foreach:      -> iterables, applied as `iterable as alias`
 *   [[[if: / elseif: -> every value tag, no block keywords
 *   [[[              -> every value tag plus the block keywords
 *
 * A foreach alias in scope (an opened, not yet closed loop above the cursor)
 * contributes `alias.field` options for that iterable, so `[[[sub.` inside a
 * subscribers loop offers `sub.user_name`.
 *
 * The tag shape (open/close brackets, key characters) is read from the shared
 * DSL spec, never hand-rolled - see resources/dsl/dsl.json.
 */

import { DSL, blockTokenPattern } from '@/utils/dsl';
import { serviceLabel } from '@/utils/services';
import { snippetCompletion, type Completion, type CompletionContext, type CompletionResult, type CompletionSource } from '@codemirror/autocomplete';
import { EditorState } from '@codemirror/state';
import type { EditorView } from '@codemirror/view';

export interface CatalogueTag {
  tag_name: string;
  description: string;
  data_type?: string;
  /** Category display name, used as the completion section header. */
  category: string;
}

export interface CompletionControl {
  key: string;
  label?: string | null;
  source?: string | null;
  type?: string;
}

export interface CompletionList {
  slug: string;
  label?: string | null;
}

export interface CompletionData {
  tags: CatalogueTag[];
  /** `event.*` tags; only offered on alert templates. */
  eventTags: string[];
  controls: CompletionControl[];
  lists: CompletionList[];
  templateType: string | null;
}

/**
 * One candidate. `closes` says whether accepting it should complete the tag
 * (insert or step over `]]]`) or leave the cursor inside the brackets because
 * more is expected after it (`if:`, `foreach:`).
 */
export interface Suggestion {
  label: string;
  apply?: string;
  detail?: string;
  info?: string;
  section: string;
  type: string;
  closes: boolean;
  boost?: number;
}

export interface Suggestions {
  /** Absolute offset in the text handed to suggest() that the options replace from. */
  from: number;
  options: Suggestion[];
  kind: 'tag' | 'formatter' | 'iterable';
}

interface ForeachScope {
  iterable: string;
  alias: string;
}

const lex = DSL.lexical;
const KEY_CLASS = `[${lex.keyRest}]`;

const PIPE_CONTEXT = new RegExp(`${lex.open}[${lex.keyStart}]${KEY_CLASS}*${lex.pipeOperator}([a-z]*)$`, 'i');
const FOREACH_CONTEXT = new RegExp(`${lex.open}foreach:\\s*(${KEY_CLASS}*)$`);
const TAG_CONTEXT = new RegExp(`${lex.open}(if:|elseif:)?(${KEY_CLASS}*)$`);
const FOREACH_EXPRESSION = new RegExp(`^\\s*(${KEY_CLASS}+)\\s+as\\s+([a-zA-Z_][a-zA-Z0-9_]*)\\s*$`);

/** Re-filter client-side while the typed token still looks like a key. */
export const TAG_VALID_FOR = new RegExp(`^${KEY_CLASS}*$`);
export const FORMATTER_VALID_FOR = /^[a-z]*$/i;

const BLOCK_KEYWORDS: Array<{ label: string; closes: boolean; info: string }> = [
  { label: 'if:', closes: false, info: 'Start a conditional block. Close it with [[[endif]]].' },
  { label: 'elseif:', closes: false, info: 'Another condition, tested when the ones above were false.' },
  { label: 'else', closes: true, info: 'Fallback branch of a conditional block.' },
  { label: 'endif', closes: true, info: 'Close a conditional block.' },
  { label: 'foreach:', closes: false, info: 'Loop over a list. Close it with [[[endforeach]]].' },
  { label: 'endforeach', closes: true, info: 'Close a foreach block.' },
];

const LOOP_TAGS: Array<{ label: string; info: string }> = [
  { label: 'loop.index', info: 'Position of the current item, starting at 1.' },
  { label: 'loop.first', info: '1 on the first item, empty otherwise.' },
  { label: 'loop.last', info: '1 on the last item, empty otherwise.' },
  { label: 'loop.count', info: 'Number of items in the loop.' },
  { label: 'raw', info: 'The current item as JSON, for finding out which fields it has.' },
];

/**
 * Item fields per iterable. Subscribers, followers, followed channels and
 * goals are the Helix row shapes plus the `<prefix>_profile_image_url`
 * decoration TwitchApiService adds. Chat mirrors chatSlots.ts. Event lists
 * mirror the EventSub payload shapes the mapper flattens by index.
 */
const ITEM_FIELDS: Record<string, string[]> = {
  subscribers: [
    'user_name',
    'user_login',
    'user_id',
    'user_profile_image_url',
    'tier',
    'plan_name',
    'is_gift',
    'gifter_name',
    'gifter_login',
    'gifter_id',
    'gifter_profile_image_url',
  ],
  channel_followers: ['user_name', 'user_login', 'user_id', 'user_profile_image_url', 'followed_at'],
  followed_channels: ['broadcaster_name', 'broadcaster_login', 'broadcaster_id', 'broadcaster_profile_image_url', 'followed_at'],
  goals: ['type', 'description', 'current_amount', 'target_amount', 'created_at', 'id'],
  chat: [
    'author',
    'login',
    'text',
    'html',
    'color',
    'badges',
    'badge_images',
    'at',
    'id',
    'mod',
    'sub',
    'vip',
    'broadcaster',
    'first',
    'source_channel',
  ],
  'event.choices': ['title', 'votes', 'channel_points_votes', 'bits_votes', 'id'],
  'event.outcomes': ['title', 'color', 'users', 'channel_points', 'id'],
  'event.top_contributions': ['user_name', 'user_login', 'user_id', 'type', 'total'],
  'event.winners': ['user_name', 'user_login', 'user_id'],
};

const LIST_ITEM_FIELDS = ['value', 'id', 'added_at', 'label', 'weight', 'color'];

/** Iterables everyone has, with the alias the completion proposes. */
const ITERABLES: Array<{ label: string; alias: string; info: string; alertOnly?: boolean }> = [
  { label: 'subscribers', alias: 'sub', info: 'Your most recent subscribers, newest first. Capped per your foreach settings.' },
  { label: 'channel_followers', alias: 'follower', info: 'Your most recent followers, newest first.' },
  { label: 'followed_channels', alias: 'channel', info: 'Channels you follow.' },
  { label: 'goals', alias: 'goal', info: 'Your active channel goals.' },
  { label: 'chat', alias: 'msg', info: 'Live chat, oldest first. Opens a direct connection to Twitch chat.' },
  { label: 'event.choices', alias: 'choice', info: 'Poll choices from the event payload.', alertOnly: true },
  { label: 'event.outcomes', alias: 'outcome', info: 'Prediction outcomes from the event payload.', alertOnly: true },
  { label: 'event.top_contributions', alias: 'contribution', info: 'Hype train top contributions.', alertOnly: true },
];

const LIST_SUFFIXES: Array<{ suffix: string; info: string }> = [
  { suffix: '', info: 'The item values as a JSON array.' },
  { suffix: ':count', info: 'How many items the List holds.' },
  { suffix: ':first', info: 'The oldest item.' },
  { suffix: ':last', info: 'The most recent item.' },
  { suffix: ':random', info: 'A random item.' },
  { suffix: ':empty', info: '1 when the List is empty, 0 otherwise.' },
  { suffix: ':json', info: 'The full item objects as JSON.' },
];

export function controlTagKey(control: CompletionControl): string {
  return control.source ? `c:${control.source}:${control.key}` : `c:${control.key}`;
}

function isAlert(data: CompletionData): boolean {
  return data.templateType === 'alert';
}

/**
 * Foreach loops opened above the cursor and not yet closed, innermost last.
 * Reads the same block-token pattern the renderer lexes with.
 */
export function foreachScopes(docBefore: string): ForeachScope[] {
  const stack: ForeachScope[] = [];
  const pattern = blockTokenPattern('g');
  let match: RegExpExecArray | null;

  while ((match = pattern.exec(docBefore)) !== null) {
    const token = match[1];
    if (token === 'endforeach') {
      stack.pop();
      continue;
    }
    if (match[4] === undefined) continue;

    const expr = FOREACH_EXPRESSION.exec(match[4]);
    if (expr) stack.push({ iterable: expr[1], alias: expr[2] });
  }

  return stack;
}

function fieldsFor(iterable: string): string[] {
  if (iterable.startsWith('c:list:')) return LIST_ITEM_FIELDS;
  return ITEM_FIELDS[iterable] ?? [];
}

function aliasOptions(scopes: ForeachScope[]): Suggestion[] {
  const options: Suggestion[] = [];

  for (const scope of scopes) {
    const section = `Loop: ${scope.alias}`;
    if (scope.iterable.startsWith('c:list:')) {
      options.push({ label: scope.alias, section, type: 'variable', closes: true, info: 'The item value.', boost: 2 });
    }
    for (const field of fieldsFor(scope.iterable)) {
      options.push({ label: `${scope.alias}.${field}`, section, type: 'property', closes: true, boost: 2 });
    }
  }

  return options;
}

function valueOptions(data: CompletionData, scopes: ForeachScope[]): Suggestion[] {
  const options: Suggestion[] = [];

  for (const control of data.controls) {
    options.push({
      label: controlTagKey(control),
      detail: control.label ?? undefined,
      info: control.type ? `${control.type} control` : undefined,
      section: control.source ? `${serviceLabel(control.source)} controls` : 'Controls',
      type: 'variable',
      closes: true,
      boost: 1,
    });
  }

  for (const list of data.lists) {
    for (const { suffix, info } of LIST_SUFFIXES) {
      options.push({
        label: `c:list:${list.slug}${suffix}`,
        detail: list.label ?? undefined,
        info,
        section: 'Lists',
        type: 'variable',
        closes: true,
      });
    }
  }

  options.push(...aliasOptions(scopes));

  if (scopes.length > 0) {
    for (const tag of LOOP_TAGS) {
      options.push({ label: tag.label, info: tag.info, section: 'Loop', type: 'keyword', closes: true });
    }
  }

  for (const tag of data.tags) {
    options.push({
      label: tag.tag_name,
      detail: tag.data_type,
      info: tag.description,
      section: tag.category,
      type: 'variable',
      closes: true,
    });
  }

  if (isAlert(data)) {
    for (const tag of data.eventTags) {
      options.push({ label: tag, section: 'Event', type: 'variable', closes: true });
    }
  }

  return options;
}

function formatterOptions(): Suggestion[] {
  return Object.entries(DSL.formatters).map(([name, spec]) => ({
    label: name,
    detail: 'argHint' in spec ? spec.argHint : undefined,
    info: spec.description,
    section: 'Formatters',
    type: 'function',
    closes: true,
  }));
}

function iterableOptions(data: CompletionData): Suggestion[] {
  const options: Suggestion[] = ITERABLES.filter((it) => !it.alertOnly || isAlert(data)).map((it) => ({
    label: it.label,
    apply: `${it.label} as ${it.alias}`,
    detail: `as ${it.alias}`,
    info: it.info,
    section: 'Iterables',
    type: 'variable',
    closes: true,
  }));

  for (const list of data.lists) {
    options.push({
      label: `c:list:${list.slug}`,
      apply: `c:list:${list.slug} as item`,
      detail: list.label ?? 'as item',
      info: 'Each item of this List.',
      section: 'Lists',
      type: 'variable',
      closes: true,
    });
  }

  return options;
}

/**
 * Candidates for the cursor at the end of `docBefore`, or null when the cursor
 * is not inside an opening `[[[`.
 */
export function suggest(docBefore: string, data: CompletionData): Suggestions | null {
  const lineStart = docBefore.lastIndexOf('\n') + 1;
  const line = docBefore.slice(lineStart);

  const pipe = PIPE_CONTEXT.exec(line);
  if (pipe) {
    return { from: lineStart + pipe.index + pipe[0].length - pipe[1].length, options: formatterOptions(), kind: 'formatter' };
  }

  const foreach = FOREACH_CONTEXT.exec(line);
  if (foreach) {
    return { from: lineStart + foreach.index + foreach[0].length - foreach[1].length, options: iterableOptions(data), kind: 'iterable' };
  }

  const tag = TAG_CONTEXT.exec(line);
  if (!tag) return null;

  const scopes = foreachScopes(docBefore);
  const options = valueOptions(data, scopes);

  if (tag[1] === undefined) {
    for (const keyword of BLOCK_KEYWORDS) {
      options.push({ label: keyword.label, info: keyword.info, section: 'Blocks', type: 'keyword', closes: keyword.closes });
    }
  }

  return { from: lineStart + tag.index + tag[0].length - tag[2].length, options, kind: 'tag' };
}

/**
 * Bang snippets: `!chat` expands to a whole working block rather than one tag.
 *
 * Templates use CodeMirror's snippet syntax - `${name}` is a field the cursor
 * lands in, Tab moves to the next one, and two fields with the same name are
 * linked so typing in one updates the other. Everything else is inserted
 * verbatim, indented to the current line.
 */
export interface BangSnippet {
  label: string;
  template: string;
  info: string;
}

const BASE_BANGS: BangSnippet[] = [
  {
    label: '!chat',
    info: "A live chat feed: one line per message, name in the chatter's colour, emotes rendered.",
    template: [
      '[[[foreach:chat as msg]]]',
      '  <div class="chat-line"><span style="color: [[[msg.color]]]">[[[msg.author]]]</span>: [[[msg.html]]]</div>',
      '[[[endforeach]]]',
    ].join('\n'),
  },
  {
    label: '!subs',
    info: 'Your most recent subscribers, one row each.',
    template: ['[[[foreach:subscribers as sub]]]', '  <div class="sub-row">[[[sub.user_name]]]</div>', '[[[endforeach]]]'].join('\n'),
  },
  {
    label: '!followers',
    info: 'Your most recent followers, one row each.',
    template: ['[[[foreach:channel_followers as follower]]]', '  <div class="follower-row">[[[follower.user_name]]]</div>', '[[[endforeach]]]'].join(
      '\n',
    ),
  },
  {
    label: '!goals',
    info: 'Your active channel goals with their progress.',
    template: [
      '[[[foreach:goals as goal]]]',
      '  <div class="goal-row">[[[goal.description]]]: [[[goal.current_amount]]] / [[[goal.target_amount]]]</div>',
      '[[[endforeach]]]',
    ].join('\n'),
  },
  {
    label: '!followed',
    info: 'The channels you follow, one row each.',
    template: [
      '[[[foreach:followed_channels as channel]]]',
      '  <div class="channel-row">[[[channel.broadcaster_name]]]</div>',
      '[[[endforeach]]]',
    ].join('\n'),
  },
  {
    label: '!if',
    info: 'A conditional block. Fill in the condition, Tab to the body.',
    template: ['[[[if:${condition}]]]', '  ${}', '[[[endif]]]'].join('\n'),
  },
  {
    label: '!ifelse',
    info: 'A conditional block with a fallback branch.',
    template: ['[[[if:${condition}]]]', '  ${}', '[[[else]]]', '  ${}', '[[[endif]]]'].join('\n'),
  },
  {
    label: '!foreach',
    info: 'An empty loop. The alias is linked: rename it once and both places follow.',
    template: ['[[[foreach:${subscribers} as ${item}]]]', '  [[[${item}.${user_name}]]]', '[[[endforeach]]]'].join('\n'),
  },
];

/**
 * Loops over an EventSub payload. Only an alert ever receives one, so these
 * are offered on alert templates alone - same gate as the `event.*` tags.
 */
const ALERT_BANGS: BangSnippet[] = [
  {
    label: '!poll',
    info: 'Poll choices with their vote counts, from a poll event.',
    template: [
      '[[[foreach:event.choices as choice]]]',
      '  <div class="poll-choice">[[[choice.title]]]: [[[choice.votes]]] votes</div>',
      '[[[endforeach]]]',
    ].join('\n'),
  },
  {
    label: '!prediction',
    info: 'Prediction outcomes with predictors and points wagered, from a prediction event.',
    template: [
      '[[[foreach:event.outcomes as outcome]]]',
      '  <div class="prediction-outcome [[[outcome.color]]]">[[[outcome.title]]]: [[[outcome.users]]] predictors, [[[outcome.channel_points]]] points</div>',
      '[[[endforeach]]]',
    ].join('\n'),
  },
  {
    label: '!hypetrain',
    info: 'The top hype train contributors, from a hype train event.',
    template: [
      '[[[foreach:event.top_contributions as contribution]]]',
      '  <div class="hype-contributor">[[[contribution.user_name]]]: [[[contribution.total]]] [[[contribution.type]]]</div>',
      '[[[endforeach]]]',
    ].join('\n'),
  },
];

/**
 * The donation services whose provisioned controls share the same six keys.
 * A bang exists for a service only once its controls are present, which is
 * what connecting it does - so `!kofi` shows up exactly when it would work.
 */
const DONATION_SOURCES = ['kofi', 'streamlabs', 'fourthwall', 'bmac', 'throne'];

export function bangSnippets(data: CompletionData): BangSnippet[] {
  const sources = new Set(data.controls.map((c) => c.source).filter((s): s is string => !!s));
  const donation = DONATION_SOURCES.filter((s) => sources.has(s)).map((source) => ({
    label: `!${source}`,
    info: `Latest ${serviceLabel(source)} donation: who, how much, and their message.`,
    template: [
      `<div class="donation ${source}">`,
      `  <strong>[[[c:${source}:latest_donor_name]]]</strong> - [[[c:${source}:latest_donation_amount]]] [[[c:${source}:latest_donation_currency]]]`,
      `  <p>[[[c:${source}:latest_donation_message]]]</p>`,
      '</div>',
    ].join('\n'),
  }));

  return [...BASE_BANGS, ...(isAlert(data) ? ALERT_BANGS : []), ...donation];
}

const BANG_CONTEXT = /![a-z]*$/;

/**
 * Where a bang is being typed on the line before the cursor, or null.
 *
 * Needs at least one letter after the `!` unless the user asked (Ctrl+Space):
 * a bare `!` ends plenty of ordinary sentences, and popping a list on every
 * exclamation mark would make the snippets feel like a bug.
 */
export function bangPrefix(lineBefore: string, explicit = false): { from: number; text: string } | null {
  const match = BANG_CONTEXT.exec(lineBefore);
  if (!match) return null;
  if (!explicit && match[0].length < 2) return null;

  return { from: match.index, text: match[0] };
}

/**
 * Accepting a completion inserts its text and, when it completes a tag, either
 * steps over a `]]]` that closeBrackets() already inserted or adds one. The
 * cursor ends up after the closer either way, ready for what follows the tag.
 */
function applySuggestion(suggestion: Suggestion) {
  return (view: EditorView, _completion: Completion, from: number, to: number): void => {
    const text = suggestion.apply ?? suggestion.label;
    const closer = lex.close.replace(/\\/g, '');
    const alreadyClosed = view.state.sliceDoc(to, to + closer.length) === closer;

    let insert = text;
    let cursor = from + text.length;
    if (suggestion.closes && alreadyClosed) {
      cursor += closer.length;
    } else if (suggestion.closes) {
      insert = text + closer;
      cursor = from + insert.length;
    }

    view.dispatch({
      changes: { from, to, insert },
      selection: { anchor: cursor },
      userEvent: 'input.complete',
    });
  };
}

function toCompletion(suggestion: Suggestion): Completion {
  return {
    label: suggestion.label,
    detail: suggestion.detail,
    info: suggestion.info,
    section: suggestion.section,
    type: suggestion.type,
    boost: suggestion.boost,
    apply: applySuggestion(suggestion),
  };
}

/**
 * The CodeMirror extension. `getData` is read on every request so reactive
 * page state (controls added on the Controls tab, the catalogue arriving) is
 * picked up without reconfiguring the editor.
 *
 * Registered as language data rather than `autocompletion({ override })`, so
 * the HTML and CSS completions the editor already ships keep working and this
 * source applies at any position - HEAD, BODY, CSS, and inside a <style> block.
 */
export function overlabelsCompletion(getData: () => CompletionData) {
  const source: CompletionSource = (context: CompletionContext): CompletionResult | null => {
    const result = suggest(context.state.sliceDoc(0, context.pos), getData());
    if (!result) return null;

    return {
      from: result.from,
      options: result.options.map(toCompletion),
      validFor: result.kind === 'formatter' ? FORMATTER_VALID_FOR : TAG_VALID_FOR,
    };
  };

  const bangSource: CompletionSource = (context: CompletionContext): CompletionResult | null => {
    const line = context.state.doc.lineAt(context.pos);
    const bang = bangPrefix(context.state.sliceDoc(line.from, context.pos), context.explicit);
    if (!bang) return null;

    return {
      from: line.from + bang.from,
      options: bangSnippets(getData()).map((snippet) =>
        snippetCompletion(snippet.template, { label: snippet.label, detail: 'snippet', info: snippet.info, type: 'snippet' }),
      ),
      validFor: /^![a-z]*$/,
    };
  };

  return EditorState.languageData.of(() => [{ autocomplete: source }, { autocomplete: bangSource }]);
}
