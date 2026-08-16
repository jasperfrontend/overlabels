import { describe, expect, it } from 'vitest';
import { renderTemplateSource } from './renderTemplate';

/**
 * The single-pass invariant.
 *
 * Overlay data is not trustworthy. Chat writes into it (a `!enter` list
 * appender stores whatever the chatter typed), donors write into it (donation
 * messages, donor names), and every one of those strings is eventually
 * substituted into a template. The rule that keeps that safe is: a value
 * substituted by the renderer is DATA and is never re-read as template source.
 *
 * `renderTemplateSource` runs two passes - `processTemplate` resolves
 * conditional/foreach blocks, then `replaceTagsWithFormatting` does the single
 * tag substitution. tagParser.ts documents the invariant for pass 2, and pass 2
 * honours it because one `String.replace` never re-scans its own output.
 *
 * The gap this file exists to guard is the boundary BETWEEN the passes: a
 * foreach body has its scoped `alias.*` tokens substituted during pass 1, and
 * pass 2 then runs over the result. Without defusing, a `[[[...]]]` sequence
 * that arrived as data in pass 1 is indistinguishable from authored template
 * source by the time pass 2 sees it.
 *
 * That mattered more than "the template leaks a tag it already uses":
 * OverlayTemplateController merges the user's controls and lists into the
 * render payload wholesale (only the Twitch data is allowlisted), so the reach
 * of a pass-2 resolution is every control value and every list on the account.
 */

const SECRET_TAG = 'c:kofi:total_received';
const SECRET_VALUE = 'LEAKED-1234.56';
const LOCALE = 'en-US';

/** Payload shaped like a real render: the secret is present but unreferenced. */
function dataWith(extra: Record<string, unknown>): Record<string, unknown> {
  return { [SECRET_TAG]: SECRET_VALUE, ...extra };
}

describe('renderTemplateSource / single-pass invariant', () => {
  it('does not resolve a tag that arrives inside a plain tag value', () => {
    const out = renderTemplateSource('[[[c:latest_donor_name]]]', dataWith({ 'c:latest_donor_name': `[[[${SECRET_TAG}]]]` }), LOCALE);

    expect(out).not.toContain(SECRET_VALUE);
  });

  it('does not resolve a tag that arrives inside a foreach item value', () => {
    // The live path: ListAppendService writes raw chatter text into
    // option_sets.items, and the renderer flattens them to c:list:<slug>.N.
    const out = renderTemplateSource(
      '[[[foreach:c:list:raffle as item]]][[[item]]][[[endforeach]]]',
      dataWith({ 'c:list:raffle.0': `[[[${SECRET_TAG}]]]`, 'c:list:raffle.count': 1 }),
      LOCALE,
    );

    expect(out).not.toContain(SECRET_VALUE);
    expect(out).toContain('&#91;&#91;&#91;');
  });

  it('does not resolve a tag that arrives inside a foreach sub-field', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.text]]][[[endforeach]]]',
      dataWith({ 'chat.0.text': `[[[${SECRET_TAG}]]]`, 'chat.0.author': 'attacker', 'chat.count': 1 }),
      LOCALE,
    );

    expect(out).not.toContain(SECRET_VALUE);
  });

  it('does not resolve a tag split across two adjacent scoped tags', () => {
    // Defusing only values that already contain `[[[` would be defeatable
    // here: neither half is a tag on its own, but one attacker controls
    // both fields of the same item and they concatenate into one.
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.a]]][[[msg.b]]][[[endforeach]]]',
      dataWith({ 'chat.0.a': '[', 'chat.0.b': `[[${SECRET_TAG}]]]`, 'chat.count': 1 }),
      LOCALE,
    );

    expect(out).not.toContain(SECRET_VALUE);
  });

  it('holds for the CSS sink, where values are not HTML-encoded', () => {
    // compiledCss runs the same two passes with encode=false, so the pass
    // boundary is identical. Entity-encoding is skipped there; bracket
    // defusing must not be.
    const out = renderTemplateSource(
      '[[[foreach:c:list:themes as t]]].row{content:"[[[t]]]"}[[[endforeach]]]',
      dataWith({ 'c:list:themes.0': `[[[${SECRET_TAG}]]]`, 'c:list:themes.count': 1 }),
      LOCALE,
      false,
    );

    expect(out).not.toContain(SECRET_VALUE);
  });

  it('holds through a nested foreach', () => {
    const out = renderTemplateSource(
      '[[[foreach:rooms as room]]][[[foreach:chat as msg]]][[[msg.text]]][[[endforeach]]][[[endforeach]]]',
      dataWith({
        'rooms.0': 'main',
        'rooms.count': 1,
        'chat.0.text': `[[[${SECRET_TAG}]]]`,
        'chat.count': 1,
      }),
      LOCALE,
    );

    expect(out).not.toContain(SECRET_VALUE);
  });

  it('keeps [[[raw]]] dumps inert', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[raw]]][[[endforeach]]]',
      dataWith({ 'chat.0.text': `[[[${SECRET_TAG}]]]`, 'chat.count': 1 }),
      LOCALE,
    );

    expect(out).not.toContain(SECRET_VALUE);
  });

  it('still HTML-escapes markup arriving in a foreach value', () => {
    // The handler is deliberately not the usual `alert(1)`: NativeDialogsTest
    // greps resources/js for native dialog calls, and a payload string reads
    // exactly like one. The assertion is about the angle brackets, so the
    // handler name is free.
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.text]]][[[endforeach]]]',
      { 'chat.0.text': '<img src=x onerror=xss>', 'chat.count': 1 },
      LOCALE,
    );

    expect(out).not.toContain('<img');
    expect(out).toContain('&lt;img');
  });
});

