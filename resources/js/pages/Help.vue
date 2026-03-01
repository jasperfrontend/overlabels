<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Help',
    href: '/help',
  },
];
</script>

<template>
  <Head title="Template Help" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-8">
          <h1 class="mb-4 text-4xl font-bold">Conditional Tags Reference</h1>
          <p class="text-lg text-muted-foreground">Complete guide to conditional template tags and available event data for your overlays.</p>
          <p class="text-lg text-muted-foreground">See your <a href="/tags" class="text-violet-400 hover:underline">static Template Tags</a> you can use for your account level.</p>
        </div>

        <!-- Conditional Syntax Section -->
        <div class="mb-12" id="conditionals">
          <h2 class="mb-6 text-2xl font-bold">Conditional Template Syntax</h2>
          <p class="mb-6 text-muted-foreground">
            Use conditional logic to dynamically show or hide content in your templates based on real-time data. All conditionals are processed
            client-side for security.
          </p>

          <div class="space-y-8">
            <!-- Boolean Conditions -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Boolean Conditions</h3>
              <p class="mb-4 text-muted-foreground">
                Test if a value exists and is truthy. Values considered false: <code>null</code>, <code>undefined</code>, <code>""</code>,
                <code>"false"</code>, <code>"0"</code>
              </p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm">
                [[[if:channel_is_branded]]]<br />
                &nbsp;&nbsp;&lt;p&gt;This stream is sponsored!&lt;/p&gt;<br />
                [[[endif]]]
              </div>
            </div>

            <!-- Numerical Comparisons -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Numerical Comparisons</h3>
              <p class="mb-4 text-muted-foreground">
                Compare numbers using standard operators: <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code>,
                <code>!=</code>, <code>=</code>
              </p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm">
                [[[if:followers_total >= 1000]]]<br />
                &nbsp;&nbsp;&lt;div class="milestone"&gt;1K+ followers!&lt;/div&gt;<br />
                [[[elseif:followers_total >= 100]]]<br />
                &nbsp;&nbsp;&lt;div&gt;Growing strong with [[[followers_total]]] followers&lt;/div&gt;<br />
                [[[else]]]<br />
                &nbsp;&nbsp;&lt;div&gt;Help us reach 100 followers!&lt;/div&gt;<br />
                [[[endif]]]
              </div>
            </div>

            <!-- String Comparisons -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">String Comparisons</h3>
              <p class="mb-4 text-muted-foreground">Compare text values using <code>=</code> and <code>!=</code> operators.</p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm">
                [[[if:channel_language = en]]]<br />
                &nbsp;&nbsp;&lt;p&gt;Welcome to our English stream!&lt;/p&gt;<br />
                [[[elseif:channel_language = es]]]<br />
                &nbsp;&nbsp;&lt;p&gt;¡Bienvenidos a nuestro stream en Español!&lt;/p&gt;<br />
                [[[endif]]]
              </div>
            </div>

            <!-- Event-based Conditionals -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Event-based Conditionals</h3>
              <p class="mb-4 text-muted-foreground">
                Use event data in alert templates to create dynamic alerts based on donation/subscription amounts, viewer counts, etc.
              </p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm">
                [[[if:event.bits >= 1000]]]<br />
                &nbsp;&nbsp;&lt;div class="big-cheer"&gt;HUGE CHEER! [[[event.user_name]]] donated [[[event.bits]]] bits!&lt;/div&gt;<br />
                [[[elseif:event.bits >= 100]]]<br />
                &nbsp;&nbsp;&lt;div class="medium-cheer"&gt;Thanks [[[event.user_name]]] for [[[event.bits]]] bits!&lt;/div&gt;<br />
                [[[else]]]<br />
                &nbsp;&nbsp;&lt;div&gt;[[[event.user_name]]] cheered with [[[event.bits]]] bits!&lt;/div&gt;<br />
                [[[endif]]]
              </div>
            </div>

            <!-- Nested Conditionals -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Nested Conditionals</h3>
              <p class="mb-4 text-muted-foreground">You can nest conditionals up to 10 levels deep for complex logic.</p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm">
                [[[if:event.tier = 3000]]]<br />
                &nbsp;&nbsp;[[[if:event.total >= 10]]]<br />
                &nbsp;&nbsp;&nbsp;&nbsp;&lt;div&gt;Tier 3 gift bomb! [[[event.total]]] subs!&lt;/div&gt;<br />
                &nbsp;&nbsp;[[[else]]]<br />
                &nbsp;&nbsp;&nbsp;&nbsp;&lt;div&gt;Tier 3 gift: [[[event.total]]] subs&lt;/div&gt;<br />
                &nbsp;&nbsp;[[[endif]]]<br />
                [[[endif]]]
              </div>
            </div>
          </div>
        </div>

        <!-- Event-based Template Tags Section -->
        <div class="mb-12" id="event-based-template-tags">
          <h2 class="mb-6 text-2xl font-bold">Event-based Template Tags</h2>
          <p class="mb-6 text-muted-foreground">
            These tags are available in alert templates and contain data specific to each Twitch event. Use <code>[[[event.tag_name]]]</code> syntax.
          </p>

          <div class="space-y-8">
            <!-- Channel Follow Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-green-500"></span>
                Channel Follow (channel.follow)
              </h3>
              <p class="mb-4 text-muted-foreground">When someone follows your channel</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">User Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.user_id]]]</code> - Follower's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Follower's username</div>
                    <div><code>[[[event.user_name]]]</code> - Follower's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Event Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.followed_at]]]</code> - Timestamp when followed</div>
                    <div><code>[[[event.broadcaster_user_name]]]</code> - Your display name</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Subscribe Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-purple-500"></span>
                Channel Subscribe (channel.subscribe)
              </h3>
              <p class="mb-4 text-muted-foreground">When someone subscribes to your channel</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">User Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.user_id]]]</code> - Subscriber's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Subscriber's username</div>
                    <div><code>[[[event.user_name]]]</code> - Subscriber's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Subscription Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.tier]]]</code> - Sub tier (1000, 2000, 3000) <span class="text-orange-400">DON'T USE THIS</span></div>
                    <div><code>[[[event.tier_display]]]</code> - Sub display (1, 2, 3) <span class="text-green-400">USE THIS</span></div>
                    <div><code>[[[event.is_gift]]]</code> - true/false if gifted</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Subscription Gift Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-pink-500"></span>
                Subscription Gifts (channel.subscription.gift)
              </h3>
              <p class="mb-4 text-muted-foreground">When someone gifts subscriptions</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">User Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.user_id]]]</code> - Gifter's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Gifter's username</div>
                    <div><code>[[[event.user_name]]]</code> - Gifter's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Gift Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.total]]]</code> - Number of subs gifted</div>
                    <div><code>[[[event.tier]]]</code> - Sub tier (1000, 2000, 3000) <span class="text-orange-400">DON'T USE THIS</span></div>
                    <div><code>[[[event.tier_display]]]</code> - Sub display (1, 2, 3) <span class="text-green-400">USE THIS</span></div>
                    <div><code>[[[event.cumulative_total]]]</code> - Total gifts ever</div>
                    <div><code>[[[event.is_anonymous]]]</code> - true/false if anonymous</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Subscription Message Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-indigo-500"></span>
                Subscription Messages (channel.subscription.message)
              </h3>
              <p class="mb-4 text-muted-foreground">When someone resubscribes with a message</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">User Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.user_name]]]</code> - Subscriber's display name</div>
                    <div><code>[[[event.tier]]]</code> - Sub tier (1000, 2000, 3000) <span class="text-orange-400">DON'T USE THIS</span></div>
                    <div><code>[[[event.tier_display]]]</code> - Sub display (1, 2, 3) <span class="text-green-400">USE THIS</span></div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Subscription Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.cumulative_months]]]</code> - Total months subbed</div>
                    <div><code>[[[event.streak_months]]]</code> - Current streak</div>
                    <div><code>[[[event.duration_months]]]</code> - Months in this sub</div>
                    <div><code>[[[event.message.text]]]</code> - The resub message</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Cheer Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-yellow-500"></span>
                Channel Cheer (channel.cheer)
              </h3>
              <p class="mb-4 text-muted-foreground">When someone cheers bits</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">User Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.user_id]]]</code> - Cheerer's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Cheerer's username</div>
                    <div><code>[[[event.user_name]]]</code> - Cheerer's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Cheer Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.bits]]]</code> - Number of bits cheered</div>
                    <div><code>[[[event.message]]]</code> - Cheer message</div>
                    <div><code>[[[event.is_anonymous]]]</code> - true/false if anonymous</div>
                  </div>
                </div>
              </div>
              <div class="mt-4 rounded bg-sidebar p-4">
                <h5 class="mb-2 font-semibold">Example Usage:</h5>
                <code class="text-sm"> [[[if:event.bits >= 1000]]]HUGE CHEER![[[endif]]] [[[event.user_name]]] cheered [[[event.bits]]] bits! </code>
              </div>
            </div>

            <!-- Channel Raid Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-red-500"></span>
                Channel Raid (channel.raid)
              </h3>
              <p class="mb-4 text-muted-foreground">When another streamer raids your channel</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">Raider Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.from_broadcaster_user_id]]]</code> - Raider's ID</div>
                    <div><code>[[[event.from_broadcaster_user_login]]]</code> - Raider's username</div>
                    <div><code>[[[event.from_broadcaster_user_name]]]</code> - Raider's name</div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Raid Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.viewers]]]</code> - Number of viewers in raid</div>
                    <div><code>[[[event.to_broadcaster_user_name]]]</code> - Your name</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Points Reward Redemption -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-cyan-500"></span>
                Channel Points Redemption (channel.channel_points_custom_reward_redemption.add)
              </h3>
              <p class="mb-4 text-muted-foreground">When someone redeems a channel points reward</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">User Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.user_id]]]</code> - Redeemer's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Redeemer's username</div>
                    <div><code>[[[event.user_name]]]</code> - Redeemer's display name</div>
                    <div><code>[[[event.user_input]]]</code> - User's input text</div>
                  </div>
                </div>
                <div>
                  <h4 class="mb-2 font-semibold">Reward Data</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.reward.title]]]</code> - Reward name</div>
                    <div><code>[[[event.reward.cost]]]</code> - Point cost</div>
                    <div><code>[[[event.reward.prompt]]]</code> - Reward description</div>
                    <div><code>[[[event.status]]]</code> - Fulfillment status</div>
                    <div><code>[[[event.redeemed_at]]]</code> - Timestamp</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Stream Online Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-green-400"></span>
                Stream Online (stream.online)
              </h3>
              <p class="mb-4 text-muted-foreground">When your stream goes live</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <h4 class="mb-2 font-semibold">Stream Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.id]]]</code> - Stream ID</div>
                    <div><code>[[[event.type]]]</code> - Stream type (usually "live")</div>
                    <div><code>[[[event.started_at]]]</code> - Stream start timestamp</div>
                  </div>
                </div>
              </div>
              <div class="mt-4 rounded border border-green-200 bg-green-50 p-4">
                <p class="text-sm text-green-800">
                  <strong>Note:</strong> This event is useful for logging, but viewers probably won't see live alerts since the stream just started.
                </p>
              </div>
            </div>

            <!-- Stream Off Event -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-red-400"></span>
                Stream Offline (stream.offline)
              </h3>
              <p class="mb-4 text-muted-foreground">When your stream goes offline</p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-1">
                <div>
                  <h4 class="mb-2 font-semibold">Stream Information</h4>
                  <div class="space-y-2 font-mono text-sm">
                    <div><code>[[[event.broadcaster_user_id]]]</code> - "73327367"</div>
                    <div><code>[[[event.broadcaster_user_login]]]</code> - "testBroadcaster"</div>
                    <div><code>[[[event.broadcaster_user_name]]]</code> - "testBroadcaster"</div>
                  </div>
                </div>
              </div>
              <div class="mt-4 rounded border border-red-200 bg-red-50 p-4">
                <p class="text-sm text-red-800">
                  <strong>Note:</strong> This event is useful for logging, but viewers won't see alerts since the stream went offline.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Ko-fi Integration Events Section -->
        <div class="mb-12" id="kofi-events">
          <h2 class="mb-6 text-2xl font-bold">Ko-fi Integration Events</h2>
          <p class="mb-6 text-muted-foreground">
            These tags are available in <strong>alert templates</strong> that are triggered by Ko-fi events. Configure which template fires for each
            event type on the <a href="/alerts" class="text-violet-400 hover:underline">Alerts Builder</a> page.
          </p>

          <div class="space-y-8">
            <!-- Tags available on all Ko-fi events -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-orange-400"></span>
                All Ko-fi Events
              </h3>
              <p class="mb-4 text-muted-foreground">Available on every Ko-fi event type (donation, subscription, shop_order, commission).</p>
              <div class="space-y-2 font-mono text-sm">
                <div><code>[[[event.from_name]]]</code> — name of the supporter</div>
                <div><code>[[[event.type]]]</code> — normalized type: <code>donation</code>, <code>subscription</code>, <code>shop_order</code>, or <code>commission</code></div>
                <div><code>[[[event.transaction_id]]]</code> — unique Ko-fi transaction ID</div>
                <div><code>[[[event.url]]]</code> — supporter's Ko-fi page URL</div>
              </div>
            </div>

            <!-- Donation / Subscription -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-yellow-400"></span>
                Donation &amp; Subscription Events
              </h3>
              <p class="mb-4 text-muted-foreground">Additional tags available for <code>donation</code> and <code>subscription</code> events.</p>
              <div class="space-y-2 font-mono text-sm">
                <div><code>[[[event.message]]]</code> — supporter's message</div>
                <div><code>[[[event.amount]]]</code> — amount as a string (e.g. <code>"5.00"</code>)</div>
                <div><code>[[[event.currency]]]</code> — currency code (e.g. <code>"USD"</code>)</div>
              </div>
            </div>

            <!-- Subscription-only -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">
                <span class="mr-2 inline-block h-4 w-4 rounded bg-purple-400"></span>
                Subscription Events Only
              </h3>
              <p class="mb-4 text-muted-foreground">Extra tags exclusive to Ko-fi <code>subscription</code> events.</p>
              <div class="space-y-2 font-mono text-sm">
                <div><code>[[[event.tier_name]]]</code> — subscription tier name</div>
                <div><code>[[[event.is_first_sub]]]</code> — <code>"1"</code> if this is the supporter's first payment, <code>"0"</code> otherwise</div>
                <div><code>[[[event.is_subscription]]]</code> — always <code>"1"</code> for subscription events</div>
              </div>
            </div>

            <!-- Example -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Example Ko-fi Alert Template</h3>
              <div class="rounded bg-sidebar p-4 font-mono text-sm">
                &lt;div class="donor"&gt;[[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!&lt;/div&gt;<br />
                &lt;div class="message"&gt;[[[if:event.message]]][[[event.message]]][[[endif]]]&lt;/div&gt;
              </div>
              <p class="mt-4 text-sm text-muted-foreground">
                The <code>[[[if:event.message]]]</code> guard ensures the message block is only rendered when the supporter left a message.
              </p>
            </div>
          </div>
        </div>

        <!-- Tips Section -->
        <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
          <h2 class="mb-4 text-2xl font-bold">Tips & Best Practices</h2>
          <div class="space-y-4 text-muted-foreground">
            <div>
              <h4 class="font-semibold text-foreground">Use Meaningful Conditions</h4>
              <p>Create different alert styles based on the value: small donations vs large donations, new followers vs milestone followers.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Test Your Conditions</h4>
              <p>
                Use the
                <a class="text-accent-foreground underline hover:no-underline" href="/testing" target="_blank" rel="nofollow noopener"
                  >Twitch Testing Guide</a
                >
                to test your alert templates with different event values to ensure they work as expected. Be sure to install the
                <a class="text-accent-foreground underline hover:no-underline" href="https://dev.twitch.tv/docs/cli/" target="_blank">Twitch CLI</a>
                first!
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Style Conditional Content</h4>
              <p>Apply different CSS classes within conditionals to create visual variety for different alert types.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Fork the Starter Kit</h4>
              <p>
                <Link class="text-accent-foreground underline hover:no-underline" href="/kits/1">Fork the Overlabels Starter Kit</Link> to get a great
                set of defaults to work with.
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">If you speak HTML & CSS and understand conditional logic, you're in the right place</h4>
              <p>
                Overlabels assumes you know your way around HTML, CSS and a template engine.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
