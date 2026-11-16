<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Move every installed Follower Bowling lane to the full-screen redesign.
 *
 * On 2026-11-16 the lane became a full-screen BRB scene in the Chat Tower and
 * Plinko look, and the product lost its on/off switch and its two moderator
 * aliases: `!bowl` puts a viewer in line, first come first served, and the
 * product pops the next one every fifteen seconds whenever someone is in
 * line. AutoPlayService no longer reads a `gobowl` control at all, so an
 * install still on the old overlay would bowl through its line while the
 * lane hid behind `[[[if:c:gobowl]]]`.
 *
 * Unlike the Chat Checkin backfill, the source is replaced WHOLESALE, edited
 * or not (Jasper, 2026-11-16: nobody but him had the lane running). Each
 * install, all or nothing:
 *  - head, html, css, description and the template_tags allowlist replaced
 *    with the redesigned lane, exactly as the installer stores it;
 *  - the `gobowl` control deleted. The twenty expression controls stay: their
 *    expressions are identical in every version the recipe ever shipped;
 *  - the `!fbfirst` / `!fbdraw` aliases the install created deleted, and the
 *    `bot_aliases` section dropped from the install's primitive_map so the
 *    uninstall list stops naming them. An alias the streamer already deleted
 *    or renamed is matched by id, as the installer does.
 *
 * Every value is frozen here: reading the recipe on disk would install
 * whatever the document says on the day this runs.
 */
return new class extends Migration
{
    private const SLUGS = ['follower-bowling', 'follower_bowling'];

    private const OVERLAY_REF = 'lane';

    private const SWITCH_KEY = 'gobowl';

    private const DESCRIPTION = 'Chatters type !bowl to get in line, first come first served. Every fifteen seconds the next one in line bowls: the ball rolls for them, your ten newest followers are the pins, and the outcome is decided from the moment they left the line.';

    private const HEAD = <<<'LANE_HEAD'
<meta name="generator" content="Overlabels /engine">
<meta name="overlabels:engine" content="v1 2026-11-16 controls:20 sha256:fe0091e8729a">
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css2?family=Albert+Sans:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
LANE_HEAD;

    private const HTML = <<<'LANE_HTML'
<div data-overlabels-engine="v1" class="stage" id="lane-widget" data-last-throw="[[[c:list:lane:last_removed_at]]]" data-knocked="[[[c:bowl_knocked]]]">
  <div class="intro">
    <p class="eyebrow">Follower Bowling</p>
    <h1>Type <span class="cmd">!bowl</span> in chat</h1>
    <p class="sub">You join the line. When it is your turn the ball rolls for you, and the pins are this channel's ten newest followers.</p>
  </div>

  [[[if:c:list:lane:count > 0]]]
  <div class="line">
    <p class="label">Next up</p>
    <ol class="queue">[[[foreach:c:list:lane as p]]][[[if:loop.index <= 4]]]<li>[[[p]]]</li>[[[endif]]][[[endforeach]]]</ol>
  </div>
  [[[endif]]]

  <p class="count">[[[if:c:list:lane:count > 0]]]<b>[[[c:list:lane:count]]]</b> in line[[[else]]]Nobody in line yet[[[endif]]]</p>

  <div class="field">
    <div class="lane">
      <div class="gutter left"></div>
      <div class="gutter right"></div>
      <div class="arrows"><i></i><i></i><i></i><i></i><i></i></div>
      <div class="foul"></div>
    </div>

    <div class="pins" id="pins">
      [[[foreach:channel_followers as f]]][[[if:loop.index <= 9]]]<div class="pin" data-key="[[[f.user_id]]]" style="--fall: var(--pin[[[loop.index]]])"><img src="[[[f.user_profile_image_url]]]" alt=""><span>[[[f.user_name]]]</span></div>[[[endif]]][[[endforeach]]]
      [[[if:channel_followers.0.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin0)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.1.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin1)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.2.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin2)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.3.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin3)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.4.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin4)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.5.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin5)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.6.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin6)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.7.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin7)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.8.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin8)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.9.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin9)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
    </div>

    <div class="ball" id="ball"><span class="hole hole-1"></span><span class="hole hole-2"></span><span class="hole hole-3"></span></div>

    <div class="slot" id="slot-strike">
      [[[if:c:bowl_knocked = 10]]]
      <div class="strike" id="strike">
        <div class="strike-word">STRIKE!</div>
        <div class="strike-name">[[[c:list:lane:last_removed]]] knocked down all ten</div>
      </div>
      [[[endif]]]
    </div>
  </div>

  <div class="score">
    <section class="now">
      <p class="label">Bowling</p>
      <div class="swap">
        <p class="bowler" id="bowler">[[[c:list:lane:last_removed]]]</p>
        <p class="bowler-idle">Waiting for a bowler</p>
      </div>
    </section>
    <section class="result">
      <p class="label">Pins down</p>
      <div class="swap">
        <p class="big num" id="score">[[[c:bowl_knocked]]]</p>
        <p class="big num dash">?</p>
      </div>
      <div class="swap status">
        <p class="status-result" id="status">[[[if:c:bowl_knocked = 10]]]strike![[[elseif:c:bowl_knocked = 0]]]gutter ball[[[else]]]of 10[[[endif]]]</p>
        <p class="status-rolling">rolling...</p>
      </div>
    </section>
  </div>