describe('renderTemplateSource / defusing does not damage ordinary data', () => {
  it('renders brackets in everyday text as readable brackets', () => {
    // "[AFK] brb" is normal chat. Entity-encoded brackets display as the
    // literal characters, so the viewer sees no difference.
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.text]]][[[endforeach]]]',
      { 'chat.0.text': '[AFK] brb', 'chat.count': 1 },
      LOCALE,
    );

    expect(out).toBe('&#91;AFK&#93; brb');
  });

  it('leaves an authored ?? default resolvable, since the author owns the template', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.missing ?? nobody]]][[[endforeach]]]',
      { 'chat.0.text': 'hi', 'chat.count': 1 },
      LOCALE,
    );

    expect(out).toBe('nobody');
  });

  it('still applies pipe formatters to scoped values', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.name|uppercase]]][[[endforeach]]]',
      { 'chat.0.name': 'jasper', 'chat.count': 1 },
      LOCALE,
    );

    expect(out).toBe('JASPER');
  });
});

/**
 * Loop scoping contract.
 *
 * A foreach body has to see three things at once: the current item under its
 * alias, the `loop.*` counters, and the entire outer payload (so a condition
 * inside a loop can branch on whether the stream is live). Historically that was
 * achieved by copying the whole payload into a fresh object per iteration, which
 * made the render cost quadratic in payload size x message count.
 *
 * These tests pin the SEMANTICS so that cost can be attacked without changing
 * behaviour. Every one of them passed before the optimisation and must keep
 * passing after it. The dotted-outer-key and shadowing cases are the two that a
 * prototype-chain lookup gets wrong if done carelessly.
 */
describe('renderTemplateSource / foreach scope', () => {
  const OUTER = {
    'twitch.stream.is_live': '1',
    'twitch.user.display_name': 'CasualElephant',
    'c:alerts:muted': '0',
    tier: 'gold',
    'chat.0.text': 'first',
    'chat.0.name': 'ana',
    'chat.1.text': 'second',
    'chat.1.name': 'bo',
    'chat.count': 2,
  };

  it('resolves the alias sub-field and the bare alias', () => {
    expect(renderTemplateSource('[[[foreach:chat as m]]][[[m.name]]];[[[endforeach]]]', OUTER, LOCALE)).toBe('ana;bo;');

    expect(
      renderTemplateSource('[[[foreach:names as n]]][[[n]]];[[[endforeach]]]', { 'names.0': 'x', 'names.1': 'y', 'names.count': 2 }, LOCALE),
    ).toBe('x;y;');
  });

  it('exposes loop.index, loop.first, loop.last and loop.count', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as m]]][[[loop.index]]]/[[[loop.count]]] f=[[[loop.first]]] l=[[[loop.last]]];[[[endforeach]]]',
      OUTER,
      LOCALE,
    );

    expect(out).toBe('0/2 f=1 l=0;1/2 f=0 l=1;');
  });

  it('lets a condition inside the loop read a plain outer key', () => {
    const out = renderTemplateSource('[[[foreach:chat as m]]][[[if:tier = gold]]]*[[[endif]]][[[m.name]]];[[[endforeach]]]', OUTER, LOCALE);

    expect(out).toBe('*ana;*bo;');
  });

  it('lets a condition inside the loop read a DOTTED outer key', () => {
    // The payload is flat: the key is literally "twitch.stream.is_live", not a
    // nested object. A lookup that walks dots before checking the flat key, or
    // that only checks own properties on a prototype-linked scope, breaks here.
    const out = renderTemplateSource(
      '[[[foreach:chat as m]]][[[if:twitch.stream.is_live = 1]]]LIVE:[[[endif]]][[[m.name]]];[[[endforeach]]]',
      OUTER,
      LOCALE,
    );

    expect(out).toBe('LIVE:ana;LIVE:bo;');
  });

  it('lets a condition inside the loop read a namespaced outer control key', () => {
    const out = renderTemplateSource('[[[foreach:chat as m]]][[[if:c:alerts:muted = 0]]]on[[[endif]]];[[[endforeach]]]', OUTER, LOCALE);

    expect(out).toBe('on;on;');
  });

  it('resolves outer tags written inside a loop body', () => {
    // Non-scoped tags are left untouched by pass 1 and resolved by pass 2, so
    // the same outer value repeats once per iteration.
    const out = renderTemplateSource('[[[foreach:chat as m]]][[[twitch.user.display_name]]]:[[[m.name]]];[[[endforeach]]]', OUTER, LOCALE);

    expect(out).toBe('CasualElephant:ana;CasualElephant:bo;');
  });

  it('branches on a scoped field', () => {
    const out = renderTemplateSource('[[[foreach:chat as m]]][[[if:m.name = ana]]]HI [[[endif]]][[[m.text]]];[[[endforeach]]]', OUTER, LOCALE);

    expect(out).toBe('HI first;second;');
  });

  it('gives the scoped alias precedence over an outer key of the same name', () => {
    const out = renderTemplateSource(
      '[[[foreach:rows as r]]][[[r.v]]];[[[endforeach]]]',
      { 'r.v': 'OUTER', 'rows.0.v': 'inner', 'rows.count': 1 },
      LOCALE,
    );

    expect(out).toBe('inner;');
  });

  it('does not leak scoped tokens outside the loop', () => {
    const out = renderTemplateSource('[[[foreach:chat as m]]][[[m.name]]];[[[endforeach]]]|[[[m.name]]]', OUTER, LOCALE);

    // The trailing tag has no scope to resolve against, so it renders empty
    // rather than picking up the last iteration's value.
    expect(out).toBe('ana;bo;|');
  });

  it('keeps outer data visible through a nested loop', () => {
    const out = renderTemplateSource(
      '[[[foreach:rooms as room]]][[[foreach:chat as m]]][[[if:tier = gold]]]g[[[endif]]][[[room.id]]]-[[[m.name]]];[[[endforeach]]][[[endforeach]]]',
      { ...OUTER, 'rooms.0.id': 'A', 'rooms.count': 1 },
      LOCALE,
    );

    expect(out).toBe('gA-ana;gA-bo;');
  });

  it('iterates only over indices that have data, not up to count', () => {
    // count is the source-of-truth size and may exceed what the payload
    // carries once a foreach cap has trimmed it. Padding the difference with
    // empty objects would render blank rows.
    const out = renderTemplateSource('[[[foreach:chat as m]]][[[m.name]]];[[[endforeach]]]', { 'chat.0.name': 'solo', 'chat.count': 25 }, LOCALE);

    expect(out).toBe('solo;');
  });
});

