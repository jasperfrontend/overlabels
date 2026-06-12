<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Search } from '@lucide/vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Conditional Tags', href: '/help/conditionals' },
];

type FamilyKey =
  | 'twitch_basic'
  | 'twitch_stream'
  | 'twitch_hype_train'
  | 'twitch_charity'
  | 'twitch_goals'
  | 'twitch_polls'
  | 'twitch_predictions'
  | 'kofi'
  | 'streamlabs'
  | 'streamelements'
  | 'fourthwall'
  | 'bmac';

const FAMILY_LABELS: Record<FamilyKey, string> = {
  twitch_basic: 'Twitch - Basic',
  twitch_stream: 'Twitch - Stream',
  twitch_hype_train: 'Twitch - Hype Train',
  twitch_charity: 'Twitch - Charity',
  twitch_goals: 'Twitch - Goals',
  twitch_polls: 'Twitch - Polls',
  twitch_predictions: 'Twitch - Predictions',
  kofi: 'Ko-fi',
  streamlabs: 'StreamLabs',
  streamelements: 'StreamElements',
  fourthwall: 'Fourthwall',
  bmac: 'Buy Me a Coffee',
};

const FAMILY_ORDER: FamilyKey[] = [
  'twitch_basic',
  'twitch_stream',
  'twitch_hype_train',
  'twitch_charity',
  'twitch_goals',
  'twitch_polls',
  'twitch_predictions',
  'kofi',
  'streamlabs',
  'streamelements',
  'fourthwall',
  'bmac',
];

interface Tag {
  tag: string;
  desc: string;
  note?: 'use' | 'avoid';
}

interface Column {
  heading: string;
  tags: Tag[];
}

interface EventCard {
  id: string;
  family: FamilyKey;
  title: string;
  type?: string;
  subtitle: string;
  dot: string;
  cols: Column[];
  example?: string;
  note?: { kind: 'info' | 'success' | 'warn'; text: string };
  specialBlock?: 'gift-bomb';
}