</div>
LANE_HTML;

    private const CSS = <<<'LANE_CSS'
:root {
  --bx: [[[c:ball_x|round:3]]];
  --by: [[[c:ball_y|round:3]]];
  --pulse: [[[c:ball_pulse|round:3]]];
  --on: [[[c:ball_on]]];
  --show: [[[c:bowl_show]]];
  --bowler: [[[c:bowler_show]]];
  --pin0: [[[c:pin_0]]];
  --pin1: [[[c:pin_1]]];
  --pin2: [[[c:pin_2]]];
  --pin3: [[[c:pin_3]]];
  --pin4: [[[c:pin_4]]];
  --pin5: [[[c:pin_5]]];
  --pin6: [[[c:pin_6]]];
  --pin7: [[[c:pin_7]]];
  --pin8: [[[c:pin_8]]];
  --pin9: [[[c:pin_9]]];

  /* the Chat Tower and Plinko palette: deep violet field, pink accent */
  --ink: #fafafa;
  --ink-soft: #e9d5ff;
  --ink-muted: #a78bfa;
  --pink: #f472b6;
  --pink-deep: #ec4899;
  --field: #1d0b30;
  --field-deep: #120720;
  --line: rgb(167 139 250 / 0.35);
  --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 1920px; height: 1080px; overflow: hidden; }
body {
  background: var(--field-deep);
  color: var(--ink);
  font-family: "Albert Sans", system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}

.stage { position: relative; width: 1920px; height: 1080px; background: var(--field-deep); }
.num { font-variant-numeric: tabular-nums; }
.cmd { font-family: var(--mono); color: var(--pink); white-space: nowrap; }

.eyebrow,
.label {
  font-family: var(--mono);
  font-size: 16px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}

/* ---------- left: how to play, then the line ---------- */
.intro { position: absolute; left: 64px; top: 64px; width: 380px; }
h1 { margin-top: 12px; font-size: 56px; line-height: 1.05; font-weight: 800; }
.sub { margin-top: 20px; font-size: 21px; line-height: 1.5; color: var(--ink-soft); }

.line { position: absolute; left: 64px; top: 440px; width: 380px; }
.queue { margin-top: 10px; list-style: none; counter-reset: place; }
.queue li {
  display: flex;
  gap: 14px;
  padding: 10px 0;
  border-top: 1px solid var(--line);
  font-size: 21px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  counter-increment: place;
}
.queue li::before {
  content: counter(place);
  flex: none;
  width: 22px;
  font-family: var(--mono);
  color: var(--ink-muted);
}
.queue li:first-child::before { color: var(--pink); }
.queue li:last-child { border-bottom: 1px solid var(--line); }

.count {
  position: absolute;
  left: 64px;
  bottom: 64px;
  width: 380px;
  font-family: var(--mono);
  font-size: 18px;
  color: var(--ink-muted);
}
.count b { color: var(--ink); font-weight: 700; }

/* ---------- centre: the lane, seen from the bowler ---------- */
.field {
  position: absolute;
  left: 480px;
  top: 0;
  width: 960px;
  height: 1080px;
  overflow: hidden;
}

.lane {
  position: absolute;
  left: 140px;
  width: 680px;
  top: 40px;
  bottom: 40px;
  border-left: 2px solid var(--line);
  border-right: 2px solid var(--line);
  background: var(--field);
}

