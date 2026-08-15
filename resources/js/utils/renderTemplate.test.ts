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