const cards: EventCard[] = [
  // === Twitch Basic ===
  {
    id: 'channel-follow',
    family: 'twitch_basic',
    title: 'Channel Follow',
    type: 'channel.follow',
    subtitle: 'When someone follows your channel',
    dot: 'bg-green-500',
    cols: [
      {
        heading: 'User Information',
        tags: [
          { tag: '[[[event.user_id]]]', desc: "Follower's Twitch ID" },
          { tag: '[[[event.user_login]]]', desc: "Follower's username" },
          { tag: '[[[event.user_name]]]', desc: "Follower's display name" },
        ],
      },
      {
        heading: 'Event Data',
        tags: [
          { tag: '[[[event.followed_at]]]', desc: 'Timestamp when followed' },
          { tag: '[[[event.broadcaster_user_name]]]', desc: 'Your display name' },
        ],
      },
    ],
  },
  {
    id: 'channel-subscribe',
    family: 'twitch_basic',
    title: 'Channel Subscribe',
    type: 'channel.subscribe',
    subtitle: 'When someone subscribes to your channel',
    dot: 'bg-purple-500',
    cols: [
      {
        heading: 'User Information',
        tags: [
          { tag: '[[[event.user_id]]]', desc: "Subscriber's Twitch ID" },
          { tag: '[[[event.user_login]]]', desc: "Subscriber's username" },
          { tag: '[[[event.user_name]]]', desc: "Subscriber's display name" },
        ],
      },
      {
        heading: 'Subscription Data',
        tags: [
          { tag: '[[[event.tier]]]', desc: 'Sub tier (1000, 2000, 3000)', note: 'avoid' },
          { tag: '[[[event.tier_display]]]', desc: 'Sub display (1, 2, 3)', note: 'use' },
          { tag: '[[[event.is_gift]]]', desc: 'true/false if gifted' },
        ],
      },
    ],
  },
  {
    id: 'channel-subscription-gift',
    family: 'twitch_basic',
    title: 'Subscription Gifts',
    type: 'channel.subscription.gift',
    subtitle: 'When someone gifts subscriptions',
    dot: 'bg-pink-500',
    cols: [
      {
        heading: 'User Information',
        tags: [
          { tag: '[[[event.user_id]]]', desc: "Gifter's Twitch ID" },
          { tag: '[[[event.user_login]]]', desc: "Gifter's username" },
          { tag: '[[[event.user_name]]]', desc: "Gifter's display name" },
        ],
      },
      {
        heading: 'Gift Data',
        tags: [
          { tag: '[[[event.total]]]', desc: 'Number of subs gifted' },
          { tag: '[[[event.tier]]]', desc: 'Sub tier (1000, 2000, 3000)', note: 'avoid' },
          { tag: '[[[event.tier_display]]]', desc: 'Sub display (1, 2, 3)', note: 'use' },
          { tag: '[[[event.cumulative_total]]]', desc: 'Total gifts ever' },
          { tag: '[[[event.is_anonymous]]]', desc: 'true/false if anonymous' },
        ],
      },
    ],
    specialBlock: 'gift-bomb',
  },
  {
    id: 'channel-subscription-message',
    family: 'twitch_basic',
    title: 'Subscription Messages',
    type: 'channel.subscription.message',
    subtitle: 'When someone resubscribes with a message',
    dot: 'bg-indigo-500',
    cols: [
      {
        heading: 'User Information',
        tags: [
          { tag: '[[[event.user_name]]]', desc: "Subscriber's display name" },
          { tag: '[[[event.tier]]]', desc: 'Sub tier (1000, 2000, 3000)', note: 'avoid' },
          { tag: '[[[event.tier_display]]]', desc: 'Sub display (1, 2, 3)', note: 'use' },
        ],
      },
      {
        heading: 'Subscription Data',
        tags: [
          { tag: '[[[event.cumulative_months]]]', desc: 'Total months subbed' },
          { tag: '[[[event.streak_months]]]', desc: 'Current streak' },
          { tag: '[[[event.duration_months]]]', desc: 'Months in this sub' },
          { tag: '[[[event.message.text]]]', desc: 'The resub message' },
        ],
      },
    ],
  },
  {
    id: 'channel-cheer',
    family: 'twitch_basic',
    title: 'Channel Cheer',
    type: 'channel.cheer',
    subtitle: 'When someone cheers bits',
    dot: 'bg-yellow-500',
    cols: [
      {
        heading: 'User Information',
        tags: [
          { tag: '[[[event.user_id]]]', desc: "Cheerer's Twitch ID" },
          { tag: '[[[event.user_login]]]', desc: "Cheerer's username" },
          { tag: '[[[event.user_name]]]', desc: "Cheerer's display name" },
        ],
      },
      {
        heading: 'Cheer Data',
        tags: [
          { tag: '[[[event.bits]]]', desc: 'Number of bits cheered' },
          { tag: '[[[event.message]]]', desc: 'Cheer message' },
          { tag: '[[[event.is_anonymous]]]', desc: 'true/false if anonymous' },
        ],
      },
    ],
    example: `[[[if:event.bits >= 1000]]]HUGE CHEER![[[endif]]] [[[event.user_name]]] cheered [[[event.bits]]] bits!`,
  },
  {
    id: 'channel-raid',
    family: 'twitch_basic',
    title: 'Channel Raid',
    type: 'channel.raid',
    subtitle: 'When another streamer raids your channel',
    dot: 'bg-red-500',
    cols: [
      {
        heading: 'Raider Information',
        tags: [
          { tag: '[[[event.from_broadcaster_user_id]]]', desc: "Raider's ID" },
          { tag: '[[[event.from_broadcaster_user_login]]]', desc: "Raider's username" },
          { tag: '[[[event.from_broadcaster_user_name]]]', desc: "Raider's name" },
        ],
      },
      {
        heading: 'Raid Data',
        tags: [
          { tag: '[[[event.viewers]]]', desc: 'Number of viewers in raid' },
          { tag: '[[[event.to_broadcaster_user_name]]]', desc: 'Your name' },
        ],
      },
    ],
  },
  {
    id: 'channel-points-redemption',
    family: 'twitch_basic',
    title: 'Channel Points Redemption',
    type: 'channel.channel_points_custom_reward_redemption.add',
    subtitle: 'When someone redeems a channel points reward',
    dot: 'bg-cyan-500',
    cols: [
      {
        heading: 'User Information',
        tags: [
          { tag: '[[[event.user_id]]]', desc: "Redeemer's Twitch ID" },
          { tag: '[[[event.user_login]]]', desc: "Redeemer's username" },
          { tag: '[[[event.user_name]]]', desc: "Redeemer's display name" },
          { tag: '[[[event.user_input]]]', desc: "User's input text" },
        ],
      },
      {
        heading: 'Reward Data',
        tags: [
          { tag: '[[[event.reward.title]]]', desc: 'Reward name' },
          { tag: '[[[event.reward.cost]]]', desc: 'Point cost' },
          { tag: '[[[event.reward.prompt]]]', desc: 'Reward description' },
          { tag: '[[[event.status]]]', desc: 'Fulfillment status' },
          { tag: '[[[event.redeemed_at]]]', desc: 'Timestamp' },
        ],
      },
    ],
  },

  // === Twitch Stream ===
  {
    id: 'stream-online',
    family: 'twitch_stream',
    title: 'Stream Online',
    type: 'stream.online',
    subtitle: 'When your stream goes live',
    dot: 'bg-green-400',
    cols: [
      {
        heading: 'Stream Information',
        tags: [
          { tag: '[[[event.id]]]', desc: 'Stream ID' },
          { tag: '[[[event.type]]]', desc: 'Stream type (usually "live")' },
          { tag: '[[[event.started_at]]]', desc: 'Stream start timestamp' },
        ],
      },
    ],
    note: {
      kind: 'info',
      text: 'Useful for logging but viewers probably will not see live alerts since the stream just started.',
    },
  },
  {
    id: 'stream-offline',
    family: 'twitch_stream',
    title: 'Stream Offline',
    type: 'stream.offline',
    subtitle: 'When your stream goes offline',
    dot: 'bg-red-400',
    cols: [
      {
        heading: 'Stream Information',
        tags: [
          { tag: '[[[event.broadcaster_user_id]]]', desc: 'Your Twitch ID' },
          { tag: '[[[event.broadcaster_user_login]]]', desc: 'Your username' },
          { tag: '[[[event.broadcaster_user_name]]]', desc: 'Your display name' },
        ],
      },
    ],
    note: {
      kind: 'warn',
      text: 'Useful for logging but viewers will not see alerts since the stream went offline.',
    },
  },
  {
    id: 'channel-update',
    family: 'twitch_stream',
    title: 'Stream Info Updated',
    type: 'channel.update',
    subtitle: 'When the title, category, or content labels change mid-stream',
    dot: 'bg-sky-400',
    cols: [
      {
        heading: 'Channel Information',
        tags: [
          { tag: '[[[event.broadcaster_user_id]]]', desc: 'Your Twitch ID' },
          { tag: '[[[event.broadcaster_user_login]]]', desc: 'Your username' },
          { tag: '[[[event.broadcaster_user_name]]]', desc: 'Your display name' },
        ],
      },
      {
        heading: 'Updated Fields',
        tags: [
          { tag: '[[[event.title]]]', desc: 'New stream title' },
          { tag: '[[[event.language]]]', desc: 'Language code (e.g. "en")' },
          { tag: '[[[event.category_id]]]', desc: 'New category/game ID' },
          { tag: '[[[event.category_name]]]', desc: 'New category/game name' },
        ],
      },
    ],
    example: `[[[if:event.category_name]]]
  <div class="now-playing">Now playing: [[[event.category_name]]]</div>
[[[endif]]]`,
  },

  // === Twitch Hype Train ===
  {
    id: 'hype-train-begin',
    family: 'twitch_hype_train',
    title: 'Hype Train Started',
    type: 'channel.hype_train.begin',
    subtitle: 'A hype train kicks off on your channel',
    dot: 'bg-orange-500',
    cols: [
      {
        heading: 'Train State',
        tags: [
          { tag: '[[[event.level]]]', desc: 'Starting level (usually 1)' },
          { tag: '[[[event.total]]]', desc: 'Total points contributed so far' },
          { tag: '[[[event.progress]]]', desc: 'Progress toward next level' },
          { tag: '[[[event.goal]]]', desc: 'Points needed for next level' },
          { tag: '[[[event.started_at]]]', desc: 'When the train started' },
          { tag: '[[[event.expires_at]]]', desc: 'When the train expires unless contributed to' },
        ],
      },
      {
        heading: 'Top & Last Contributor',
        tags: [
          { tag: '[[[event.last_contribution.user_name]]]', desc: 'Most recent contributor' },
          { tag: '[[[event.last_contribution.type]]]', desc: '"bits", "subscription", or "other"' },
          { tag: '[[[event.last_contribution.total]]]', desc: 'Their contribution amount' },
          { tag: '[[[event.top_contributions.count]]]', desc: 'How many top contributors are listed' },
          { tag: '[[[event.top_contributions.0.user_name]]]', desc: '#1 contributor name' },
          { tag: '[[[event.top_contributions.0.type]]]', desc: '#1 contribution type' },
          { tag: '[[[event.top_contributions.0.total]]]', desc: '#1 contribution total' },
        ],
      },
    ],
  },
  {
    id: 'hype-train-progress',
    family: 'twitch_hype_train',
    title: 'Hype Train Progress',
    type: 'channel.hype_train.progress',
    subtitle: 'A new contribution lands during an active train - fires frequently, budget for spam',
    dot: 'bg-orange-400',
    cols: [
      {
        heading: 'Train State',
        tags: [
          { tag: '[[[event.level]]]', desc: 'Current level' },
          { tag: '[[[event.total]]]', desc: 'Total points contributed so far' },
          { tag: '[[[event.progress]]]', desc: 'Progress toward next level' },
          { tag: '[[[event.goal]]]', desc: 'Points needed for next level' },
          { tag: '[[[event.expires_at]]]', desc: 'When the train expires' },
        ],
      },
      {
        heading: 'Top & Last Contributor',
        tags: [
          { tag: '[[[event.last_contribution.user_name]]]', desc: 'Who just contributed' },
          { tag: '[[[event.last_contribution.type]]]', desc: '"bits", "subscription", or "other"' },
          { tag: '[[[event.last_contribution.total]]]', desc: 'Their contribution amount' },
          { tag: '[[[event.top_contributions.0.user_name]]]', desc: '#1 contributor (also .1 and .2)' },
          { tag: '[[[event.top_contributions.0.total]]]', desc: '#1 contribution total' },
        ],
      },
    ],
    example: `<div class="hype-progress">
  Level [[[event.level]]] - [[[event.progress]]] / [[[event.goal]]]
  [[[if:event.last_contribution.user_name]]]
    <small>Last: [[[event.last_contribution.user_name]]]</small>
  [[[endif]]]
</div>`,
  },
  {
    id: 'hype-train-end',
    family: 'twitch_hype_train',
    title: 'Hype Train Ended',
    type: 'channel.hype_train.end',
    subtitle: 'The train finished - use the final level + top contributors for the "thanks" beat',
    dot: 'bg-orange-600',
    cols: [
      {
        heading: 'Final State',
        tags: [
          { tag: '[[[event.level]]]', desc: 'Final level reached' },
          { tag: '[[[event.total]]]', desc: 'Final total contributed' },
          { tag: '[[[event.started_at]]]', desc: 'When the train started' },
          { tag: '[[[event.ended_at]]]', desc: 'When it ended' },
          { tag: '[[[event.cooldown_ends_at]]]', desc: 'When the next train can start' },
        ],
      },
      {
        heading: 'Top Contributors',
        tags: [
          { tag: '[[[event.top_contributions.count]]]', desc: 'How many contributors are listed' },
          { tag: '[[[event.top_contributions.0.user_name]]]', desc: '#1 contributor name' },
          { tag: '[[[event.top_contributions.0.type]]]', desc: '#1 contribution type' },
          { tag: '[[[event.top_contributions.0.total]]]', desc: '#1 contribution total' },
          { tag: '[[[event.top_contributions.1.user_name]]]', desc: '#2 contributor' },
          { tag: '[[[event.top_contributions.2.user_name]]]', desc: '#3 contributor' },
        ],
      },
    ],
  },

  // === Twitch Charity ===
  {
    id: 'charity-donate',
    family: 'twitch_charity',
    title: 'Charity Donation',
    type: 'channel.charity_campaign.donate',
    subtitle: 'A viewer donated to the active charity campaign',
    dot: 'bg-rose-500',
    cols: [
      {
        heading: 'Donor & Campaign',
        tags: [
          { tag: '[[[event.user_name]]]', desc: "Donor's display name" },
          { tag: '[[[event.user_login]]]', desc: "Donor's username" },
          { tag: '[[[event.charity_name]]]', desc: 'Charity being donated to' },
          { tag: '[[[event.charity_description]]]', desc: 'Charity description' },
          { tag: '[[[event.charity_logo]]]', desc: 'Charity logo URL' },
          { tag: '[[[event.charity_website]]]', desc: 'Charity website URL' },
        ],
      },
      {
        heading: 'Amount',
        tags: [
          { tag: '[[[event.amount.formatted]]]', desc: 'Ready-to-display string (e.g. "$15.23")', note: 'use' },
          { tag: '[[[event.amount.value]]]', desc: 'Raw minor units (1523 = $15.23)' },
          { tag: '[[[event.amount.decimal_places]]]', desc: 'Decimal places (usually 2)' },
          { tag: '[[[event.amount.currency]]]', desc: 'Currency code ("USD", "EUR", etc.)' },
        ],
      },
    ],
    example: `<div class="charity-donation">
  [[[event.user_name]]] donated [[[event.amount.formatted]]] to [[[event.charity_name]]]!
</div>`,
  },
  {
    id: 'charity-start',
    family: 'twitch_charity',
    title: 'Charity Campaign Started',
    type: 'channel.charity_campaign.start',
    subtitle: 'A charity campaign begins on your channel',
    dot: 'bg-rose-400',
    cols: [
      {
        heading: 'Campaign',
        tags: [
          { tag: '[[[event.charity_name]]]', desc: 'Charity being raised for' },
          { tag: '[[[event.charity_description]]]', desc: 'Charity description' },
          { tag: '[[[event.charity_logo]]]', desc: 'Charity logo URL' },
          { tag: '[[[event.charity_website]]]', desc: 'Charity website URL' },
          { tag: '[[[event.started_at]]]', desc: 'When the campaign began' },
        ],
      },
      {
        heading: 'Goal',
        tags: [
          { tag: '[[[event.target_amount.formatted]]]', desc: 'Fundraising target (formatted)', note: 'use' },
          { tag: '[[[event.target_amount.value]]]', desc: 'Target in minor units' },
          { tag: '[[[event.target_amount.currency]]]', desc: 'Currency code' },
          { tag: '[[[event.current_amount.formatted]]]', desc: 'Raised so far (formatted)' },
        ],
      },
    ],
  },
  {
    id: 'charity-progress',
    family: 'twitch_charity',
    title: 'Charity Campaign Progress',
    type: 'channel.charity_campaign.progress',
    subtitle: 'Current vs. target update - fires on every donation, budget for spam',
    dot: 'bg-rose-300',
    cols: [
      {
        heading: 'Campaign',
        tags: [
          { tag: '[[[event.charity_name]]]', desc: 'Charity name' },
          { tag: '[[[event.charity_logo]]]', desc: 'Charity logo URL' },
        ],
      },
      {
        heading: 'Progress',
        tags: [
          { tag: '[[[event.current_amount.formatted]]]', desc: 'Raised so far (formatted)', note: 'use' },
          { tag: '[[[event.current_amount.value]]]', desc: 'Raised in minor units' },
          { tag: '[[[event.target_amount.formatted]]]', desc: 'Target (formatted)' },
          { tag: '[[[event.target_amount.value]]]', desc: 'Target in minor units' },
          { tag: '[[[event.target_amount.currency]]]', desc: 'Currency code' },
        ],
      },
    ],
    example: `<div class="charity-progress">
  [[[event.current_amount.formatted]]] raised of [[[event.target_amount.formatted]]]
</div>`,
  },
  {
    id: 'charity-stop',
    family: 'twitch_charity',
    title: 'Charity Campaign Ended',
    type: 'channel.charity_campaign.stop',
    subtitle: 'The campaign wrapped up - use the final totals for a thank-you alert',
    dot: 'bg-rose-700',
    cols: [
      {
        heading: 'Campaign',
        tags: [
          { tag: '[[[event.charity_name]]]', desc: 'Charity name' },
          { tag: '[[[event.charity_description]]]', desc: 'Charity description' },
          { tag: '[[[event.charity_logo]]]', desc: 'Charity logo URL' },
          { tag: '[[[event.stopped_at]]]', desc: 'When the campaign ended' },
        ],
      },
      {
        heading: 'Final Totals',
        tags: [
          { tag: '[[[event.current_amount.formatted]]]', desc: 'Final amount raised (formatted)', note: 'use' },
          { tag: '[[[event.current_amount.value]]]', desc: 'Final amount in minor units' },
          { tag: '[[[event.target_amount.formatted]]]', desc: 'Target (formatted)' },
        ],
      },
    ],
  },

  // === Twitch Goals ===
  {
    id: 'goal-begin',
    family: 'twitch_goals',
    title: 'Channel Goal Started',
    type: 'channel.goal.begin',
    subtitle: 'A follower, sub, or bits goal begins',
    dot: 'bg-emerald-500',
    cols: [
      {
        heading: 'Goal',
        tags: [
          { tag: '[[[event.type]]]', desc: '"follower", "subscription", "subscription_count", "new_subscription", or "new_subscription_count"' },
          { tag: '[[[event.description]]]', desc: 'Goal description (your custom text)' },
          { tag: '[[[event.current_amount]]]', desc: 'Starting amount (where the goal begins from)' },
          { tag: '[[[event.target_amount]]]', desc: 'Target to hit' },
          { tag: '[[[event.started_at]]]', desc: 'When the goal started' },
        ],
      },
    ],
  },
  {
    id: 'goal-progress',
    family: 'twitch_goals',
    title: 'Channel Goal Progress',
    type: 'channel.goal.progress',
    subtitle: 'Current amount updated - fires on every contribution, budget for spam',
    dot: 'bg-emerald-400',
    cols: [
      {
        heading: 'Goal',
        tags: [
          { tag: '[[[event.type]]]', desc: 'Goal type ("follower", "subscription", etc.)' },
          { tag: '[[[event.description]]]', desc: 'Goal description' },
          { tag: '[[[event.current_amount]]]', desc: 'Current value' },
          { tag: '[[[event.target_amount]]]', desc: 'Target value' },
        ],
      },
    ],
    example: `<div class="goal-bar">
  [[[event.description]]]: [[[event.current_amount]]] / [[[event.target_amount]]]
</div>`,
  },
  {
    id: 'goal-end',
    family: 'twitch_goals',
    title: 'Channel Goal Ended',
    type: 'channel.goal.end',
    subtitle: 'Goal completed or expired - is_achieved tells you which',
    dot: 'bg-emerald-600',
    cols: [
      {
        heading: 'Goal',
        tags: [
          { tag: '[[[event.type]]]', desc: 'Goal type' },
          { tag: '[[[event.description]]]', desc: 'Goal description' },
          { tag: '[[[event.is_achieved]]]', desc: 'true if goal was hit, false if it expired' },
          { tag: '[[[event.current_amount]]]', desc: 'Final value' },
          { tag: '[[[event.target_amount]]]', desc: 'Target value' },
          { tag: '[[[event.started_at]]]', desc: 'When the goal started' },
          { tag: '[[[event.ended_at]]]', desc: 'When the goal ended' },
        ],
      },
    ],
    example: `[[[if:event.is_achieved]]]
  <div class="goal-hit">[[[event.description]]] - HIT!</div>
[[[else]]]
  <div class="goal-miss">[[[event.description]]] ended at [[[event.current_amount]]] / [[[event.target_amount]]]</div>
[[[endif]]]`,
  },

  // === Twitch Polls ===
  {
    id: 'poll-begin',
    family: 'twitch_polls',
    title: 'Poll Started',
    type: 'channel.poll.begin',
    subtitle: 'A poll opens with up to 5 choices',
    dot: 'bg-blue-500',
    cols: [
      {
        heading: 'Poll',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Poll question' },
          { tag: '[[[event.started_at]]]', desc: 'When the poll opened' },
          { tag: '[[[event.ends_at]]]', desc: 'When the poll closes' },
        ],
      },
      {
        heading: 'Choices & Voting',
        tags: [
          { tag: '[[[event.choices.count]]]', desc: 'How many choices (max 5)' },
          { tag: '[[[event.choices.0.title]]]', desc: 'First choice title (also .1 to .4)' },
          { tag: '[[[event.channel_points_voting.is_enabled]]]', desc: 'true if channel points can vote' },
          { tag: '[[[event.channel_points_voting.amount_per_vote]]]', desc: 'Points per channel-points vote' },
          { tag: '[[[event.bits_voting.is_enabled]]]', desc: 'true if bits can vote (legacy)' },
        ],
      },
    ],
  },
  {
    id: 'poll-progress',
    family: 'twitch_polls',
    title: 'Poll Progress',
    type: 'channel.poll.progress',
    subtitle: 'Mid-poll vote count update - fires frequently as votes come in',
    dot: 'bg-blue-400',
    cols: [
      {
        heading: 'Poll',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Poll question' },
          { tag: '[[[event.ends_at]]]', desc: 'When the poll closes' },
        ],
      },
      {
        heading: 'Choices',
        tags: [
          { tag: '[[[event.choices.count]]]', desc: 'How many choices' },
          { tag: '[[[event.choices.total_votes]]]', desc: 'Total votes across all choices (use as denominator for progress bars)' },
          { tag: '[[[event.choices.total_channel_points_votes]]]', desc: 'Total channel-points votes across all choices' },
          { tag: '[[[event.choices.0.title]]]', desc: 'First choice title' },
          { tag: '[[[event.choices.0.votes]]]', desc: 'First choice total votes' },
          { tag: '[[[event.choices.0.channel_points_votes]]]', desc: 'Channel-points votes for #0' },
          { tag: '[[[event.choices.1.title]]]', desc: 'Second choice title (also .2 to .4)' },
          { tag: '[[[event.choices.1.votes]]]', desc: 'Second choice votes' },
        ],
      },
    ],
  },
  {
    id: 'poll-end',
    family: 'twitch_polls',
    title: 'Poll Ended',
    type: 'channel.poll.end',
    subtitle: 'Final results - status tells you if it completed naturally or was cut short',
    dot: 'bg-blue-600',
    cols: [
      {
        heading: 'Poll',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Poll question' },
          { tag: '[[[event.status]]]', desc: '"completed", "terminated", or "archived"' },
          { tag: '[[[event.started_at]]]', desc: 'When the poll opened' },
          { tag: '[[[event.ended_at]]]', desc: 'When the poll ended' },
        ],
      },
      {
        heading: 'Final Choices',
        tags: [
          { tag: '[[[event.choices.count]]]', desc: 'How many choices' },
          { tag: '[[[event.choices.total_votes]]]', desc: 'Final total votes across all choices' },
          { tag: '[[[event.choices.0.title]]]', desc: 'First choice title' },
          { tag: '[[[event.choices.0.votes]]]', desc: 'First choice final vote count' },
          { tag: '[[[event.choices.1.title]]]', desc: 'Second choice title (also .2 to .4)' },
          { tag: '[[[event.choices.1.votes]]]', desc: 'Second choice final votes' },
        ],
      },
    ],
    example: `[[[if:event.status = completed]]]
  <div class="poll-done">Poll ended: [[[event.title]]]</div>
[[[elseif:event.status = terminated]]]
  <div class="poll-cut">Poll cut short: [[[event.title]]]</div>
[[[endif]]]`,
  },

  // === Twitch Predictions ===
  {
    id: 'prediction-begin',
    family: 'twitch_predictions',
    title: 'Prediction Started',
    type: 'channel.prediction.begin',
    subtitle: 'A prediction opens with up to 10 outcomes',
    dot: 'bg-fuchsia-500',
    cols: [
      {
        heading: 'Prediction',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Prediction question' },
          { tag: '[[[event.started_at]]]', desc: 'When it opened' },
          { tag: '[[[event.locks_at]]]', desc: 'When predictions close' },
        ],
      },
      {
        heading: 'Outcomes',
        tags: [
          { tag: '[[[event.outcomes.count]]]', desc: 'How many outcomes (max 10)' },
          { tag: '[[[event.outcomes.0.title]]]', desc: 'First outcome title (also .1 to .9)' },
          { tag: '[[[event.outcomes.0.color]]]', desc: '"blue" or "pink"' },
        ],
      },
    ],
  },
  {
    id: 'prediction-progress',
    family: 'twitch_predictions',
    title: 'Prediction Progress',
    type: 'channel.prediction.progress',
    subtitle: 'Update with current predictor counts - fires frequently',
    dot: 'bg-fuchsia-400',
    cols: [
      {
        heading: 'Prediction',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Prediction question' },
          { tag: '[[[event.locks_at]]]', desc: 'When predictions close' },
        ],
      },
      {
        heading: 'Outcomes',
        tags: [
          { tag: '[[[event.outcomes.count]]]', desc: 'How many outcomes' },
          { tag: '[[[event.outcomes.total_users]]]', desc: 'Total predictors across all outcomes' },
          { tag: '[[[event.outcomes.total_channel_points]]]', desc: 'Total channel points wagered across all outcomes' },
          { tag: '[[[event.outcomes.0.title]]]', desc: 'First outcome title' },
          { tag: '[[[event.outcomes.0.color]]]', desc: '"blue" or "pink"' },
          { tag: '[[[event.outcomes.0.users]]]', desc: 'Number of predictors on #0' },
          { tag: '[[[event.outcomes.0.channel_points]]]', desc: 'Total channel points on #0' },
          { tag: '[[[event.outcomes.1.title]]]', desc: 'Second outcome title (also .2 to .9)' },
          { tag: '[[[event.outcomes.1.users]]]', desc: 'Predictors on #1' },
        ],
      },
    ],
  },
  {
    id: 'prediction-lock',
    family: 'twitch_predictions',
    title: 'Prediction Locked',
    type: 'channel.prediction.lock',
    subtitle: 'Predictions close - waiting for the streamer to resolve',
    dot: 'bg-fuchsia-600',
    cols: [
      {
        heading: 'Prediction',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Prediction question' },
          { tag: '[[[event.locked_at]]]', desc: 'When it locked' },
        ],
      },
      {
        heading: 'Final Outcomes',
        tags: [
          { tag: '[[[event.outcomes.count]]]', desc: 'How many outcomes' },
          { tag: '[[[event.outcomes.total_users]]]', desc: 'Total predictors across all outcomes' },
          { tag: '[[[event.outcomes.total_channel_points]]]', desc: 'Total channel points wagered across all outcomes' },
          { tag: '[[[event.outcomes.0.title]]]', desc: 'First outcome title' },
          { tag: '[[[event.outcomes.0.users]]]', desc: 'Final predictor count on #0' },
          { tag: '[[[event.outcomes.0.channel_points]]]', desc: 'Final channel points on #0' },
          { tag: '[[[event.outcomes.1.title]]]', desc: 'Second outcome title (also .2 to .9)' },
        ],
      },
    ],
  },
  {
    id: 'prediction-end',
    family: 'twitch_predictions',
    title: 'Prediction Ended',
    type: 'channel.prediction.end',
    subtitle: 'Winning outcome + payouts - or canceled if refunded',
    dot: 'bg-fuchsia-700',
    cols: [
      {
        heading: 'Prediction',
        tags: [
          { tag: '[[[event.title]]]', desc: 'Prediction question' },
          { tag: '[[[event.status]]]', desc: '"resolved" or "canceled"' },
          { tag: '[[[event.winning_outcome_id]]]', desc: 'ID of the winning outcome (resolved only)' },
          { tag: '[[[event.started_at]]]', desc: 'When it opened' },
          { tag: '[[[event.ended_at]]]', desc: 'When it ended' },
        ],
      },
      {
        heading: 'Final Outcomes',
        tags: [
          { tag: '[[[event.outcomes.count]]]', desc: 'How many outcomes' },
          { tag: '[[[event.outcomes.total_users]]]', desc: 'Final total predictors across all outcomes' },
          { tag: '[[[event.outcomes.total_channel_points]]]', desc: 'Final total channel points wagered' },
          { tag: '[[[event.outcomes.0.title]]]', desc: 'First outcome title' },
          { tag: '[[[event.outcomes.0.users]]]', desc: 'Final predictor count on #0' },
          { tag: '[[[event.outcomes.0.channel_points]]]', desc: 'Final channel points on #0' },
          { tag: '[[[event.outcomes.1.title]]]', desc: 'Second outcome title (also .2 to .9)' },
        ],
      },
    ],
    example: `[[[if:event.status = resolved]]]
  <div class="prediction-resolved">Winner: [[[event.outcomes.0.title]]]</div>
[[[elseif:event.status = canceled]]]
  <div class="prediction-canceled">Prediction canceled - refunded</div>
[[[endif]]]`,
  },

  // === Ko-fi ===
  {
    id: 'kofi-controls',
    family: 'kofi',
    title: 'Ko-fi Auto-provisioned Controls',
    subtitle: 'Six controls are created on connect and kept up to date with every donation, subscription, shop order, or commission',
    dot: 'bg-orange-400',
    cols: [
      {
        heading: 'Use in any template with the [[[c:kofi:key]]] syntax',
        tags: [
          { tag: '[[[c:kofi:donations_received]]]', desc: 'Total count of Ko-fi events received (counter)' },
          { tag: '[[[c:kofi:latest_donor_name]]]', desc: 'Name of the most recent supporter' },
          { tag: '[[[c:kofi:latest_donation_amount]]]', desc: 'Amount of the most recent payment' },
          { tag: '[[[c:kofi:latest_donation_message]]]', desc: 'Message from the most recent supporter' },
          { tag: '[[[c:kofi:latest_donation_currency]]]', desc: 'Currency of the most recent payment (e.g. USD)' },
          { tag: '[[[c:kofi:total_received]]]', desc: 'Running total of all Ko-fi amounts (session)' },
        ],
      },
    ],
    note: { kind: 'info', text: 'Ko-fi, StreamLabs, StreamElements, and Fourthwall share a unified control schema - the six keys are identical across all four integrations, so you can swap the prefix (c:kofi:, c:streamlabs:, c:streamelements:, c:fourthwall:) and the template keeps working.' },
  },
  {
    id: 'kofi-all',
    family: 'kofi',
    title: 'All Ko-fi Events',
    subtitle: 'Available on every Ko-fi event type (donation, subscription, shop_order, commission)',
    dot: 'bg-orange-400',
    cols: [
      {
        heading: 'Common Tags',
        tags: [
          { tag: '[[[event.from_name]]]', desc: 'Name of the supporter' },
          { tag: '[[[event.source]]]', desc: 'Display name of the platform (e.g. "Ko-fi") - useful for reusing templates across donation services' },
          { tag: '[[[event.type]]]', desc: 'Normalized type: donation, subscription, shop_order, or commission' },
          { tag: '[[[event.transaction_id]]]', desc: 'Unique Ko-fi transaction ID' },
          { tag: '[[[event.url]]]', desc: "Supporter's Ko-fi page URL" },
        ],
      },
    ],
  },
  {
    id: 'kofi-donation-subscription',
    family: 'kofi',
    title: 'Ko-fi Donation & Subscription Events',
    subtitle: 'Additional tags available for donation and subscription events',
    dot: 'bg-yellow-400',
    cols: [
      {
        heading: 'Payment Tags',
        tags: [
          { tag: '[[[event.message]]]', desc: "Supporter's message" },
          { tag: '[[[event.amount]]]', desc: 'Amount as a string (e.g. "5.00")' },
          { tag: '[[[event.currency]]]', desc: 'Currency code (e.g. "USD")' },
        ],
      },
    ],
    example: `<div class="donor">[[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!</div>
<div class="message">[[[if:event.message]]][[[event.message]]][[[endif]]]</div>`,
  },
  {
    id: 'kofi-subscription',
    family: 'kofi',
    title: 'Ko-fi Subscription-Only Tags',
    subtitle: 'Extra tags exclusive to Ko-fi subscription events',
    dot: 'bg-purple-400',
    cols: [
      {
        heading: 'Subscription Tags',
        tags: [
          { tag: '[[[event.tier_name]]]', desc: 'Subscription tier name' },
          { tag: '[[[event.is_first_sub]]]', desc: '"1" if first payment, "0" otherwise' },
          { tag: '[[[event.is_subscription]]]', desc: 'Always "1" for subscription events' },
        ],
      },
    ],
  },

  // === Buy Me a Coffee ===
  {
    id: 'bmac-controls',
    family: 'bmac',
    title: 'Buy Me a Coffee Auto-provisioned Controls',
    subtitle: 'Seven controls track every BMAC event - donations, commissions, extras, memberships, monthly support, and wishlist payments',
    dot: 'bg-yellow-300',
    cols: [
      {
        heading: 'Use in any template with the [[[c:bmac:key]]] syntax',
        tags: [
          { tag: '[[[c:bmac:donations_received]]]', desc: 'Total count of BMAC events received (counter, increments on every event type)' },
          { tag: '[[[c:bmac:latest_donor_name]]]', desc: 'Name of the most recent supporter' },
          { tag: '[[[c:bmac:latest_donation_amount]]]', desc: 'Top-level amount paid for the most recent event (includes shipping/extras for orders)' },
          { tag: '[[[c:bmac:latest_donation_message]]]', desc: "Supporter's note - empty when supporter chose to keep it private (note_hidden)" },
          { tag: '[[[c:bmac:latest_donation_currency]]]', desc: 'Currency of the most recent payment (e.g. USD)' },
          { tag: '[[[c:bmac:total_received]]]', desc: 'Running total of every BMAC payment (session)' },
          { tag: '[[[c:bmac:latest_support_type]]]', desc: 'Type of the most recent support: Supporter, Commission, Extra, Membership, Subscription, or Wishlist' },
        ],
      },
    ],
    note: { kind: 'info', text: 'BMAC shares the same six core control keys as Ko-fi, StreamLabs, StreamElements, and Fourthwall. Swap the prefix (c:kofi:, c:bmac:, etc.) and the same template renders for all five integrations. Use latest()/oldest() over the _at companion timestamps to pick the most recent supporter across services.' },
  },
  {
    id: 'bmac-all',
    family: 'bmac',
    title: 'All BMAC Events',
    subtitle: 'Available on every BMAC event (donation, commission, extra, membership, recurring, wishlist)',
    dot: 'bg-yellow-300',
    cols: [
      {
        heading: 'Common Tags',
        tags: [
          { tag: '[[[event.from_name]]]', desc: 'Name of the supporter (data.supporter_name)' },
          { tag: '[[[event.source]]]', desc: 'Always "Buy Me a Coffee" - useful for reusing templates across donation services' },
          { tag: '[[[event.type]]]', desc: 'Normalized type: donation, commission, extra, membership, recurring, or wishlist' },
          { tag: '[[[event.support_type]]]', desc: 'Human label from BMAC (Supporter, Commission, Extra, Membership, Subscription, Wishlist)' },
          { tag: '[[[event.transaction_id]]]', desc: "BMAC transaction_id, or psp_id for memberships and monthly support" },
          { tag: '[[[event.message]]]', desc: 'BMAC-generated description (e.g. "John bought you a coffee")' },
          { tag: '[[[event.live_mode]]]', desc: '"1" for live events, "0" for BMAC test mode' },
        ],
      },
    ],
  },
  {
    id: 'bmac-payment',
    family: 'bmac',
    title: 'BMAC Payment Tags',
    subtitle: 'Money- and message-shaped tags emitted by every event type',
    dot: 'bg-yellow-400',
    cols: [
      {
        heading: 'Payment Tags',
        tags: [
          { tag: '[[[event.amount]]]', desc: 'Top-level amount as string (e.g. "5.00") - matches what BMAC reports on the dashboard' },
          { tag: '[[[event.currency]]]', desc: 'Currency code (e.g. "USD")' },
          { tag: '[[[event.support_note]]]', desc: "Supporter's private note. Empty when note_hidden is true (memberships and monthly support)" },
          { tag: '[[[event.coffee_count]]]', desc: 'Number of coffees purchased (donation events only)' },
          { tag: '[[[event.commission_name]]]', desc: 'Commission product name (commission events only)' },
          { tag: '[[[event.wishlist_title]]]', desc: 'Wishlist item title (wishlist events only)' },
          { tag: '[[[event.extras_title]]]', desc: 'First extra purchased (extra events only)' },
        ],
      },
    ],
    example: `<div class="donor">[[[event.from_name]]] sent [[[event.amount]]] [[[event.currency]]]!</div>
<div class="message">[[[if:event.support_note]]][[[event.support_note]]][[[endif]]]</div>`,
  },
  {
    id: 'bmac-recurring',
    family: 'bmac',
    title: 'BMAC Recurring & Membership Tags',
    subtitle: 'Distinguish one-off support from monthly support and membership tiers',
    dot: 'bg-purple-400',
    cols: [
      {
        heading: 'Recurring Tags',
        tags: [
          { tag: '[[[event.is_recurring]]]', desc: '"1" for membership and monthly support, "0" otherwise' },
          { tag: '[[[event.is_membership]]]', desc: '"1" only for membership events (use to read membership_level_name from raw payload)' },
        ],
      },
    ],
  },

  // === StreamLabs ===
  {
    id: 'streamlabs-controls',
    family: 'streamlabs',
    title: 'StreamLabs Auto-provisioned Controls',
    subtitle: 'Six controls are created on connect and kept up to date with every donation',
    dot: 'bg-emerald-500',
    cols: [
      {
        heading: 'Use in any template with the [[[c:streamlabs:key]]] syntax',
        tags: [
          { tag: '[[[c:streamlabs:donations_received]]]', desc: 'Total number of donations received (counter)' },
          { tag: '[[[c:streamlabs:latest_donor_name]]]', desc: 'Name of the most recent donor' },
          { tag: '[[[c:streamlabs:latest_donation_amount]]]', desc: 'Amount of the most recent donation' },
          { tag: '[[[c:streamlabs:latest_donation_message]]]', desc: 'Message from the most recent donor' },
          { tag: '[[[c:streamlabs:latest_donation_currency]]]', desc: 'Currency of the most recent donation (e.g. USD)' },
          { tag: '[[[c:streamlabs:total_received]]]', desc: 'Running total of all donation amounts (session)' },
        ],
      },
    ],
    note: { kind: 'info', text: 'StreamLabs, Ko-fi, StreamElements, and Fourthwall share a unified control schema - the six keys are identical across all four integrations, so you can swap the prefix (c:streamlabs:, c:kofi:, c:streamelements:, c:fourthwall:) and the template keeps working.' },
  },
  {
    id: 'streamlabs-donation',
    family: 'streamlabs',
    title: 'StreamLabs Donation Event Tags',
    subtitle: 'Available in alert templates triggered by StreamLabs donations',
    dot: 'bg-emerald-500',
    cols: [
      {
        heading: 'Event Tags',
        tags: [
          { tag: '[[[event.from_name]]]', desc: 'Name of the donor' },
          { tag: '[[[event.message]]]', desc: "Donor's message" },
          { tag: '[[[event.amount]]]', desc: 'Donation amount (e.g. "5.00")' },
          { tag: '[[[event.currency]]]', desc: 'Currency code (e.g. "USD")' },
          { tag: '[[[event.formatted_amount]]]', desc: 'Formatted amount (e.g. "$5.00")' },
          { tag: '[[[event.type]]]', desc: 'Always "donation"' },
          { tag: '[[[event.source]]]', desc: 'Always "StreamLabs" - useful for reusing alert templates across donation services' },
          { tag: '[[[event.transaction_id]]]', desc: 'Unique event identifier' },
        ],
      },
    ],
    example: `<div class="donation">
  [[[event.from_name]]] donated [[[event.formatted_amount]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>`,
  },

  // === StreamElements ===
  {
    id: 'streamelements-controls',
    family: 'streamelements',
    title: 'StreamElements Auto-provisioned Controls',
    subtitle: 'Six controls are created on connect and kept up to date with every tip',
    dot: 'bg-teal-500',
    cols: [
      {
        heading: 'Use in any template with the [[[c:streamelements:key]]] syntax',
        tags: [
          { tag: '[[[c:streamelements:donations_received]]]', desc: 'Total number of tips received (counter)' },
          { tag: '[[[c:streamelements:latest_donor_name]]]', desc: 'Name of the most recent tipper' },
          { tag: '[[[c:streamelements:latest_donation_amount]]]', desc: 'Amount of the most recent tip' },
          { tag: '[[[c:streamelements:latest_donation_message]]]', desc: 'Message from the most recent tipper' },
          { tag: '[[[c:streamelements:latest_donation_currency]]]', desc: 'Currency of the most recent tip (e.g. USD)' },
          { tag: '[[[c:streamelements:total_received]]]', desc: 'Running total of all tip amounts (session)' },
        ],
      },
    ],
    note: { kind: 'info', text: 'StreamElements, Ko-fi, StreamLabs, and Fourthwall share a unified control schema - the six keys are identical across all four integrations, so you can swap the prefix (c:streamelements:, c:kofi:, c:streamlabs:, c:fourthwall:) and the template keeps working.' },
  },
  {
    id: 'streamelements-donation',
    family: 'streamelements',
    title: 'StreamElements Tip Event Tags',
    subtitle: 'Available in alert templates triggered by StreamElements tips',
    dot: 'bg-teal-500',
    cols: [
      {
        heading: 'Event Tags',
        tags: [
          { tag: '[[[event.from_name]]]', desc: 'Name of the tipper' },
          { tag: '[[[event.message]]]', desc: "Tipper's message" },
          { tag: '[[[event.amount]]]', desc: 'Tip amount (e.g. "5.00")' },
          { tag: '[[[event.currency]]]', desc: 'Currency code (e.g. "USD")' },
          { tag: '[[[event.formatted_amount]]]', desc: 'Formatted amount (e.g. "$5.00")' },
          { tag: '[[[event.type]]]', desc: 'Always "donation" (SE "tip" is normalized to "donation")' },
          { tag: '[[[event.source]]]', desc: 'Always "StreamElements" - useful for reusing alert templates across donation services' },
          { tag: '[[[event.transaction_id]]]', desc: 'Unique event identifier' },
        ],
      },
    ],
    example: `<div class="donation">
  [[[event.from_name]]] tipped [[[event.formatted_amount]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>`,
  },

  // === Fourthwall ===
  {
    id: 'fourthwall-controls',
    family: 'fourthwall',
    title: 'Fourthwall Auto-provisioned Controls',
    subtitle: 'Six controls are created on connect and kept up to date with every donation',
    dot: 'bg-sky-500',
    cols: [
      {
        heading: 'Use in any template with the [[[c:fourthwall:key]]] syntax',
        tags: [
          { tag: '[[[c:fourthwall:donations_received]]]', desc: 'Total number of donations received (counter)' },
          { tag: '[[[c:fourthwall:latest_donor_name]]]', desc: 'Name of the most recent donor' },
          { tag: '[[[c:fourthwall:latest_donation_amount]]]', desc: 'Amount of the most recent donation' },
          { tag: '[[[c:fourthwall:latest_donation_message]]]', desc: 'Message from the most recent donor' },
          { tag: '[[[c:fourthwall:latest_donation_currency]]]', desc: 'Currency of the most recent donation (e.g. USD)' },
          { tag: '[[[c:fourthwall:total_received]]]', desc: 'Running total of all donation amounts (session)' },
        ],
      },
    ],
    note: { kind: 'info', text: 'Fourthwall, Ko-fi, StreamLabs, and StreamElements share a unified control schema - the six keys are identical across all four integrations, so you can swap the prefix (c:fourthwall:, c:kofi:, c:streamlabs:, c:streamelements:) and the template keeps working.' },
  },
  {
    id: 'fourthwall-donation',
    family: 'fourthwall',
    title: 'Fourthwall Donation Event Tags',
    subtitle: 'Available in alert templates triggered by Fourthwall donations',
    dot: 'bg-sky-500',
    cols: [
      {
        heading: 'Event Tags',
        tags: [
          { tag: '[[[event.from_name]]]', desc: 'Name of the donor' },
          { tag: '[[[event.message]]]', desc: "Donor's message" },
          { tag: '[[[event.amount]]]', desc: 'Donation amount (e.g. "10")' },
          { tag: '[[[event.currency]]]', desc: 'Currency code (e.g. "USD")' },
          { tag: '[[[event.type]]]', desc: 'Always "donation"' },
          { tag: '[[[event.source]]]', desc: 'Always "Fourthwall" - useful for reusing alert templates across donation services' },
          { tag: '[[[event.status]]]', desc: 'Donation lifecycle state (e.g. "OPEN") - Fourthwall-specific' },
          { tag: '[[[event.transaction_id]]]', desc: 'Unique donation identifier (e.g. don_...)' },
        ],
      },
    ],
    example: `<div class="donation">
  [[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>`,
  },
];