.gutter {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 56px;
  background: var(--field-deep);
}
.gutter.left { left: 0; border-right: 1px solid var(--line); }
.gutter.right { right: 0; border-left: 1px solid var(--line); }

/* the foul line the ball waits behind */
.foul {
  position: absolute;
  left: 56px;
  right: 56px;
  top: 760px;
  height: 3px;
  background: var(--ink-muted);
}

/* aiming arrows on the boards, pointing at the rack */
.arrows {
  position: absolute;
  left: 56px;
  right: 56px;
  top: 600px;
  display: flex;
  justify-content: space-evenly;
}
.arrows i {
  width: 0;
  height: 0;
  border-left: 12px solid transparent;
  border-right: 12px solid transparent;
  border-bottom: 26px solid var(--line);
}
.arrows i:nth-child(2),
.arrows i:nth-child(4) { margin-top: 36px; }
.arrows i:nth-child(3) { margin-top: 72px; }

/* ---------- pins: the head pin nearest the ball, the back row of four on top ---------- */
.pins { position: absolute; inset: 0; }

.pin {
  position: absolute;
  width: 128px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  transform-origin: 50% 100%;
  transform: translateY(calc(var(--fall) * -36px)) rotate(calc(var(--fall) * var(--dir, 1) * 80deg));
  opacity: calc(1 - var(--fall) * 0.75);
  transition:
    transform 0.35s cubic-bezier(0.3, 1.4, 0.6, 1),
    opacity 0.35s;
}

.pin img {
  width: 80px;
  height: 80px;
  border-radius: 9999px;
  border: 3px solid var(--ink-muted);
  background: var(--field-deep);
  object-fit: cover;
}

.pin span {
  max-width: 128px;
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--field-deep);
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* the head pin carries the pink ring */
.pin:nth-child(1) img { border-color: var(--pink); }

/* a stand-in for a follower the channel does not have yet, so a new
   channel still bowls at ten: same physics, dimmer face; a real follower
   takes its slot the moment they arrive */
.pin-filler img {
  border-style: dashed;
  opacity: 0.55;
  filter: grayscale(1);
}
.pin-filler span { color: var(--ink-muted); }

/* rows from the ball: 1, 2, 3, 4. --dir is the side a pin falls to */
.pin:nth-child(1)  { left: 416px; top: 428px; --dir: 1; }
.pin:nth-child(2)  { left: 348px; top: 312px; --dir: -1; }
.pin:nth-child(3)  { left: 484px; top: 312px; --dir: 1; }
.pin:nth-child(4)  { left: 280px; top: 196px; --dir: -1; }
.pin:nth-child(5)  { left: 416px; top: 196px; --dir: 1; }
.pin:nth-child(6)  { left: 552px; top: 196px; --dir: 1; }
.pin:nth-child(7)  { left: 212px; top: 80px; --dir: -1; }
.pin:nth-child(8)  { left: 348px; top: 80px; --dir: -1; }
.pin:nth-child(9)  { left: 484px; top: 80px; --dir: 1; }
.pin:nth-child(10) { left: 620px; top: 80px; --dir: 1; }

/* the ball: waits behind the foul line pulsing, rolls up the lane into the
   rack and gets smaller as it goes; on a gutter ball it curves off to the
   right and drops into the gutter */
.ball {
  position: absolute;
  left: 430px;
  top: 840px;
  width: 100px;
  height: 100px;
  border-radius: 9999px;
  background: var(--pink);
  box-shadow:
    inset -10px -12px 0 rgb(0 0 0 / 0.22),
    inset 6px 6px 0 rgb(255 255 255 / 0.25),
    0 0 0 3px var(--field-deep);
  transform:
    translate(calc(var(--on) * var(--by) * 312px), calc(var(--on) * var(--bx) * -560px))
    scale(calc(var(--pulse) * (1 - var(--on) * var(--bx) * 0.32)))
    rotate(calc(var(--on) * var(--bx) * 1080deg));
  /* between throws it rests on the approach, dimmed */
  opacity: calc(0.3 + var(--on) * 0.7);
  z-index: 2;
}

.hole {
  position: absolute;
  width: 14px;
  height: 14px;
  border-radius: 9999px;
  background: var(--field-deep);
}
.hole-1 { left: 42px; top: 26px; width: 15px; height: 15px; }
.hole-2 { left: 28px; top: 48px; }
.hole-3 { left: 54px; top: 52px; }

