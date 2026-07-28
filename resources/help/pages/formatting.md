---
title: Formatting Pipes
description: Learn how to format numbers, durations, currencies, and dates in your Twitch overlays using pipe syntax. Zero dependencies, fully locale-aware.
heading: Formatting Pipes
lead: Learn how to format numbers, durations, currencies, and dates in your Twitch overlays using pipe syntax. Zero dependencies, fully locale-aware.
canonical: https://overlabels.com/help/formatting
---

No JavaScript required, no external libraries, no build step. Just add a pipe to your tag and you are
done.

## Pipe Syntax

Add a pipe `|` after any tag name, followed by the formatter. Some formatters accept arguments after a
colon.

```
Without formatting:                 [[[c:score]]]
With a formatter:                   [[[c:score|round]]]
With a formatter and arguments:     [[[c:timer|duration:hh:mm:ss]]]
```

The pipe is stripped before the tag is resolved. A tag like `[[[c:score|round]]]` still reads the value
of the `score` control - the pipe only affects how it is displayed.

### Works with all tag types

Pipes work on any template tag - controls, Twitch data, Ko-fi data, StreamLabs data, event data. Anything
between `[[[` and `]]]` can have a pipe.

```
Control value:              [[[c:amount|currency:EUR]]]
Ko-fi integration data:     [[[c:kofi:latest_donation_amount|currency]]]
Event data in alerts:       [[[event.user_name|uppercase]]]
```

## `|round` - Round numbers

Rounds a numeric value. Without arguments, rounds to a whole number. Pass a number to control decimal
places.

| Tag | Raw value | Output |
|---|---|---|
| `[[[c:score\|round]]]` | 42.789 | 43 |
| `[[[c:score\|round:1]]]` | 42.789 | 42.8 |
| `[[[c:score\|round:2]]]` | 42.789 | 42.79 |

## `|number` - Locale-aware numbers

Formats a number with thousands separators and decimal notation based on your locale setting. Optionally
pass a number to fix decimal places.

| Tag | Raw value | en-US | nl-NL |
|---|---|---|---|
| `[[[c:viewers\|number]]]` | 1234567 | 1,234,567 | 1.234.567 |
| `[[[c:ratio\|number:2]]]` | 3.5 | 3.50 | 3,50 |

## `|currency` - Currency formatting

Formats a number as a currency value with the proper symbol, decimal places, and separators for your
locale.