const searchQuery = ref('');
const activeFamily = ref<FamilyKey | 'all'>('all');

const filteredCards = computed(() => {
  const q = searchQuery.value.toLowerCase().trim();
  return cards.filter((card) => {
    if (activeFamily.value !== 'all' && card.family !== activeFamily.value) return false;
    if (!q) return true;
    const hay = [
      card.title,
      card.type ?? '',
      card.subtitle,
      FAMILY_LABELS[card.family],
      ...card.cols.flatMap((c) => [c.heading, ...c.tags.flatMap((t) => [t.tag, t.desc])]),
    ]
      .join(' ')
      .toLowerCase();
    return hay.includes(q);
  });
});

const familyCounts = computed(() => {
  const q = searchQuery.value.toLowerCase().trim();
  const counts: Record<FamilyKey, number> = {
    twitch_basic: 0,
    twitch_stream: 0,
    twitch_hype_train: 0,
    twitch_charity: 0,
    twitch_goals: 0,
    twitch_polls: 0,
    twitch_predictions: 0,
    kofi: 0,
    streamlabs: 0,
    streamelements: 0,
    fourthwall: 0,
    bmac: 0,
  };
  for (const card of cards) {
    if (!q) {
      counts[card.family]++;
      continue;
    }
    const hay = [
      card.title,
      card.type ?? '',
      card.subtitle,
      FAMILY_LABELS[card.family],
      ...card.cols.flatMap((c) => [c.heading, ...c.tags.flatMap((t) => [t.tag, t.desc])]),
    ]
      .join(' ')
      .toLowerCase();
    if (hay.includes(q)) counts[card.family]++;
  }
  return counts;
});

