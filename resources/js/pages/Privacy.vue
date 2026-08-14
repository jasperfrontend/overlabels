<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import LoginSocial from '@/components/LoginSocial.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, ArrowRight } from '@lucide/vue';

const lastUpdated = 'August 4, 2026';
</script>

<template>
  <div>
    <Head title="Privacy Policy - Overlabels">
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
              <li>Twitch account information (username, user ID, email address)</li>
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
              <li>Usage data and analytics (page views, feature usage)</li>
              <li>Device information (browser type, operating system)</li>
              <li>IP address and approximate location</li>
              <li>Cookies and similar tracking technologies</li>
            </ul>

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
            <p class="mb-4 text-gray-600 dark:text-gray-400">We do not sell your personal information. We may share your data with:</p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li><strong>Service Providers:</strong> Third-party services that help us operate our platform (hosting, analytics)</li>
              <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
              <li><strong>Business Transfers:</strong> In connection with a merger, sale, or acquisition</li>
              <li><strong>Your Consent:</strong> With your explicit permission</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">5. Data Security</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              We implement appropriate technical and organizational measures to protect your personal information, including:
            </p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Encryption of data in transit and at rest</li>
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
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">8. Cookies and Tracking</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">We use cookies and similar technologies to:</p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Keep you logged in</li>
              <li>Remember your preferences</li>
              <li>Analyze usage patterns</li>
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
              Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to
              protect your information in accordance with this Privacy Policy.
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
                Email: privacy@overlabels.com<br />
                Discord:
                <a
                  href="https://discord.gg/overlabels"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-purple-600 hover:underline dark:text-purple-400"
                  >discord.gg/overlabels</a
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
