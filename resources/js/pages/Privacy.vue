<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import LoginSocial from '@/components/LoginSocial.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, ArrowRight } from '@lucide/vue';

const lastUpdated = 'November 24, 2026';
</script>

<template>
  <div>
    <Head title="Privacy Policy">
      <meta name="description" content="Overlabels Privacy Policy - Learn how we collect, use, and protect your data." />
    </Head>

    <div class="min-h-screen bg-gradient-to-b from-white to-gray-50 dark:from-gray-950 dark:to-gray-900">
      <!-- Navigation -->
      <nav class="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-lg">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 items-center justify-between">
            <!-- Plain anchor: '/' is a Blade view, not an Inertia page. -->
            <a href="/" class="flex cursor-pointer items-center gap-2.5">
              <img src="/favicon.png" alt="" class="h-8 w-8" />
              <span class="text-lg font-bold tracking-tight">Overlabels</span>
              <Badge variant="outline" class="text-xs">Beta</Badge>
            </a>
            <div class="flex items-center gap-6">
              <Link href="/help" class="hidden text-sm text-muted-foreground transition-colors hover:text-foreground sm:block">Docs </Link>
              <Link href="/help/manifesto" class="hidden text-sm text-muted-foreground transition-colors hover:text-foreground sm:block">
                Manifesto
              </Link>
              <DarkModeToggle />
              <Link v-if="$page.props.auth.user" :href="route('dashboard.index')" class="btn btn-primary text-sm">
                Dashboard
                <ArrowRight class="ml-1.5 h-4 w-4" />
              </Link>
              <LoginSocial v-else />
            </div>
          </div>
        </div>
      </nav>

      <!-- Content -->
      <div class="container mx-auto max-w-4xl px-6 py-12">
        <h1 class="mb-2 text-4xl font-bold text-gray-900 dark:text-white">Privacy Policy</h1>
        <p class="mb-8 text-gray-600 dark:text-gray-400">Last updated: {{ lastUpdated }}</p>

        <div class="prose prose-gray dark:prose-invert max-w-none space-y-8">
          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">1. Introduction</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Welcome to Overlabels ("we," "our," or "us"). We are committed to protecting your personal information and your right to privacy. This
              Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our service.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">2. Information We Collect</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Information You Provide</h3>
            <ul class="mb-4 list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>
                Twitch account information (display name and Twitch user ID). We do not store your email address: it is stripped from the Twitch login
                response before anything is saved, and there is no column to hold it
              </li>
              <li>Overlay templates and configurations you create</li>
              <li>
                Lists you create through the dashboard or chat commands, including the values your viewers contribute (chatter display names, custom
                messages, etc.)
              </li>
              <li>Communication data when you contact our support</li>
              <li>Reports you submit about a publicly listed overlay (see "Reporting a public overlay" below)</li>
            </ul>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Information Collected Automatically</h3>
            <ul class="mb-4 list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>
                Your IP address and browser user agent, recorded against your login sessions and against each use of an overlay access token. Overlay
                access logs are deleted after 90 days
              </li>
              <li>Events Twitch sends us about your channel, which are deleted after 90 days</li>
            </ul>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              We use one analytics tool: Cloudflare Web Analytics, on this site and on the pages that serve your overlays. It counts page views and
              measures how fast pages load. It sets no cookies, stores nothing in your browser and does not build a profile of you. It only reports
              the address of a page without anything after a "#" or "?", so your overlay access token never reaches it. There is no Google Analytics,
              no Plausible, no Fathom, no PostHog, no Mixpanel, no Hotjar, no Matomo and no advertising pixel, and we do not use tracking cookies. The
              only cookie we set is the one that keeps you logged in. We do not geolocate your IP address or send it to any third party.
            </p>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              For a complete, plain-language inventory of every table and how long each thing is kept, see
              <a href="/help/your-data" class="text-purple-600 hover:underline dark:text-purple-400">Your data on Overlabels</a>.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Information from Twitch</h3>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Stream data (follower count, subscriber count, viewer statistics)</li>
              <li>Channel information and settings</li>
              <li>EventSub notifications (follows, raids, subscriptions)</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">3. How We Use Your Information</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">We use your information to:</p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Provide and maintain our overlay services</li>
              <li>Process and display real-time stream events</li>
              <li>Save and manage your overlay templates</li>
              <li>Communicate with you about service updates</li>
              <li>Improve our services and develop new features</li>
              <li>Ensure security and prevent fraud</li>
              <li>Comply with legal obligations</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">4. Data Sharing and Disclosure</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              We do not sell your personal information. These are all the companies that receive data when you use Overlabels, what they receive, and
              why. Where a company is outside the EU, see International Data Transfers below.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Services that run Overlabels</h3>
            <div class="mb-6 overflow-x-auto">
              <table class="w-full min-w-xl border-collapse text-left text-sm text-gray-600 dark:text-gray-400">
                <thead>
                  <tr class="border-b border-gray-300 text-gray-900 dark:border-gray-700 dark:text-white">
                    <th class="py-2 pr-4 font-semibold">Service</th>
                    <th class="py-2 pr-4 font-semibold">What it receives</th>
                    <th class="py-2 pr-4 font-semibold">Why</th>
                    <th class="py-2 font-semibold">Where</th>
                  </tr>
                </thead>
                <tbody class="align-top">
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Linode (Akamai)</td>
                    <td class="py-2 pr-4">Everything Overlabels stores</td>
                    <td class="py-2 pr-4">Our servers and database</td>
                    <td class="py-2">Frankfurt, Germany</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Cloudflare</td>
                    <td class="py-2 pr-4">
                      All traffic to our websites, uploaded images, the page analytics described above, and the events Twitch sends us about your
                      channel, held in a queue until our servers pick them up (normally a second or two, at most four days if our servers are down)
                    </td>
                    <td class="py-2 pr-4">Network, security, image storage, analytics</td>
                    <td class="py-2">Images in the EU. Network and event queue worldwide</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Scaleway</td>
                    <td class="py-2 pr-4">Database backups</td>
                    <td class="py-2 pr-4">A second backup copy</td>
                    <td class="py-2">Paris, France</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Twitch</td>
                    <td class="py-2 pr-4">Your login, and the requests Overlabels makes for your channel</td>
                    <td class="py-2 pr-4">Overlabels is built on Twitch</td>
                    <td class="py-2">United States</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Bunny Fonts</td>
                    <td class="py-2 pr-4">Your IP address, when a page loads its font</td>
                    <td class="py-2 pr-4">The font our website uses</td>
                    <td class="py-2">EU</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Healthchecks.io</td>
                    <td class="py-2 pr-4">No personal data, only a signal that the daily backup ran</td>
                    <td class="py-2 pr-4">Warns us when a backup fails</td>
                    <td class="py-2">EU</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Services used only when you use a feature</h3>
            <div class="mb-6 overflow-x-auto">
              <table class="w-full min-w-xl border-collapse text-left text-sm text-gray-600 dark:text-gray-400">
                <thead>
                  <tr class="border-b border-gray-300 text-gray-900 dark:border-gray-700 dark:text-white">
                    <th class="py-2 pr-4 font-semibold">Service</th>
                    <th class="py-2 pr-4 font-semibold">What it receives</th>
                    <th class="py-2 pr-4 font-semibold">When</th>
                    <th class="py-2 font-semibold">Where</th>
                  </tr>
                </thead>
                <tbody class="align-top">
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee, Throne</td>
                    <td class="py-2 pr-4">The connection details, and the events the service sends us</td>
                    <td class="py-2 pr-4">Only the services you connect</td>
                    <td class="py-2">Differs per service</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">ElevenLabs</td>
                    <td class="py-2 pr-4">Only the text of an alert you set up to be spoken</td>
                    <td class="py-2 pr-4">Text-to-speech alerts</td>
                    <td class="py-2">United States</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Freesound</td>
                    <td class="py-2 pr-4">Only your search terms</td>
                    <td class="py-2 pr-4">Searching the sound library</td>
                    <td class="py-2">Spain</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">Discord</td>
                    <td class="py-2 pr-4">Your Twitch name and ID and what you wrote, when you suggest an integration</td>
                    <td class="py-2 pr-4">The suggestion form. Backup alerts go there too, with no personal data</td>
                    <td class="py-2">United States</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Services your browser or OBS contacts directly</h3>
            <p class="mb-3 text-gray-600 dark:text-gray-400">
              Some content is loaded by the browser showing it, not by our servers. These services see the IP address of that browser, which for an
              overlay is the computer running OBS.
            </p>
            <div class="mb-4 overflow-x-auto">
              <table class="w-full min-w-xl border-collapse text-left text-sm text-gray-600 dark:text-gray-400">
                <thead>
                  <tr class="border-b border-gray-300 text-gray-900 dark:border-gray-700 dark:text-white">
                    <th class="py-2 pr-4 font-semibold">Service</th>
                    <th class="py-2 pr-4 font-semibold">What it receives</th>
                    <th class="py-2 font-semibold">When</th>
                  </tr>
                </thead>
                <tbody class="align-top">
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">Twitch</td>
                    <td class="py-2 pr-4">IP address, and which channel's chat is read</td>
                    <td class="py-2">Overlays that show chat read it straight from Twitch</td>
                  </tr>
                  <tr class="border-b border-gray-200 dark:border-gray-800">
                    <td class="py-2 pr-4">BetterTTV, FrankerFaceZ, 7TV</td>
                    <td class="py-2 pr-4">IP address, and which channel's emotes are loaded</td>
                    <td class="py-2">Overlays that show chat emotes</td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4">OpenStreetMap</td>
                    <td class="py-2 pr-4">IP address, and which part of the map is shown</td>
                    <td class="py-2">GPS maps</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="mb-6 text-gray-600 dark:text-gray-400">
              Anything you add to your own overlays yourself, such as fonts or images from another website, is loaded from wherever you point it.
            </p>

            <p class="mb-2 text-gray-600 dark:text-gray-400">Beyond these services, we only share your data:</p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
              <li><strong>Business Transfers:</strong> In connection with a merger, sale, or acquisition</li>
              <li><strong>Your Consent:</strong> With your explicit permission</li>
            </ul>

            <h3 class="mt-6 mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Who at Overlabels can see your content</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Overlabels administrators can see the contents of your account, whether you made something private or not. That includes the source
              code, screenshots and controls of your overlays and alerts, your kits, your Lists, your bot commands, the events on your account, the
              names and access logs of your overlay access tokens (including IP addresses), and your login sessions. Making an overlay or kit private
              hides it from other users and from the public. It does not hide it from administrators.
            </p>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Administrators can also open your Overlabels account as if they were you (we call this impersonation). While they do, they see and can
              do everything you can do in Overlabels, including editing your overlays and using the Twitch permissions you gave Overlabels when you
              connected your channel. Almost all of those permissions only read data. The one that changes anything lets Overlabels edit your
              channel's stream information, such as its title and category. This does not give them access to your Twitch account itself: they never
              see your Twitch password, they can't log in to Twitch as you, and they can't do anything on Twitch beyond what you authorized Overlabels
              to do. Starting and stopping impersonation is recorded in an audit log with the administrator's identity and IP address. They cannot see
              your overlay access tokens themselves: we only store a fingerprint of each token, not the token.
            </p>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              <strong>Our promise: we stay out of your overlays.</strong> Being able to edit your account is not the same as being allowed to.
              Administrators never add to or change what you host on Overlabels unless you explicitly ask us to. You can ask in a Discord
              conversation, by email or in a GitHub issue. The only time we change or remove your content, or remove your account, without being asked
              is when something you host on Overlabels breaks our
              <a href="/terms" class="text-purple-600 hover:underline dark:text-purple-400">Terms of Service</a>. This is a promise, not a technical
              limit. What you show on your Twitch stream remains your own responsibility.
            </p>
            <p class="text-gray-600 dark:text-gray-400">
              We look at your content to answer your support requests, to investigate reports and abuse, and to keep the service working. The
              administrator role is held by the people who run Overlabels, and is given to as few accounts as possible. More detail is on
              <a href="/help/your-data" class="text-purple-600 hover:underline dark:text-purple-400">Your data on Overlabels</a>.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">5. Data Security</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              We implement appropriate technical and organizational measures to protect your personal information, including:
            </p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>TLS in transit, and encryption at rest for every credential we hold: your Twitch tokens and every integration credential</li>
              <li>Regular security audits and updates</li>
              <li>Access controls and authentication</li>
              <li>Secure API token management</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">6. Data Retention</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              We retain your personal information for as long as necessary to provide our services and fulfill the purposes outlined in this policy.
              When you delete your account, we will delete or anonymize your personal information within 30 days, except where we are required to
              retain it by law.
            </p>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Lists and snapshots</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              You can create Lists through the dashboard or have viewers populate them through chat commands. Every destructive action on a List
              (clear, draw, pop, restore) automatically creates a snapshot of its previous contents so you can recover from mistakes. Snapshots that
              you have not explicitly pinned are deleted automatically 30 days after they were created and cannot be recovered after that point.
              Pinned snapshots are retained until you unpin or delete them.
            </p>
            <p class="text-gray-600 dark:text-gray-400">
              Lists you delete are removed immediately, along with all their associated snapshots (including pinned ones). Configuring a List with a
              deadline causes its contents to be snapshotted, cleared, and the List disabled at that moment; the resulting snapshot follows the same
              30-day retention rule.
            </p>

            <h3 class="mt-6 mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">Reporting a public overlay</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Every publicly listed overlay carries a "Report" button. Submitting a report stores the reason you wrote, which overlay it was about,
              the date, and the IP address the report was sent from. If you are logged in it also stores your Overlabels account, so we can see who
              filed it. If you are not logged in it stores the email address you entered instead. We do not verify that address; it exists only so we
              can reply to you about the report, and we do not add it to any mailing list.
            </p>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              The IP address is stored to detect people filing large numbers of reports, and for nothing else. Reports are visible only to Overlabels
              administrators. They are not shown to the person whose overlay you reported, and we do not tell them who reported it.
            </p>
            <p class="text-gray-600 dark:text-gray-400">
              A report is deleted automatically 180 days after an administrator marks it as handled, which removes the reason, the email address and
              the IP address along with it. Administrators can also delete a report at any time before that. You can ask us to delete a report you
              filed by contacting us at the address in section 12; if you filed it anonymously, write from the address you used so we can find it.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">7. Your Rights</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">You have the right to:</p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Access your personal information</li>
              <li>Correct inaccurate data</li>
              <li>Request deletion of your data</li>
              <li>Object to processing of your data</li>
              <li>Export your data in a portable format</li>
              <li>Withdraw consent at any time</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">8. Cookies</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">We use cookies only to:</p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Keep you logged in</li>
              <li>Remember your preferences</li>
              <li>Improve service performance</li>
            </ul>
            <p class="mt-4 text-gray-600 dark:text-gray-400">
              You can control cookies through your browser settings, but disabling them may affect service functionality.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">9. Children's Privacy</h2>
            <p class="text-gray-600 dark:text-gray-400">
              Our service is not directed to individuals under 13 years of age. We do not knowingly collect personal information from children under
              13. If you become aware that a child has provided us with personal information, please contact us.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">10. International Data Transfers</h2>
            <p class="text-gray-600 dark:text-gray-400">
              Overlabels is run from the EU and stores your data in the EU. Some of the services listed in section 4 are outside it: Twitch,
              ElevenLabs and Discord are in the United States, Cloudflare's network and event queue run worldwide, and OpenStreetMap is in the United
              Kingdom. For the United States we rely on the EU-US Data Privacy Framework where the company is certified under it, and otherwise on the
              European Commission's Standard Contractual Clauses. The United Kingdom is covered by an adequacy decision of the European Commission.
              The donation services you choose to connect handle your data under their own policies, wherever they are based.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">11. Changes to This Policy</h2>
            <p class="text-gray-600 dark:text-gray-400">
              We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page
              and updating the "Last updated" date. We encourage you to review this Privacy Policy periodically.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">12. Contact Us</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">If you have questions or concerns about this Privacy Policy, please contact us at:</p>
            <div class="rounded-lg bg-gray-100 p-4 dark:bg-gray-800">
              <p class="text-gray-700 dark:text-gray-300">
                Email: jasper@emailjasper.com<br />
                Discord:
                <a
                  href="https://discord.gg/qANdj5QPpd"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-purple-600 hover:underline dark:text-purple-400"
                  >discord.gg/qANdj5QPpd</a
                >
              </p>
            </div>
          </section>
        </div>

        <div class="mt-12 border-t border-gray-200 pt-8 dark:border-gray-800">
          <Button variant="outline" asChild>
            <a href="/" class="cursor-pointer gap-2">
              <ArrowLeft class="h-4 w-4" />
              Back to Home
            </a>
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