function setFamily(family: FamilyKey | 'all') {
  activeFamily.value = family;
}

function clearFilter() {
  searchQuery.value = '';
  activeFamily.value = 'all';
}
</script>

<template>
  <Head>
    <title>Help - Overlabels</title>
    <meta
      name="description"
      content="Complete reference for conditional template tags, event data, Ko-fi, StreamLabs, StreamElements, and Fourthwall integration tags in Overlabels overlays."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/conditionals" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Help - Overlabels" />
    <meta
      property="og:description"
      content="Complete reference for conditional template tags, event data, Ko-fi, StreamLabs, StreamElements, and Fourthwall integration tags in Overlabels overlays."
    />
    <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Help - Overlabels" />
    <meta
      name="twitter:description"
      content="Complete reference for conditional template tags, event data, Ko-fi, StreamLabs, StreamElements, and Fourthwall integration tags in Overlabels overlays."
    />
    <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-12">
          <Heading
            title="Conditional Tags Reference"
            title-class="text-4xl font-bold mb-4"
            description="Complete reference for conditional template tags, event data, Ko-fi, StreamLabs, StreamElements, and Fourthwall integration tags in Overlabels overlays."
          />
          <p class="mt-4 text-foreground">
            See your
            <a href="/tags" class="cursor-pointer text-violet-400 hover:underline">static Template Tags</a>
            for your account. Need to format numbers, durations, or currencies? See
            <Link href="/help/formatting" class="cursor-pointer text-violet-400 hover:underline">Formatting Pipes</Link>.
          </p>
        </div>

        <!-- Conditional Syntax Section (static, always visible) -->
        <div class="mb-12" id="conditionals">
          <h2 class="mb-6 text-2xl font-bold">Conditional Template Syntax</h2>
          <p class="mb-6 text-foreground">
            Use conditional logic to dynamically show or hide content in your overlays based on real-time data. All conditionals are processed
            client-side for security.
          </p>

          <div class="space-y-8">
            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-4 text-xl font-semibold">Boolean Conditions</h3>
              <p class="mb-4 text-foreground">
                Test if a value exists and is truthy. Values considered false: <code>null</code>, <code>undefined</code>, <code>""</code>,
                <code>"false"</code>, <code>"0"</code>
              </p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">[[[if:channel_is_branded]]]
  &lt;p&gt;This stream is sponsored!&lt;/p&gt;