Without arguments, the currency is determined by your locale (EUR for Dutch, GBP for British, USD for
American, etc.). Pass a three-letter [ISO 4217](https://en.wikipedia.org/wiki/ISO_4217) currency code to
override.

| Tag | Raw value | en-US | nl-NL |
|---|---|---|---|
| `[[[c:goal\|currency]]]` | 42.5 | $42.50 | € 42,50 |
| `[[[c:goal\|currency:EUR]]]` | 42.5 | €42.50 | € 42,50 |
| `[[[c:goal\|currency:JPY]]]` | 4250 | ¥4,250 | JP¥ 4.250 |

**Tip:** If your streaming currency differs from your locale's default, just pass the code explicitly. A
Dutch streamer who receives USD donations can use `[[[c:kofi:latest_donation_amount|currency:USD]]]`.

## `|duration` - Time durations

Without arguments, the duration formatter picks the most readable format based on how large the value is.
The input is always in **seconds**.

| Raw seconds | Output |
|---|---|
| 45 | 0:45 |
| 754 | 12:34 |
| 8107 | 2:15:07 |
| 93907 | 1d 2h 5m |

### Explicit patterns

Pass a pattern using `dd`, `hh`, `mm`, `ss` tokens. Time overflows into the largest unit in your pattern.

| Tag | Seconds | Output |
|---|---|---|
| `[[[c:timer\|duration:hh:mm:ss]]]` | 8107 | 02:15:07 |
| `[[[c:timer\|duration:mm:ss]]]` | 8107 | 135:07 |
| `[[[c:timer\|duration:dd:hh:mm:ss]]]` | 93907 | 01:02:05:07 |
| `[[[c:timer\|duration:mm:ss]]]` | 45 | 00:45 |

The overflow rule means `mm:ss` with 8107 seconds gives you `135:07`, not `15:07`. The hours spill into
the minutes because there is no `hh` in the pattern.

**Tip:** Negative values (like a countdown past zero) are supported. The output will be prefixed with
`-`, e.g. `-02:15`.

## `|date` - Date formatting

Formats a date or datetime string. Without arguments, shows a locale-aware date and time. Use a named
preset or a custom pattern to control the exact output.

| Tag | en-US | nl-NL |
|---|---|---|
| `[[[c:event_date\|date]]]` | Apr 5, 2026, 7:00 PM | 5 apr 2026, 19:00 |
| `[[[c:event_date\|date:short]]]` | Apr 5, 7:00 PM | 5 apr, 19:00 |
| `[[[c:event_date\|date:long]]]` | Saturday, April 5, 2026, 7:00 PM | zaterdag 5 april 2026, 19:00 |
| `[[[c:event_date\|date:date]]]` | Apr 5, 2026 | 5 apr 2026 |
| `[[[c:event_date\|date:time]]]` | 7:00:00 PM | 19:00:00 |
| `[[[c:event_date\|date:dd-MM-yyyy]]]` | 05-04-2026 | 05-04-2026 |
| `[[[c:event_date\|date:dd-MM-yyyy HH:mm]]]` | 05-04-2026 19:00 | 05-04-2026 19:00 |

### Available tokens

| Token | Meaning |
|---|---|
| `yyyy` | Full year |
| `MM` | Month (01-12) |
| `dd` | Day (01-31) |
| `HH` | Hours (00-23) |
| `mm` | Minutes (00-59) |
| `ss` | Seconds (00-59) |

## `|distance` - Distance with unit conversion

Formats a distance value with locale-aware number formatting and optional unit conversion. The input is
always assumed to be in **kilometers**. Pass the target unit as an argument: `km` for kilometers
(pass-through) or `mi` for miles.

The unit label is never appended. Add your own label in the template if you want it:
`[[[c:gps:session_distance|distance:km]]] km`.

| Tag | Raw value (km) | en-US | nl-NL |
|---|---|---|---|
| `[[[c:gps:session_distance\|distance:km]]]` | 11.61 | 11.61 | 11,61 |
| `[[[c:gps:session_distance\|distance:mi]]]` | 11.61 | 7.21 | 7,21 |

**Why km in, any unit out?** Overlabels GPS controls store raw distance values in km so your templates
are the single source of truth for how distance is shown. Your locale handles number formatting; the pipe
arg handles unit choice.

## `|speed` - Speed with unit conversion

Formats a speed value with locale-aware number formatting and unit conversion. The input is always
assumed to be in **meters per second**. Pass the target unit: `kmh` for kilometers per hour, or `mph` for
miles per hour.

The unit label is not appended. Append it yourself if you want it shown.

| Tag | Raw value (m/s) | en-US | nl-NL |
|---|---|---|---|
| `[[[c:gps:session_max_speed\|speed:kmh]]]` | 15.5 | 55.8 | 55,8 |
| `[[[c:gps:session_avg_speed\|speed:mph]]]` | 15.5 | 34.7 | 34,7 |

**Note:** `c:gps:speed` is a legacy control that's pre-converted server-side based on your speed_unit
setting. The newer per-session controls (`session_max_speed`, `session_avg_speed`) store raw m/s so the
`|speed:` pipe is required to render them.

## `|uppercase` / `|lowercase` - Text transforms

Simple text case transformations. No arguments needed.

| Tag | Raw value | Output |
|---|---|---|
| `[[[event.user_name\|uppercase]]]` | NightBot | NIGHTBOT |
| `[[[event.user_name\|lowercase]]]` | NightBot | nightbot |

## `?? default` - Fallback for empty tags

Add `?? something` after a tag to show literal text when the tag resolves **empty**. Without it, an empty
tag renders nothing - so `Hi [[[bot:args.0]]]!` with no argument becomes `Hi !`. A default keeps it
readable.

```
No value - shows the default:
[[[bot:args.0 ?? everyone]]]

Pairs with a pipe - format when present, default when empty:
[[[c:donations|number ?? 0]]]

The default is literal - spaces and punctuation are kept:
[[[event.user_name ?? a kind stranger]]]
```

### How it behaves

| Tag | Raw value | Output |
|---|---|---|
| `[[[c:tips ?? 0]]]` | (empty) | 0 |
| `[[[c:tips ?? 0]]]` | 25 | 25 |
| `[[[c:tips\|currency ?? no tips yet]]]` | (empty) | no tips yet |
| `[[[c:tips\|currency ?? no tips yet]]]` | 25 | $25.00 |

When a value is present the pipe formats it as usual; the default is only used when the value is empty,
and it is shown verbatim (the pipe is never applied to it).

**It only fills in for empty.** A default backstops a *missing* value, not a *wrong* one. If a value is
present - even an unexpected one - it is shown as-is and the default never fires. And like pipes, `??` is
display-only: it never changes a control's stored value or feeds into expression-control math.

## Locale Settings

The `|number`, `|currency`, `|date`, `|distance`, and `|speed` formatters are all locale-aware. Your
locale controls things like:

- Thousands separator (comma vs period vs space)
- Decimal separator (period vs comma)
- Currency symbol and position
- Date month names and ordering
- Default currency code (USD for en-US, EUR for nl-NL, etc.)

### Changing your locale

Go to [Settings > Account](/settings/account) and pick your locale from the dropdown. You will see a live
preview of how numbers, currencies, and dates will look in your overlays.

The locale is applied to all your overlays automatically. Your viewers do not need to do anything.

## Pipes in CSS

Pipes work in CSS too - anywhere you can use a template tag, you can pipe it.

```html
<style>
  .timer {
    /* Hides when timer hits 00:00 */
    content: '[[[c:round_timer|duration:mm:ss]]]';
  }
  .donation {
    content: '[[[c:kofi:latest_donation_amount|currency]]]';
  }
</style>
```

## Tips

### One pipe per tag

Each tag supports exactly one pipe. You cannot chain multiple formatters like
`[[[c:score|round|number]]]`. Pick the one formatter that gets you closest to what you need.

### Pipes are display-only

Formatting never changes the stored value. Your control still holds the raw number - the pipe just
changes how the overlay renders it. Two tags referencing the same control with different pipes will
display differently but read the same underlying value.

### Unknown formatters are ignored

If you typo a formatter name, the value is displayed as-is. No errors, no blank output - just the raw
value. Check your spelling if formatting does not seem to apply.

### Duration expects seconds

The `|duration` formatter always expects the raw value to be in seconds. Timer controls already output
seconds, so they work perfectly. If you are feeding in a custom value, make sure it is seconds.

### Same tag, different format

You can reference the same control multiple times with different pipes. This is useful for showing the
same value in different formats.

```html
<div>[[[c:timer|duration:hh:mm:ss]]]</div>
<div>[[[c:timer]]] seconds</div>
```

## Quick Reference

| Pipe | Arguments | Description |
|---|---|---|
| `\|round` | N (decimal places) | Round to N decimals (default: 0) |
| `\|number` | N (decimal places) | Locale-aware thousands separators |
| `\|currency` | CODE (e.g. EUR, GBP) | Locale-aware currency with symbol |
| `\|duration` | pattern (hh:mm:ss, mm:ss, etc.) | Seconds to human-readable time |
| `\|date` | short, long, date, time, or pattern | Locale-aware date + time formatting |
| `\|distance` | km, mi | Locale-aware distance, input in km |
| `\|speed` | kmh, mph | Locale-aware speed, input in m/s |
| `\|uppercase` | - | ALL CAPS |
| `\|lowercase` | - | all lowercase |
| `?? text` | literal fallback | Shown only when the tag is empty |

## More help

See the [Conditional Tags](/help/conditionals) and [Controls](/help/controls) guides for more on how
template tags and controls work. If you are stuck,
[jasper@emailjasper.com](mailto:jasper@emailjasper.com) or
[open an issue](https://github.com/jasperfrontend/overlabels/issues) on
[GitHub](https://github.com/jasperfrontend/overlabels).