/* ---------- the strike moment ---------- */
.strike {
  position: absolute;
  left: 0;
  right: 0;
  top: 560px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 28px 24px;
  background: rgb(18 7 32 / 0.92);
  border-top: 2px solid var(--pink);
  border-bottom: 2px solid var(--pink);
  text-align: center;
  opacity: var(--show);
  transform: scale(calc(0.6 + var(--show) * 0.4));
  transition:
    opacity 0.3s ease,
    transform 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.4);
  z-index: 3;
}
.strike-word { font-size: 96px; font-weight: 800; line-height: 1; letter-spacing: -0.02em; color: var(--pink); }
.strike-name {
  max-width: 100%;
  font-size: 28px;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ---------- right: who bowls, and how it went ---------- */
.score { position: absolute; right: 64px; top: 64px; width: 380px; text-align: center; }
.result { margin-top: 56px; opacity: var(--bowler); transition: opacity 0.25s; }

/* two lines in one place, one fading in as the other fades out */
.swap { display: grid; }
.swap > * { grid-area: 1 / 1; transition: opacity 0.25s; }

.bowler {
  margin-top: 8px;
  font-size: 44px;
  font-weight: 800;
  line-height: 1.15;
  color: var(--pink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: var(--bowler);
}
.bowler-idle {
  margin-top: 8px;
  align-self: center;
  font-size: 24px;
  font-weight: 600;
  color: var(--ink-soft);
  opacity: calc(1 - var(--bowler));
}

.big { margin-top: 4px; font-size: 144px; font-weight: 800; line-height: 0.95; letter-spacing: -0.03em; }
#score { opacity: var(--show); }
.dash { color: var(--ink-muted); opacity: calc(1 - var(--show)); }

.status { margin-top: 8px; font-size: 24px; font-weight: 600; color: var(--ink-soft); }
.status-result { opacity: var(--show); }
.status-rolling { color: var(--ink-muted); opacity: calc(var(--bowler) - var(--show)); }
.stage[data-knocked="10"] .status-result { color: var(--pink); font-weight: 800; }
LANE_CSS;

    /**
     * The render allowlist at the largest followers cap an account can set
     * (User::FOREACH_CAP_MAX, 50). The installer expands the followers loop
     * to the owner's own cap, so no single exact list fits every account; a
     * superset is harmless, because the payload never carries more followers
     * than the cap, and the next save of the overlay narrows it again.
     */
    private const TAGS = [
        'c:list:lane:last_removed_at', 'c:bowl_knocked', 'endif', 'endforeach',
        'c:list:lane:count', 'else', 'if:channel_followers.0.user_id', 'if:channel_followers.1.user_id',
        'if:channel_followers.2.user_id', 'if:channel_followers.3.user_id', 'if:channel_followers.4.user_id', 'if:channel_followers.5.user_id',
        'if:channel_followers.6.user_id', 'if:channel_followers.7.user_id', 'if:channel_followers.8.user_id', 'if:channel_followers.9.user_id',
        'c:list:lane:last_removed', 'c:ball_x', 'c:ball_y', 'c:ball_pulse',
        'c:ball_on', 'c:bowl_show', 'c:bowler_show', 'c:pin_0',
        'c:pin_1', 'c:pin_2', 'c:pin_3', 'c:pin_4',
        'c:pin_5', 'c:pin_6', 'c:pin_7', 'c:pin_8',
        'c:pin_9', 'channel_followers.0.user_id', 'channel_followers.1.user_id', 'channel_followers.2.user_id',
        'channel_followers.3.user_id', 'channel_followers.4.user_id', 'channel_followers.5.user_id', 'channel_followers.6.user_id',
        'channel_followers.7.user_id', 'channel_followers.8.user_id', 'channel_followers.9.user_id', 'c:list:lane.count',
        'c:list:lane.0', 'c:list:lane.1', 'c:list:lane.2', 'c:list:lane.3',
        'c:list:lane.4', 'c:list:lane.5', 'c:list:lane.6', 'c:list:lane.7',
        'c:list:lane.8', 'c:list:lane.9', 'channel_followers.count', 'channel_followers.0.user_profile_image_url',
        'channel_followers.0.user_name', 'channel_followers.1.user_profile_image_url', 'channel_followers.1.user_name', 'channel_followers.2.user_profile_image_url',
        'channel_followers.2.user_name', 'channel_followers.3.user_profile_image_url', 'channel_followers.3.user_name', 'channel_followers.4.user_profile_image_url',
        'channel_followers.4.user_name', 'channel_followers.5.user_profile_image_url', 'channel_followers.5.user_name', 'channel_followers.6.user_profile_image_url',
        'channel_followers.6.user_name', 'channel_followers.7.user_profile_image_url', 'channel_followers.7.user_name', 'channel_followers.8.user_profile_image_url',
        'channel_followers.8.user_name', 'channel_followers.9.user_profile_image_url', 'channel_followers.9.user_name', 'channel_followers.10.user_id',
        'channel_followers.10.user_profile_image_url', 'channel_followers.10.user_name', 'channel_followers.11.user_id', 'channel_followers.11.user_profile_image_url',
        'channel_followers.11.user_name', 'channel_followers.12.user_id', 'channel_followers.12.user_profile_image_url', 'channel_followers.12.user_name',
        'channel_followers.13.user_id', 'channel_followers.13.user_profile_image_url', 'channel_followers.13.user_name', 'channel_followers.14.user_id',
        'channel_followers.14.user_profile_image_url', 'channel_followers.14.user_name', 'channel_followers.15.user_id', 'channel_followers.15.user_profile_image_url',
        'channel_followers.15.user_name', 'channel_followers.16.user_id', 'channel_followers.16.user_profile_image_url', 'channel_followers.16.user_name',
        'channel_followers.17.user_id', 'channel_followers.17.user_profile_image_url', 'channel_followers.17.user_name', 'channel_followers.18.user_id',
        'channel_followers.18.user_profile_image_url', 'channel_followers.18.user_name', 'channel_followers.19.user_id', 'channel_followers.19.user_profile_image_url',
        'channel_followers.19.user_name', 'channel_followers.20.user_id', 'channel_followers.20.user_profile_image_url', 'channel_followers.20.user_name',
        'channel_followers.21.user_id', 'channel_followers.21.user_profile_image_url', 'channel_followers.21.user_name', 'channel_followers.22.user_id',
        'channel_followers.22.user_profile_image_url', 'channel_followers.22.user_name', 'channel_followers.23.user_id', 'channel_followers.23.user_profile_image_url',
        'channel_followers.23.user_name', 'channel_followers.24.user_id', 'channel_followers.24.user_profile_image_url', 'channel_followers.24.user_name',
        'channel_followers.25.user_id', 'channel_followers.25.user_profile_image_url', 'channel_followers.25.user_name', 'channel_followers.26.user_id',
        'channel_followers.26.user_profile_image_url', 'channel_followers.26.user_name', 'channel_followers.27.user_id', 'channel_followers.27.user_profile_image_url',
        'channel_followers.27.user_name', 'channel_followers.28.user_id', 'channel_followers.28.user_profile_image_url', 'channel_followers.28.user_name',
        'channel_followers.29.user_id', 'channel_followers.29.user_profile_image_url', 'channel_followers.29.user_name', 'channel_followers.30.user_id',
        'channel_followers.30.user_profile_image_url', 'channel_followers.30.user_name', 'channel_followers.31.user_id', 'channel_followers.31.user_profile_image_url',
        'channel_followers.31.user_name', 'channel_followers.32.user_id', 'channel_followers.32.user_profile_image_url', 'channel_followers.32.user_name',
        'channel_followers.33.user_id', 'channel_followers.33.user_profile_image_url', 'channel_followers.33.user_name', 'channel_followers.34.user_id',
        'channel_followers.34.user_profile_image_url', 'channel_followers.34.user_name', 'channel_followers.35.user_id', 'channel_followers.35.user_profile_image_url',
        'channel_followers.35.user_name', 'channel_followers.36.user_id', 'channel_followers.36.user_profile_image_url', 'channel_followers.36.user_name',
        'channel_followers.37.user_id', 'channel_followers.37.user_profile_image_url', 'channel_followers.37.user_name', 'channel_followers.38.user_id',
        'channel_followers.38.user_profile_image_url', 'channel_followers.38.user_name', 'channel_followers.39.user_id', 'channel_followers.39.user_profile_image_url',
        'channel_followers.39.user_name', 'channel_followers.40.user_id', 'channel_followers.40.user_profile_image_url', 'channel_followers.40.user_name',
        'channel_followers.41.user_id', 'channel_followers.41.user_profile_image_url', 'channel_followers.41.user_name', 'channel_followers.42.user_id',
        'channel_followers.42.user_profile_image_url', 'channel_followers.42.user_name', 'channel_followers.43.user_id', 'channel_followers.43.user_profile_image_url',
        'channel_followers.43.user_name', 'channel_followers.44.user_id', 'channel_followers.44.user_profile_image_url', 'channel_followers.44.user_name',
        'channel_followers.45.user_id', 'channel_followers.45.user_profile_image_url', 'channel_followers.45.user_name', 'channel_followers.46.user_id',
        'channel_followers.46.user_profile_image_url', 'channel_followers.46.user_name', 'channel_followers.47.user_id', 'channel_followers.47.user_profile_image_url',
        'channel_followers.47.user_name', 'channel_followers.48.user_id', 'channel_followers.48.user_profile_image_url', 'channel_followers.48.user_name',
        'channel_followers.49.user_id', 'channel_followers.49.user_profile_image_url', 'channel_followers.49.user_name',
    ];

    public function up(): void
    {
        $recipeIds = DB::table('recipes')->whereIn('slug', self::SLUGS)->pluck('id');

        if ($recipeIds->isEmpty()) {
            return;
        }

        $moved = 0;
        $skipped = [];

        DB::table('recipe_instances')
            ->whereIn('recipe_id', $recipeIds)
            ->select('id', 'user_id', 'primitive_map')
            ->chunkById(100, function ($instances) use (&$moved, &$skipped) {
                foreach ($instances as $instance) {
                    $outcome = $this->upgrade($instance);

                    if ($outcome === 'moved') {
                        $moved++;
                    } elseif ($outcome !== 'done') {
                        $skipped[$instance->id] = $outcome;
                    }
                }
            });

        Log::info('Follower Bowling redesign migration', ['moved' => $moved, 'skipped' => count($skipped)]);

        foreach ($skipped as $instanceId => $reason) {
            Log::warning('Follower Bowling redesign migration skipped an install', [
                'recipe_instance_id' => $instanceId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * One install. Returns 'moved', 'done' (already on the redesign), or the
     * reason it was left alone.
     */
    private function upgrade(object $instance): string
    {
        $map = is_string($instance->primitive_map)
            ? json_decode($instance->primitive_map, true)
            : $instance->primitive_map;
        $map = is_array($map) ? $map : [];
        $templateId = $map['overlays'][self::OVERLAY_REF] ?? null;

        if (! $templateId) {
            return 'no lane overlay in the install';
        }

        $template = DB::table('overlay_templates')->where('id', $templateId)->first(['id', 'html']);

        if (! $template) {
            return 'overlay is gone';
        }

        $hasSwitch = DB::table('overlay_controls')
            ->where('overlay_template_id', $templateId)
            ->where('key', self::SWITCH_KEY)
            ->exists();

        if ((string) $template->html === self::HTML && ! $hasSwitch && ! isset($map['bot_aliases'])) {
            return 'done';
        }

        $aliasIds = array_values(array_map('intval', $map['bot_aliases'] ?? []));
        unset($map['bot_aliases']);
        $now = now();

        DB::transaction(function () use ($instance, $templateId, $aliasIds, $map, $now) {
            DB::table('overlay_templates')->where('id', $templateId)->update([
                'head' => self::HEAD,
                'html' => self::HTML,
                'css' => self::CSS,
                'description' => self::DESCRIPTION,
                'template_tags' => json_encode(self::TAGS),
                'updated_at' => $now,
            ]);

            DB::table('overlay_controls')
                ->where('overlay_template_id', $templateId)
                ->where('key', self::SWITCH_KEY)
                ->delete();

            if ($aliasIds !== []) {
                DB::table('bot_aliases')
                    ->where('user_id', $instance->user_id)
                    ->whereIn('id', $aliasIds)
                    ->delete();
            }

            DB::table('recipe_instances')->where('id', $instance->id)->update([
                'primitive_map' => json_encode($map),
                'updated_at' => $now,
            ]);
        });

        return 'moved';
    }

    /**
     * The old lane is not kept anywhere to put back, and nobody was running
     * it. Nothing to undo.
     */
    public function down(): void {}
};