[[[endif]]]</pre>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-4 text-xl font-semibold">Numerical Comparisons</h3>
              <p class="mb-4 text-foreground">
                Compare numbers using standard operators: <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code>,
                <code>!=</code>, <code>=</code>
              </p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">[[[if:followers_total >= 1000]]]
  &lt;div class="milestone"&gt;1K+ followers!&lt;/div&gt;
[[[elseif:followers_total >= 100]]]
  &lt;div&gt;Growing strong with [[[followers_total]]] followers&lt;/div&gt;
[[[else]]]
  &lt;div&gt;Help us reach 100 followers!&lt;/div&gt;
[[[endif]]]</pre>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-4 text-xl font-semibold">String Comparisons</h3>
              <p class="mb-4 text-foreground">Compare text values using <code>=</code> and <code>!=</code> operators.</p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">[[[if:channel_language = en]]]
  &lt;p&gt;Welcome to our English stream!&lt;/p&gt;
[[[elseif:channel_language = es]]]
  &lt;p&gt;¡Bienvenidos a nuestro stream en Español!&lt;/p&gt;
[[[endif]]]</pre>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-4 text-xl font-semibold">Event-based Conditionals</h3>
              <p class="mb-4 text-foreground">
                Use event data in alert templates to create dynamic alerts based on donation/subscription amounts, viewer counts, etc.
              </p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">[[[if:event.bits >= 1000]]]
  &lt;div class="big-cheer"&gt;HUGE CHEER! [[[event.user_name]]] donated [[[event.bits]]] bits!&lt;/div&gt;
