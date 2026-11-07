import { describe, expect, it } from 'vitest';
import { redactedHtml, redactMessage, toRedactCommand } from './chatRedact';
import type { ChatMessage } from './ircParser';

function message(text: string, overrides: Partial<ChatMessage> = {}): ChatMessage {
  return {
    id: 'm1',
    userId: '1',
    login: 'viewer',
    author: 'Viewer',
    text,
    color: '',
    badges: '',
    badgeVersions: [],
    at: 0,
    isMod: false,
    isSubscriber: false,
    isVip: false,
    isBroadcaster: false,
    isFirstMessage: false,
    sourceRoomId: '',
    emotes: [],
    ...overrides,
  };
}

describe('toRedactCommand', () => {
  it('takes a valid word, lowercased', () => {
    expect(toRedactCommand('hide')).toBe('hide');
    expect(toRedactCommand(' Hide ')).toBe('hide');
  });

  it('means off for anything unusable', () => {
    expect(toRedactCommand(null)).toBe('');
    expect(toRedactCommand(undefined)).toBe('');
    expect(toRedactCommand('')).toBe('');
    expect(toRedactCommand('two words')).toBe('');
    expect(toRedactCommand(42)).toBe('');
  });
});

describe('redactMessage', () => {
  it('strips the command and marks the message redacted', () => {
    const result = redactMessage(message('!hide my secret'), 'hide');

    expect(result.text).toBe('my secret');
    expect(result.redacted).toBe(true);
    expect(result.id).toBe('m1');
  });

  it('matches the command in any case', () => {
    expect(redactMessage(message('!HIDE my secret'), 'hide').redacted).toBe(true);
  });

  it('drops emote positions, which pointed into the unstripped text', () => {
    const result = redactMessage(message('!hide Kappa', { emotes: [{ begin: 6, end: 10, id: '25' }] }), 'hide');

    expect(result.emotes).toEqual([]);
  });

  it('leaves every other message as the same object', () => {
    const plain = message('hello');
    expect(redactMessage(plain, 'hide')).toBe(plain);

    // A longer word is a different command.
    const longer = message('!hidex my secret');
    expect(redactMessage(longer, 'hide')).toBe(longer);

    // Nothing to hide.
    const bare = message('!hide');
    expect(redactMessage(bare, 'hide')).toBe(bare);
    const spaces = message('!hide   ');
    expect(redactMessage(spaces, 'hide')).toBe(spaces);
  });

  it('does nothing while the feature is off', () => {
    const msg = message('!hide my secret');
    expect(redactMessage(msg, '')).toBe(msg);
  });

  it('uses the word the streamer picked', () => {
    expect(redactMessage(message('!shh quiet'), 'shh').text).toBe('quiet');
    expect(redactMessage(message('!hide quiet'), 'shh').redacted).toBeUndefined();
  });
});

describe('redactedHtml', () => {
  it('wraps the text in the redacted span', () => {
    expect(redactedHtml('my secret')).toBe('<span class="ol-redacted">my secret</span>');
  });

  it('escapes chatter text, so the span is the only markup', () => {
    expect(redactedHtml('<b>"hi"</b> & \'x\'')).toBe('<span class="ol-redacted">&lt;b&gt;&quot;hi&quot;&lt;/b&gt; &amp; &#39;x&#39;</span>');
  });

  it('keeps an emote code as letters, so the font draws it as a bar', () => {
    expect(redactedHtml('KEKW')).toBe('<span class="ol-redacted">KEKW</span>');
  });

  it('turns anything the font cannot draw into x, so no fallback face shows it', () => {
    // Emoji (one ZWJ family = one grapheme), Cyrillic, Japanese.
    expect(redactedHtml('hi 👨‍👩‍👧 привет 日本')).toBe('<span class="ol-redacted">hi x xxxxxx xx</span>');
  });

  it('keeps Latin letters with accents, which the font covers', () => {
    expect(redactedHtml('café ñ')).toBe('<span class="ol-redacted">café ñ</span>');
  });
});
