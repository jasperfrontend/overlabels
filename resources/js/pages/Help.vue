<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Help",
    href: '/help',
  },
];
</script>



<template>
  <Head title="Template Help" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="max-w-4xl mx-auto p-6">
        <div class="mb-8">
          <h1 class="text-4xl font-bold mb-4">Template Help & Reference</h1>
          <p class="text-muted-foreground text-lg">
            Complete guide to conditional template tags and available event data for your overlays.
          </p>
        </div>

        <!-- Conditional Syntax Section -->
        <div class="mb-12">
          <h2 class="text-2xl font-bold mb-6">Conditional Template Syntax</h2>
          <p class="text-muted-foreground mb-6">
            Use conditional logic to dynamically show or hide content in your templates based on real-time data. All conditionals are processed client-side for security.
          </p>

          <div class="space-y-8">
            <!-- Boolean Conditions -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">Boolean Conditions</h3>
              <p class="text-muted-foreground mb-4">
                Test if a value exists and is truthy. Values considered false: <code>null</code>, <code>undefined</code>, <code>""</code>, <code>"false"</code>, <code>"0"</code>
              </p>
              <div class="bg-muted p-4 rounded font-mono text-sm">
  [[[if:channel_is_branded]]]<br/>
  &nbsp;&nbsp;&lt;p&gt;This stream is sponsored!&lt;/p&gt;<br/>
  [[[endif]]]
              </div>
            </div>

            <!-- Numerical Comparisons -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">Numerical Comparisons</h3>
              <p class="text-muted-foreground mb-4">
                Compare numbers using standard operators: <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code>, <code>!=</code>, <code>=</code>
              </p>
              <div class="bg-muted p-4 rounded font-mono text-sm">
  [[[if:followers_total >= 1000]]]<br/>
  &nbsp;&nbsp;&lt;div class="milestone"&gt;1K+ followers!&lt;/div&gt;<br/>
  [[[elseif:followers_total >= 100]]]<br/>
  &nbsp;&nbsp;&lt;div&gt;Growing strong with [[[followers_total]]] followers&lt;/div&gt;<br/>
  [[[else]]]<br/>
  &nbsp;&nbsp;&lt;div&gt;Help us reach 100 followers!&lt;/div&gt;<br/>
  [[[endif]]]
              </div>
            </div>

            <!-- String Comparisons -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">String Comparisons</h3>
              <p class="text-muted-foreground mb-4">
                Compare text values using <code>=</code> and <code>!=</code> operators.
              </p>
              <div class="bg-muted p-4 rounded font-mono text-sm">
  [[[if:channel_language = en]]]<br/>
  &nbsp;&nbsp;&lt;p&gt;Welcome to our English stream!&lt;/p&gt;<br/>
  [[[elseif:channel_language = es]]]<br/>
  &nbsp;&nbsp;&lt;p&gt;¡Bienvenidos a nuestro stream en Español!&lt;/p&gt;<br/>
  [[[endif]]]
              </div>
            </div>

            <!-- Event-based Conditionals -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">Event-based Conditionals</h3>
              <p class="text-muted-foreground mb-4">
                Use event data in alert templates to create dynamic alerts based on donation/subscription amounts, viewer counts, etc.
              </p>
              <div class="bg-muted p-4 rounded font-mono text-sm">
  [[[if:event.bits >= 1000]]]<br/>
  &nbsp;&nbsp;&lt;div class="big-cheer"&gt;HUGE CHEER! [[[event.user_name]]] donated [[[event.bits]]] bits!&lt;/div&gt;<br/>
  [[[elseif:event.bits >= 100]]]<br/>
  &nbsp;&nbsp;&lt;div class="medium-cheer"&gt;Thanks [[[event.user_name]]] for [[[event.bits]]] bits!&lt;/div&gt;<br/>
  [[[else]]]<br/>
  &nbsp;&nbsp;&lt;div&gt;[[[event.user_name]]] cheered with [[[event.bits]]] bits!&lt;/div&gt;<br/>
  [[[endif]]]
              </div>
            </div>

            <!-- Nested Conditionals -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">Nested Conditionals</h3>
              <p class="text-muted-foreground mb-4">
                You can nest conditionals up to 10 levels deep for complex logic.
              </p>
              <div class="bg-muted p-4 rounded font-mono text-sm">
  [[[if:event.tier = 3000]]]<br/>
  &nbsp;&nbsp;[[[if:event.total >= 10]]]<br/>
  &nbsp;&nbsp;&nbsp;&nbsp;&lt;div&gt;Tier 3 gift bomb! [[[event.total]]] subs!&lt;/div&gt;<br/>
  &nbsp;&nbsp;[[[else]]]<br/>
  &nbsp;&nbsp;&nbsp;&nbsp;&lt;div&gt;Tier 3 gift: [[[event.total]]] subs&lt;/div&gt;<br/>
  &nbsp;&nbsp;[[[endif]]]<br/>
  [[[endif]]]
              </div>
            </div>
          </div>
        </div>

        <!-- Event-based Template Tags Section -->
        <div class="mb-12">
          <h2 class="text-2xl font-bold mb-6">Event-based Template Tags</h2>
          <p class="text-muted-foreground mb-6">
            These tags are available in alert templates and contain data specific to each Twitch event. Use <code>[[[event.tag_name]]]</code> syntax.
          </p>

          <div class="space-y-8">
            <!-- Channel Follow Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-green-500 rounded mr-2"></span>
                Channel Follow (channel.follow)
              </h3>
              <p class="text-muted-foreground mb-4">When someone follows your channel</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">User Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.user_id]]]</code> - Follower's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Follower's username</div>
                    <div><code>[[[event.user_name]]]</code> - Follower's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Event Data</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.followed_at]]]</code> - Timestamp when followed</div>
                    <div><code>[[[event.broadcaster_user_name]]]</code> - Your display name</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Subscribe Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-purple-500 rounded mr-2"></span>
                Channel Subscribe (channel.subscribe)
              </h3>
              <p class="text-muted-foreground mb-4">When someone subscribes to your channel</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">User Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.user_id]]]</code> - Subscriber's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Subscriber's username</div>
                    <div><code>[[[event.user_name]]]</code> - Subscriber's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Subscription Data</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.tier]]]</code> - Sub tier (1000, 2000, 3000)</div>
                    <div><code>[[[event.is_gift]]]</code> - true/false if gifted</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Subscription Gift Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-pink-500 rounded mr-2"></span>
                Subscription Gifts (channel.subscription.gift)
              </h3>
              <p class="text-muted-foreground mb-4">When someone gifts subscriptions</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">User Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.user_id]]]</code> - Gifter's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Gifter's username</div>
                    <div><code>[[[event.user_name]]]</code> - Gifter's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Gift Data</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.total]]]</code> - Number of subs gifted</div>
                    <div><code>[[[event.tier]]]</code> - Sub tier (1000, 2000, 3000)</div>
                    <div><code>[[[event.cumulative_total]]]</code> - Total gifts ever</div>
                    <div><code>[[[event.is_anonymous]]]</code> - true/false if anonymous</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Subscription Message Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-indigo-500 rounded mr-2"></span>
                Subscription Messages (channel.subscription.message)
              </h3>
              <p class="text-muted-foreground mb-4">When someone resubscribes with a message</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">User Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.user_name]]]</code> - Subscriber's display name</div>
                    <div><code>[[[event.tier]]]</code> - Sub tier (1000, 2000, 3000)</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Subscription Data</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.cumulative_months]]]</code> - Total months subbed</div>
                    <div><code>[[[event.streak_months]]]</code> - Current streak</div>
                    <div><code>[[[event.duration_months]]]</code> - Months in this sub</div>
                    <div><code>[[[event.message.text]]]</code> - The resub message</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Cheer Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-yellow-500 rounded mr-2"></span>
                Channel Cheer (channel.cheer)
              </h3>
              <p class="text-muted-foreground mb-4">When someone cheers bits</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">User Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.user_id]]]</code> - Cheerer's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Cheerer's username</div>
                    <div><code>[[[event.user_name]]]</code> - Cheerer's display name</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Cheer Data</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.bits]]]</code> - Number of bits cheered</div>
                    <div><code>[[[event.message]]]</code> - Cheer message</div>
                    <div><code>[[[event.is_anonymous]]]</code> - true/false if anonymous</div>
                  </div>
                </div>
              </div>
              <div class="mt-4 p-4 bg-muted rounded">
                <h5 class="font-semibold mb-2">Example Usage:</h5>
                <code class="text-sm">
                  [[[if:event.bits >= 1000]]]HUGE CHEER![[[endif]]] [[[event.user_name]]] cheered [[[event.bits]]] bits!
                </code>
              </div>
            </div>

            <!-- Channel Raid Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-red-500 rounded mr-2"></span>
                Channel Raid (channel.raid)
              </h3>
              <p class="text-muted-foreground mb-4">When another streamer raids your channel</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">Raider Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.from_broadcaster_user_id]]]</code> - Raider's ID</div>
                    <div><code>[[[event.from_broadcaster_user_login]]]</code> - Raider's username</div>
                    <div><code>[[[event.from_broadcaster_user_name]]]</code> - Raider's name</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Raid Data</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.viewers]]]</code> - Number of viewers in raid</div>
                    <div><code>[[[event.to_broadcaster_user_name]]]</code> - Your name</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Channel Points Reward Redemption -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-cyan-500 rounded mr-2"></span>
                Channel Points Redemption (channel.channel_points_custom_reward_redemption.add)
              </h3>
              <p class="text-muted-foreground mb-4">When someone redeems a channel points reward</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">User Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.user_id]]]</code> - Redeemer's Twitch ID</div>
                    <div><code>[[[event.user_login]]]</code> - Redeemer's username</div>
                    <div><code>[[[event.user_name]]]</code> - Redeemer's display name</div>
                    <div><code>[[[event.user_input]]]</code> - User's input text</div>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold mb-2">Reward Data</h4>
                  <div class="space-y-2 text-sm font-mono">
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
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-green-400 rounded mr-2"></span>
                Stream Online (stream.online)
              </h3>
              <p class="text-muted-foreground mb-4">When your stream goes live</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">Stream Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.id]]]</code> - Stream ID</div>
                    <div><code>[[[event.type]]]</code> - Stream type (usually "live")</div>
                    <div><code>[[[event.started_at]]]</code> - Stream start timestamp</div>
                  </div>
                </div>
              </div>
              <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded">
                <p class="text-sm text-green-800">
                  <strong>Note:</strong> This event is useful for logging, but viewers probably won't see live alerts since the stream just started.
                </p>
              </div>
            </div>

            <!-- Stream Off Event -->
            <div class="border rounded-lg p-6 bg-card">
              <h3 class="text-xl font-semibold mb-4">
                <span class="inline-block w-4 h-4 bg-red-400 rounded mr-2"></span>
                Stream Offline (stream.offline)
              </h3>
              <p class="text-muted-foreground mb-4">When your stream goes offline</p>
              <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <div>
                  <h4 class="font-semibold mb-2">Stream Information</h4>
                  <div class="space-y-2 text-sm font-mono">
                    <div><code>[[[event.broadcaster_user_id]]]</code> - "73327367"</div>
                    <div><code>[[[event.broadcaster_user_login]]]</code> - "testBroadcaster"</div>
                    <div><code>[[[event.broadcaster_user_name]]]</code> - "testBroadcaster"</div>
                  </div>
                </div>
              </div>
              <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded">
                <p class="text-sm text-red-800">
                  <strong>Note:</strong> This event is useful for logging, but viewers won't see alerts since the stream went offline.
                </p>
              </div>
            </div>

          </div>
        </div>

        <!-- Tips Section -->
        <div class="border rounded-lg p-6 bg-card">
          <h2 class="text-2xl font-bold mb-4">Tips & Best Practices</h2>
          <div class="space-y-4 text-muted-foreground">
            <div>
              <h4 class="font-semibold text-foreground">Use Meaningful Conditions</h4>
              <p>Create different alert styles based on the value: small donations vs large donations, new followers vs milestone followers.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Test Your Conditions</h4>
              <p>Use the <a class="text-accent-foreground underline hover:no-underline" href="https://dev.twitch.tv/docs/cli/event-command/" target="_blank" rel="nofollow noopener">Twitch CLI</a> to test your alert templates with different event values to ensure they work as expected.
                (<a href="https://dev.twitch.tv/docs/cli/" class="text-accent-foreground underline hover:no-underline" target="_blank" rel="nofollow">How to install Twitch CLI</a>)</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Style Conditional Content</h4>
              <p>Apply different CSS classes within conditionals to create visual variety for different alert types.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Fork the Starter Kit</h4>
              <p>Fork the Overlabels Starter Kit to get a great set of defaults to work with.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">If you speak HTML & CSS and understand conditional logic, you're in the right place</h4>
              <p>
                Overlabels assumes you know your way around HTML, CSS and a template engine.
                If those sound like foreign words, you'll probably have a better time with a
                drag-and-drop overlay tool like <a class="text-accent-foreground underline hover:no-underline" href="https://streamelements.com" target="_blank">StreamElements</a> or <a class="text-accent-foreground underline hover:no-underline" href="https://streamlabs.com" target="_blank">StreamLabs</a> instead.
              </p>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