[[[elseif:event.bits >= 100]]]
  &lt;div class="medium-cheer"&gt;Thanks [[[event.user_name]]] for [[[event.bits]]] bits!&lt;/div&gt;
[[[else]]]
  &lt;div&gt;[[[event.user_name]]] cheered with [[[event.bits]]] bits!&lt;/div&gt;
[[[endif]]]</pre>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-4 text-xl font-semibold">Nested Conditionals</h3>
              <p class="mb-4 text-foreground">You can nest conditionals up to 10 levels deep for complex logic.</p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">[[[if:event.tier = 3000]]]
  [[[if:event.total >= 10]]]
    &lt;div&gt;Tier 3 gift bomb! [[[event.total]]] subs!&lt;/div&gt;
  [[[else]]]
    &lt;div&gt;Tier 3 gift: [[[event.total]]] subs&lt;/div&gt;
  [[[endif]]]
[[[endif]]]</pre>
            </div>

            <div class="border border-sidebar-border bg-card p-6" id="foreach-loops">
              <h3 class="mb-4 text-xl font-semibold">Foreach Loops</h3>
              <p class="mb-4 text-foreground">
                Repeat a block of markup for every item in a list. Use this for poll choices, prediction outcomes, hype-train
                contributors - anything where the server sends indexed entries (<code>event.choices.0.title</code>,
                <code>event.choices.1.title</code>, ...) plus a <code>.count</code>.
              </p>
              <p class="mb-4 text-foreground">
                Inside the loop body you can reference the current item through the alias you named after <code>as</code>,
                plus these loop metadata tokens:
              </p>
              <ul class="mb-4 ml-6 list-disc text-foreground">
                <li><code>[[[loop.index]]]</code> - zero-based iteration index</li>
                <li><code>[[[loop.first]]]</code> / <code>[[[loop.last]]]</code> - booleans, handy with <code>[[[if:...]]]</code></li>
                <li><code>[[[loop.count]]]</code> - total number of items</li>
              </ul>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">&lt;ul&gt;
  [[[foreach:event.choices as choice]]]
    &lt;li class="[[[if:loop.first]]]first[[[endif]]]"&gt;
      [[[loop.index]]]. [[[choice.title]]] - [[[choice.votes]]] votes
    &lt;/li&gt;
  [[[endforeach]]]
&lt;/ul&gt;</pre>
              <p class="mt-4 text-sm text-foreground">
                Loops can be nested, and you can use <code>[[[if:...]]]</code> inside a loop body (as shown above).
                Non-scoped tokens like <code>[[[event.title]]]</code> still work inside the body.
              </p>

              <h4 class="mt-6 mb-3 text-lg font-semibold">Iterable collections</h4>
              <p class="mb-3 text-foreground">
                These are the collections you can put on the right-hand side of <code>[[[foreach:X as Y]]]</code>.
                Event collections follow Twitch's own limits. User-scope collections obey the caps on your
                <Link :href="route('settings.account')" class="underline hover:no-underline">Account settings page</Link>
                (hard maximum 50 per loop).
              </p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-sidebar text-left">
                      <th class="py-2 pr-4 font-semibold">Iterable</th>
                      <th class="py-2 pr-4 font-semibold">Scope</th>
                      <th class="py-2 pr-4 font-semibold">Cap source</th>
                      <th class="py-2 font-semibold">Use in alert or static?</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="border-b border-sidebar">
                      <td class="py-2 pr-4"><code>event.choices</code></td>
                      <td class="py-2 pr-4">Poll alert</td>
                      <td class="py-2 pr-4">5 (Twitch limit)</td>
                      <td class="py-2">Alert</td>
                    </tr>
                    <tr class="border-b border-sidebar">
                      <td class="py-2 pr-4"><code>event.outcomes</code></td>
                      <td class="py-2 pr-4">Prediction alert</td>
                      <td class="py-2 pr-4">10 (Twitch limit)</td>
                      <td class="py-2">Alert</td>
                    </tr>
                    <tr class="border-b border-sidebar">
                      <td class="py-2 pr-4"><code>event.top_contributions</code></td>
                      <td class="py-2 pr-4">Hype train alert</td>
                      <td class="py-2 pr-4">3 (fixed)</td>
                      <td class="py-2">Alert</td>
                    </tr>
                    <tr class="border-b border-sidebar">
                      <td class="py-2 pr-4"><code>subscribers</code></td>
                      <td class="py-2 pr-4">User (channel)</td>
                      <td class="py-2 pr-4">Account settings (default 10)</td>
                      <td class="py-2">Static</td>
                    </tr>
                    <tr class="border-b border-sidebar">
                      <td class="py-2 pr-4"><code>goals</code></td>
                      <td class="py-2 pr-4">User (channel)</td>
                      <td class="py-2 pr-4">Account settings (default 3)</td>
                      <td class="py-2">Static</td>
                    </tr>
                    <tr class="border-b border-sidebar">
                      <td class="py-2 pr-4"><code>channel_followers</code></td>
                      <td class="py-2 pr-4">User (channel)</td>
                      <td class="py-2 pr-4">Account settings (default 5)</td>
                      <td class="py-2">Static</td>
                    </tr>
                    <tr>
                      <td class="py-2 pr-4"><code>followed_channels</code></td>
                      <td class="py-2 pr-4">User (channel)</td>
                      <td class="py-2 pr-4">Account settings (default 5)</td>
                      <td class="py-2">Static</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="mt-3 text-sm text-foreground">
                Inside a loop, use <code>[[[alias.count]]]</code> on the iterable itself to get the total (untruncated) count.
                For example, <code>[[[subscribers.count]]]</code> shows the real subscriber total even if your cap is 10.
              </p>

              <h4 class="mt-6 mb-3 text-lg font-semibold">Inspect a loop item with <code>[[[raw]]]</code></h4>
              <p class="mb-3 text-foreground">
                Not sure what fields an iterable exposes? Drop <code>[[[raw]]]</code> inside the loop body and it will print the
                current item as pretty-printed JSON. It's the fastest way to see the shape of anything you're iterating over
                without guessing.
              </p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">[[[foreach:event.choices as choice]]]
  &lt;pre&gt;[[[raw]]]&lt;/pre&gt;