/**
 * The html-safe escape hatch.
 *
 * `chat.N.html` is the one foreach field rendered WITHOUT entity-encoding, so
 * that emote `<img>` tags survive. That is a deliberate hole in the escaping
 * hardened above, and these tests are what keep it a hole rather than a wound.
 *
 * Two properties must both hold: markup that the emote parser produced gets
 * through, and a chatter still cannot reach the tag substitution pass. The
 * second is the one that would silently regress.
 */
describe('renderTemplateSource / html-safe foreach fields', () => {
  const SAFE = { chat: ['html'] };

  it('emits a declared html field without entity-encoding', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.html]]][[[endforeach]]]',
      { 'chat.0.html': 'hi <img class="overlay-emote" alt="Kappa" src="https://cdn/x.png">', 'chat.count': 1 },
      LOCALE,
      true,
      SAFE,
    );

    expect(out).toContain('<img class="overlay-emote"');
    expect(out).not.toContain('&lt;img');
  });

  it('still defuses tag brackets inside an html field', () => {
    // encodeHtml never touched `[`, so without defusing here a chatter typing
    // a tag would land literally in the output and be resolved by pass 2 -
    // the injection hole closed in #230, reopened through a side door.
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.html]]][[[endforeach]]]',
      { 'chat.0.html': `[[[${SECRET_TAG}]]]`, 'chat.count': 1, [SECRET_TAG]: SECRET_VALUE },
      LOCALE,
      true,
      SAFE,
    );

    expect(out).not.toContain(SECRET_VALUE);
    expect(out).toContain('&#91;&#91;&#91;');
  });

  it('does not exempt any other field of the same loop', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.text]]][[[endforeach]]]',
      { 'chat.0.text': '<img src=x onerror=xss>', 'chat.count': 1 },
      LOCALE,
      true,
      SAFE,
    );

    expect(out).toContain('&lt;img');
    expect(out).not.toContain('<img');
  });

  it('does not exempt a field called html on a DIFFERENT iterable', () => {
    // The exemption is keyed by iterable precisely so it cannot leak to some
    // other loop that happens to have a field with the same name.
    const out = renderTemplateSource(
      '[[[foreach:c:list:notes as n]]][[[n.html]]][[[endforeach]]]',
      { 'c:list:notes.0.html': '<img src=x onerror=xss>', 'c:list:notes.count': 1 },
      LOCALE,
      true,
      SAFE,
    );

    expect(out).toContain('&lt;img');
    expect(out).not.toContain('<img');
  });

  it('encodes everything when no exemption is passed at all', () => {
    const out = renderTemplateSource(
      '[[[foreach:chat as msg]]][[[msg.html]]][[[endforeach]]]',
      { 'chat.0.html': '<img src=x>', 'chat.count': 1 },
      LOCALE,
    );

    expect(out).toContain('&lt;img');
    expect(out).not.toContain('<img');
  });
});