[[[endforeach]]]</pre>
              <p class="mt-3 text-sm text-foreground">
                <code>[[[raw]]]</code> only works inside a <code>[[[foreach]]]</code> and always dumps the current iteration's
                item regardless of the alias name. It's meant as a scaffolding tool - remove it from your finished template.
              </p>

              <h4 class="mt-6 mb-3 text-lg font-semibold">Fields available on each iteration item</h4>
              <p class="mb-4 text-foreground">
                Inside the loop body, reference any of these fields as <code>[[[alias.field]]]</code>, where
                <code>alias</code> is the name you picked after <code>as</code>. Missing fields render as an empty string.
              </p>

              <div class="space-y-5">
                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>event.choices</code> - poll choice</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>id</code> - stable choice id (good for <code>data-key</code>)</li>
                    <li><code>title</code> - choice label shown to voters</li>
                    <li><code>votes</code> - total votes on this choice</li>
                    <li><code>channel_points_votes</code> - votes cast with channel points</li>
                    <li><code>bits_votes</code> - votes cast with bits (deprecated by Twitch, still in payload)</li>
                  </ul>
                  <p class="mt-2 text-xs text-foreground">
                    Aggregates on the iterable itself:
                    <code>event.choices.total_votes</code>,
                    <code>event.choices.total_channel_points_votes</code>,
                    <code>event.choices.total_bits_votes</code>.
                  </p>
                </div>

                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>event.outcomes</code> - prediction outcome</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>id</code> - stable outcome id</li>
                    <li><code>title</code> - outcome label</li>
                    <li><code>color</code> - <code>"blue"</code> or <code>"pink"</code> (Twitch's own colouring)</li>
                    <li><code>users</code> - number of predictors on this outcome</li>
                    <li><code>channel_points</code> - total channel points wagered on this outcome</li>
                  </ul>
                  <p class="mt-2 text-xs text-foreground">
                    Aggregates: <code>event.outcomes.total_users</code>, <code>event.outcomes.total_channel_points</code>.
                    The winning outcome id is <code>event.winning_outcome_id</code> on lock/end events.
                  </p>
                </div>

                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>event.top_contributions</code> - hype train contributor</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>user_id</code>, <code>user_login</code>, <code>user_name</code> - the contributor</li>
                    <li><code>type</code> - <code>"bits"</code>, <code>"subscription"</code>, or <code>"other"</code></li>
                    <li><code>total</code> - amount contributed in the unit implied by <code>type</code></li>
                  </ul>
                  <p class="mt-2 text-xs text-foreground">
                    Capped at 3 items (fixed). For just the single latest contributor use
                    <code>event.last_contribution.user_name</code>, <code>event.last_contribution.type</code>,
                    <code>event.last_contribution.total</code>.
                  </p>
                </div>

                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>subscribers</code> - channel subscriber</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>user_id</code>, <code>user_login</code>, <code>user_name</code> - the subscriber</li>
                    <li><code>user_profile_image_url</code> - the subscriber's avatar (enriched from Helix)</li>
                    <li><code>broadcaster_id</code>, <code>broadcaster_login</code>, <code>broadcaster_name</code> - your channel</li>
                    <li><code>is_gift</code> - <code>true</code> if the sub was gifted</li>
                    <li><code>gifter_id</code>, <code>gifter_login</code>, <code>gifter_name</code> - empty string when <code>is_gift</code> is false</li>
                    <li><code>gifter_profile_image_url</code> - the gifter's avatar (enriched)</li>
                    <li><code>tier</code> - <code>"1000"</code>, <code>"2000"</code>, <code>"3000"</code>, or <code>"Prime"</code></li>
                    <li><code>plan_name</code> - human-readable tier label (e.g. <code>"Tier 1"</code>)</li>
                  </ul>
                </div>

                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>channel_followers</code> - someone who follows you</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>user_id</code>, <code>user_login</code>, <code>user_name</code> - the follower</li>
                    <li><code>followed_at</code> - ISO-8601 timestamp of when they followed</li>
                    <li><code>user_profile_image_url</code> - the follower's avatar (enriched from Helix)</li>
                  </ul>
                </div>

                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>followed_channels</code> - a channel you follow</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>broadcaster_id</code>, <code>broadcaster_login</code>, <code>broadcaster_name</code> - the channel</li>
                    <li><code>followed_at</code> - ISO-8601 timestamp of when you followed</li>
                    <li><code>broadcaster_profile_image_url</code> - the channel's avatar (enriched from Helix)</li>
                  </ul>
                </div>

                <div class="rounded border border-sidebar bg-sidebar p-4">
                  <h5 class="mb-2 font-semibold"><code>goals</code> - a channel goal</h5>
                  <ul class="ml-5 list-disc text-sm text-foreground">
                    <li><code>id</code> - stable goal id</li>
                    <li><code>broadcaster_id</code>, <code>broadcaster_login</code>, <code>broadcaster_name</code> - your channel</li>
                    <li>
                      <code>type</code> - one of
                      <code>follower</code>,
                      <code>subscription</code>,
                      <code>subscription_count</code>,
                      <code>new_subscription</code>,
                      <code>new_subscription_count</code>
                    </li>
                    <li><code>description</code> - the free-text label you set on Twitch</li>
                    <li><code>current_amount</code> - progress toward the goal</li>
                    <li><code>target_amount</code> - goal target</li>
                    <li><code>created_at</code> - ISO-8601 timestamp of when the goal was created</li>
                  </ul>
                </div>
              </div>

              <h4 class="mt-6 mb-3 text-lg font-semibold">Animating loop items with <code>data-key</code></h4>
              <p class="mb-3 text-foreground">
                Both static and alert overlays reconcile their rendered HTML via morphdom on every data update - when a poll
                vote changes or a hype train contribution arrives, only the differences get patched instead of the whole
                subtree being thrown away. That reconciliation is structural by default, which works fine for content updates
                but means in-flight CSS transitions reset because the DOM nodes underneath can be replaced between renders.
              </p>
              <p class="mb-3 text-foreground">
                Add <code>data-key</code> to the repeated element and morphdom will reuse the same DOM node across renders
                whenever the key matches. CSS transitions on that element then keep running smoothly:
              </p>
              <pre class="bg-sidebar-accent p-4 font-mono text-sm whitespace-pre-wrap">&lt;ul&gt;
  [[[foreach:event.choices as choice]]]
    &lt;li data-key="[[[choice.id]]]" style="--bar-width: [[[choice.votes_pct]]]%"&gt;
      [[[choice.title]]] - [[[choice.votes]]] votes
    &lt;/li&gt;
  [[[endforeach]]]
&lt;/ul&gt;</pre>
              <p class="mt-3 text-foreground">
                With the <code>&lt;li&gt;</code> pinned by its <code>data-key</code>, a CSS rule like
                <code>transition: width 300ms ease-out</code> on a bar inside the <code>&lt;li&gt;</code> will animate from
                the old width to the new one on every update. Without <code>data-key</code>, morphdom may replace the node
                and the transition has no "from" state to interpolate from, so the bar jumps.
              </p>
              <p class="mt-3 text-foreground">
                <code>data-key</code> falls back to the element's <code>id</code> if no <code>data-key</code> is set, and
                finally to morphdom's positional matching if neither is present - so older templates render identically to
                before. It's purely additive: add it when you want smooth animations on repeated items.
              </p>
              <p class="mt-3 text-sm text-foreground">
                <strong>Gotcha:</strong> pair <code>data-key</code> with CSS <code>transition</code>, not keyframe
                <code>animation</code>. Keyframe animations only fire once when a node mounts, so they won't re-trigger
                when the same DOM node's custom property changes. Transitions react to property changes and will.
              </p>
            </div>
          </div>
        </div>

        <!-- Filter controls -->
        <div class="mb-6 space-y-3" id="event-tags">
          <h2 class="text-2xl font-bold">Event & Integration Tags</h2>
          <p class="text-foreground">
            Tags available in alert templates, grouped by source. Click a category chip to narrow the list, or search by tag name, event type, or description.
          </p>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
              <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
              <input
                v-model="searchQuery"
                placeholder="Search tags, event types, descriptions..."
                class="input-border w-full pl-8 pr-2.5 py-1.5 text-sm"
              />
            </div>
            <button
              v-if="searchQuery || activeFamily !== 'all'"
              type="button"
              class="cursor-pointer rounded border border-sidebar bg-sidebar-accent px-3 py-1.5 text-xs text-foreground hover:bg-sidebar"
              @click="clearFilter"
            >
              Clear filters
            </button>
          </div>

          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              class="cursor-pointer rounded border px-2.5 py-1 text-xs transition"
              :class="activeFamily === 'all'
                ? 'border-violet-500 bg-violet-500/20 text-violet-200'
                : 'border-sidebar bg-sidebar-accent text-foreground hover:bg-sidebar'"
              @click="setFamily('all')"
            >
              All <span class="opacity-60">({{ cards.length }})</span>
            </button>
            <button
              v-for="family in FAMILY_ORDER"
              :key="family"
              type="button"
              class="cursor-pointer rounded border px-2.5 py-1 text-xs transition"
              :class="[
                activeFamily === family
                  ? 'border-violet-500 bg-violet-500/20 text-violet-200'
                  : 'border-sidebar bg-sidebar-accent text-foreground hover:bg-sidebar',
                familyCounts[family] === 0 ? 'opacity-40' : '',
              ]"
              :disabled="familyCounts[family] === 0 && activeFamily !== family"
              @click="setFamily(family)"
            >
              {{ FAMILY_LABELS[family] }} <span class="opacity-60">({{ familyCounts[family] }})</span>
            </button>
          </div>

          <p class="text-xs text-muted-foreground">
            Showing {{ filteredCards.length }} of {{ cards.length }} cards
          </p>
        </div>

        <!-- Event cards -->
        <div v-if="filteredCards.length === 0" class="border border-sidebar-border bg-card p-8 text-center">
          <p class="text-sm text-muted-foreground">
            No cards match your filter. <button type="button" class="cursor-pointer text-violet-400 hover:underline" @click="clearFilter">Clear filters</button>.
          </p>
        </div>

        <div v-else class="mb-12 space-y-6">
          <div
            v-for="card in filteredCards"
            :key="card.id"
            :id="card.id"
            class="border border-sidebar-border bg-card p-6"
          >
            <div class="mb-1 flex items-start gap-3">
              <span class="mt-1.5 inline-block h-3 w-3 shrink-0 rounded" :class="card.dot"></span>
              <div class="min-w-0 flex-1">
                <h3 class="text-xl font-semibold">
                  {{ card.title }}
                  <code v-if="card.type" class="ml-2 border border-sidebar-border px-1.5 py-0.5 text-xs font-normal text-purple-300">{{ card.type }}</code>
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">{{ card.subtitle }}</p>
              </div>
              <span class="shrink-0 border border-sidebar-border px-2 py-0.5 text-[10px] text-muted-foreground">{{ FAMILY_LABELS[card.family] }}</span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4" :class="card.cols.length > 1 ? 'md:grid-cols-2' : ''">
              <div v-for="col in card.cols" :key="col.heading">
                <h4 class="mb-2 font-semibold">{{ col.heading }}</h4>
                <div class="space-y-1.5 font-mono text-sm">
                  <div v-for="t in col.tags" :key="t.tag">
                    <code>{{ t.tag }}</code> <span class="font-sans text-foreground"> - {{ t.desc }}</span>
                    <span v-if="t.note === 'avoid'" class="ml-1 font-sans text-xs text-orange-400">DON'T USE</span>
                    <span v-if="t.note === 'use'" class="ml-1 font-sans text-xs text-green-400">USE THIS</span>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="card.example" class="mt-4 border border-sidebar-border p-4">
              <h5 class="mb-2 font-semibold">Example:</h5>
              <pre class="overflow-x-auto font-mono text-xs whitespace-pre-wrap text-foreground">{{ card.example }}</pre>
            </div>

            <div
              v-if="card.note"
              class="mt-4 rounded border p-3 text-sm"
              :class="{
                'border-green-500/30 bg-green-950/20 text-green-300': card.note.kind === 'info',
                'border-emerald-500/30 bg-emerald-950/20 text-emerald-300': card.note.kind === 'success',
                'border-amber-500/30 bg-amber-950/20 text-amber-300': card.note.kind === 'warn',
              }"
            >
              <strong>Note:</strong> {{ card.note.text }}
            </div>

            <!-- Gift bomb detection (only on channel.subscription.gift) -->
            <div v-if="card.specialBlock === 'gift-bomb'" id="gift-bomb-detection" class="mt-6 rounded-lg border border-pink-500/20 bg-pink-500/5 p-4">
              <h4 class="mb-2 font-semibold">Gift bomb detection</h4>
              <p class="mb-3 text-sm text-foreground">
                When someone gifts multiple subs at once (a "gift bomb"), Twitch sends each gift as a separate event. Without intervention, gifting 25 subs would trigger 25 individual alerts - which is chaos.
              </p>
              <p class="mb-3 text-sm text-foreground">
                Overlabels automatically detects gift bombs by collecting gifts from the same person within an 8-second window and combining them into a single alert. The alert updates live as more gifts come in, so your overlay shows a running count instead of a flood of notifications.
              </p>
              <p class="mb-3 text-sm text-foreground">The alert stays on screen longer for bigger gift bombs:</p>
              <ul class="mb-3 space-y-1 text-sm text-foreground">
                <li>2-4 gifts: 5 seconds</li>
                <li>5-19 gifts: 6 seconds</li>
                <li>20-49 gifts: 8 seconds</li>
                <li>50+ gifts: 10 seconds</li>
              </ul>
              <p class="text-sm text-foreground">
                Use <code>[[[event.total]]]</code> in your template to show the final gift count. Combine with conditionals to style large bombs differently:
              </p>
              <pre class="mt-3 overflow-x-auto border border-sidebar-border p-4 font-mono text-xs whitespace-pre-wrap">[[[if:event.total >= 25]]]
  &lt;div class="mega-bomb"&gt;[[[event.user_name]]] just gifted [[[event.total]]] subs!&lt;/div&gt;
[[[elseif:event.total >= 5]]]
  &lt;div class="gift-bomb"&gt;[[[event.user_name]]] gifted [[[event.total]]] subs!&lt;/div&gt;
[[[else]]]
  &lt;div&gt;[[[event.user_name]]] gifted [[[event.total]]] subs&lt;/div&gt;
[[[endif]]]</pre>
            </div>
          </div>
        </div>

        <!-- Integration how-it-works (always visible when the respective family is all or selected) -->
        <div v-if="activeFamily === 'all' || activeFamily === 'kofi'" class="mb-12 border border-sidebar-border bg-card p-6">
          <h3 class="mb-4 text-xl font-semibold">
            <span class="mr-2 inline-block h-3 w-3 rounded bg-orange-400"></span>
            Ko-fi - How It Works
          </h3>
          <p class="mb-4 text-foreground">
            These tags are available in <strong>alert templates</strong> that are triggered by Ko-fi events. Configure which alert fires for each event type on the
            <a href="/alerts" class="cursor-pointer text-violet-400 hover:underline">Alerts Builder</a> page.
          </p>
        </div>

        <div v-if="activeFamily === 'all' || activeFamily === 'streamlabs'" class="mb-12 border border-sidebar-border bg-card p-6">
          <h3 class="mb-4 text-xl font-semibold">
            <span class="mr-2 inline-block h-3 w-3 rounded bg-emerald-500"></span>
            StreamLabs - How It Works
          </h3>
          <ol class="mb-4 list-inside list-decimal space-y-2 text-foreground">
            <li>
              Go to
              <a href="/settings/integrations/streamlabs" class="cursor-pointer text-violet-400 hover:underline">Settings &gt; Integrations &gt; StreamLabs</a> and click <strong>Authenticate with StreamLabs</strong>.
            </li>
            <li>Authorize Overlabels in the StreamLabs popup.</li>
            <li>Done. Overlabels listens for donations automatically and updates your overlay controls in real time.</li>
          </ol>
          <div class="space-y-3 text-sm text-foreground">
            <div>
              <h4 class="font-semibold">Test Mode</h4>
              <p>
                Toggle test mode on the StreamLabs settings page to experiment with donations without affecting your live counters. When you turn test mode off, the donation counter resets to your seed value.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Starting Donation Count</h4>
              <p>
                Already have donations from before connecting? Set a starting count so your <code>[[[c:streamlabs:donations_received]]]</code> control picks up where you left off.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Shared Alert Templates</h4>
              <p>
                StreamLabs, Ko-fi, StreamElements, and Fourthwall donation events expose the same core tags, so you can point all four services at the same alert template.
              </p>
            </div>
          </div>
        </div>

        <div v-if="activeFamily === 'all' || activeFamily === 'streamelements'" class="mb-12 border border-sidebar-border bg-card p-6">
          <h3 class="mb-4 text-xl font-semibold">
            <span class="mr-2 inline-block h-3 w-3 rounded bg-teal-500"></span>
            StreamElements - How It Works
          </h3>
          <ol class="mb-4 list-inside list-decimal space-y-2 text-foreground">
            <li>
              Go to
              <a href="/settings/integrations/streamelements" class="cursor-pointer text-violet-400 hover:underline">Settings &gt; Integrations &gt; StreamElements</a> and paste your StreamElements JWT token (Account &gt; Channels &gt; Show secrets &gt; JWT Token in the SE dashboard).
            </li>
            <li>Save. Overlabels authenticates to the StreamElements realtime socket on your behalf.</li>
            <li>Done. Overlabels listens for tips automatically and updates your overlay controls in real time.</li>
          </ol>
          <div class="space-y-3 text-sm text-foreground">
            <div>
              <h4 class="font-semibold">Test Mode</h4>
              <p>
                Toggle test mode on the StreamElements settings page to experiment with tips without affecting your live counters. When you turn test mode off, the tip counter resets to your seed value.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Starting Tip Count</h4>
              <p>
                Already have tips from before connecting? Set a starting count so your <code>[[[c:streamelements:donations_received]]]</code> control picks up where you left off.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Shared Alert Templates</h4>
              <p>
                StreamElements, StreamLabs, Ko-fi, and Fourthwall donation events expose the same core tags, so you can point all four services at the same alert template.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">JWT Rotation</h4>
              <p>
                StreamElements JWTs do not have a refresh flow. If you regenerate the JWT in SE's dashboard, paste the new one into the settings page - Overlabels reconnects automatically.
              </p>
            </div>
          </div>
        </div>

        <div v-if="activeFamily === 'all' || activeFamily === 'fourthwall'" class="mb-12 border border-sidebar-border bg-card p-6">
          <h3 class="mb-4 text-xl font-semibold">
            <span class="mr-2 inline-block h-3 w-3 rounded bg-sky-500"></span>
            Fourthwall - How It Works
          </h3>
          <ol class="mb-4 list-inside list-decimal space-y-2 text-foreground">
            <li>
              Go to
              <a href="/settings/integrations/fourthwall" class="cursor-pointer text-violet-400 hover:underline">Settings &gt; Integrations &gt; Fourthwall</a> and click <strong>Authenticate with Fourthwall</strong>.
            </li>
            <li>Authorize Overlabels in the Fourthwall popup, granting the webhook scopes.</li>
            <li>Done. Overlabels registers a donation webhook on your Fourthwall shop automatically and updates your overlay controls in real time.</li>
          </ol>
          <div class="space-y-3 text-sm text-foreground">
            <div>
              <h4 class="font-semibold">Test Mode</h4>
              <p>
                Toggle test mode on the Fourthwall settings page to experiment with donations without affecting your live counters. When you turn test mode off, the donation counter resets to your seed value. You can fire test donations directly from the Fourthwall dashboard under your webhook's Recent Events.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Starting Donation Count</h4>
              <p>
                Already have Fourthwall donations from before connecting? Set a starting count so your <code>[[[c:fourthwall:donations_received]]]</code> control picks up where you left off.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Shared Alert Templates</h4>
              <p>
                Fourthwall, Ko-fi, StreamLabs, and StreamElements donation events expose the same core tags, so you can point all four services at the same alert template.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Automatic Webhook Lifecycle</h4>
              <p>
                Connecting registers a webhook on your Fourthwall shop programmatically; disconnecting deregisters it. You don't paste URLs into the Fourthwall dashboard - Overlabels manages the webhook on your behalf.
              </p>
            </div>
          </div>
        </div>

        <div v-if="activeFamily === 'all' || activeFamily === 'bmac'" class="mb-12 border border-sidebar-border bg-card p-6">
          <h3 class="mb-4 text-xl font-semibold">
            <span class="mr-2 inline-block h-3 w-3 rounded bg-yellow-300"></span>
            Buy Me a Coffee - How It Works
          </h3>
          <ol class="mb-4 list-inside list-decimal space-y-2 text-foreground">
            <li>
              Open
              <a href="https://studio.buymeacoffee.com/webhooks/" target="_blank" rel="noopener" class="cursor-pointer text-violet-400 hover:underline">studio.buymeacoffee.com/webhooks</a>
              and click <strong>Create new webhook</strong>.
            </li>
            <li>
              Paste the webhook URL shown on
              <a href="/settings/integrations/bmac" class="cursor-pointer text-violet-400 hover:underline">Settings &gt; Integrations &gt; Buy Me a Coffee</a>
              into BMAC's <strong>Webhook URL</strong> field.
            </li>
            <li>Pick the events you want (donation, commission, extras, membership, monthly support, wishlist) and create the webhook.</li>
            <li>Copy the <strong>Secret</strong> BMAC shows you and paste it back into the Overlabels settings page.</li>
            <li>Use BMAC's <strong>Send Test</strong> button to fire a test event - it should appear in <a href="/dashboard/recents" class="cursor-pointer text-violet-400 hover:underline">Recent Events</a>.</li>
          </ol>
          <div class="space-y-3 text-sm text-foreground">
            <div>
              <h4 class="font-semibold">Verification</h4>
              <p>
                BMAC signs every webhook with HMAC-SHA256 of the request body using the secret you saved. Overlabels rejects any request whose signature header (<code>x-signature-sha256</code>) doesn't match.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Privacy</h4>
              <p>
                Overlabels strips the supporter's email, shipping address, and gross-charged total from every event before storing it. Supporter emails are kept in an encrypted backend column for future analytics, never exposed to overlay templates or the Recent Events log.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Test Mode</h4>
              <p>
                Toggle test mode on the BMAC settings page to fire the same BMAC test webhook repeatedly without dedup. When you turn it off, the donation counter resets to your seed value.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Starting Donation Count</h4>
              <p>
                Already had supporters before connecting? Set a starting count so your <code>[[[c:bmac:donations_received]]]</code> control picks up where you left off. One-time-only - email Jasper if you need to correct it later.
              </p>
            </div>
            <div>
              <h4 class="font-semibold">Shared Alert Templates</h4>
              <p>
                BMAC, Ko-fi, StreamLabs, StreamElements, and Fourthwall all expose the same six core control keys. Pair them with <code>latest()</code>/<code>oldest()</code> over the <code>_at</code> companion timestamps to pick the most recent supporter across every connected service.
              </p>
            </div>
          </div>
        </div>

        <!-- Tips Section (always visible) -->
        <div class="border border-sidebar-border bg-sidebar-accent p-6" id="tips">
          <h2 class="mb-4 text-2xl font-bold">Tips & Best Practices</h2>
          <div class="space-y-6 text-foreground">
            <div>
              <h4 class="font-semibold text-lg text-violet-400">Use Meaningful Conditions</h4>
              <p class="text-sm">Create different alert styles based on the value: small donations vs large donations, new followers vs milestone followers.</p>
            </div>
            <div>
              <h4 class="font-semibold text-lg text-violet-400">Test Your Conditions</h4>
              <p class="text-sm">
                Use the
                <a class="cursor-pointer text-accent-foreground underline hover:no-underline" href="/testing" target="_blank" rel="nofollow noopener">Twitch Testing Guide</a>
                to test your alert templates with different event values to ensure they work as expected. Be sure to install the
                <a class="cursor-pointer text-accent-foreground underline hover:no-underline" href="https://dev.twitch.tv/docs/cli/" target="_blank">Twitch CLI</a>
                first.
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-lg text-violet-400">Style Conditional Content</h4>
              <p class="text-sm">Apply different CSS classes within conditionals to create visual variety for different alert types.</p>
            </div>
            <div>
              <h4 class="font-semibold text-lg text-violet-400">Copy the Starter Kit</h4>
              <p class="text-sm">
                <Link class="cursor-pointer text-accent-foreground underline hover:no-underline" href="/kits/1">Copy the Overlabels Starter Kit</Link>
                to get a great set of defaults to work with.
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-lg text-violet-400">High-frequency progress events</h4>
              <p class="text-sm">
                Hype train, charity, goal, poll, and prediction <code>*.progress</code> events can fire every few seconds during active engagement. The overlay extends the current alert rather than restacking, so the UI stays calm - but keep your templates lightweight for good measure.
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-lg text-violet-400">Speak HTML & CSS</h4>
              <p class="text-sm">Overlabels assumes you know your way around HTML, CSS, and a template engine.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
