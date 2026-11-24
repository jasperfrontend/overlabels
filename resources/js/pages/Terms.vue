<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import LoginSocial from '@/components/LoginSocial.vue';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, ArrowRight } from '@lucide/vue';

const lastUpdated = 'November 24, 2026';
const effectiveDate = 'January 13, 2025';
</script>

<template>
  <div>
    <Head title="Terms of Service">
      <meta name="description" content="Overlabels Terms of Service - Read our terms and conditions for using our stream overlay platform." />
    </Head>

    <div class="min-h-screen bg-linear-to-b from-white to-gray-50 dark:from-gray-950 dark:to-gray-900">
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
        <h1 class="mb-2 text-4xl font-bold text-gray-900 dark:text-white">Terms of Service</h1>
        <p class="mb-8 text-gray-600 dark:text-gray-400">Last updated: {{ lastUpdated }} | Effective date: {{ effectiveDate }}</p>

        <div class="prose prose-gray dark:prose-invert max-w-none space-y-8">
          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">1. Agreement to Terms</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              By accessing or using Overlabels ("Service"), you agree to be bound by these Terms of Service ("Terms"). If you disagree with any part
              of these terms, you do not have permission to access the Service.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">2. Description of Service</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Overlabels provides a platform for creating, managing, and displaying stream overlays for Twitch broadcasters. Our Service includes:
            </p>
            <ul class="list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Overlay template creation and customization tools</li>
              <li>Real-time integration with Twitch EventSub</li>
              <li>Browser source URLs for OBS and streaming software</li>
              <li>Analytics and performance tracking</li>
              <li>Template storage and management</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">3. Account Registration</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">3.1 Account Creation</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              To use our Service, you must authenticate through Twitch OAuth. You are responsible for maintaining the security of your Twitch account
              and for all activities that occur under your account.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">3.2 Account Requirements</h3>
            <ul class="mb-4 list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>You must be at least 13 years old to use our Service</li>
              <li>You must have a valid Twitch account in good standing</li>
              <li>You must provide accurate and complete information</li>
              <li>You are responsible for all activity under your account</li>
            </ul>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">4. Acceptable Use Policy</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">4.1 Permitted Use</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">You may use our Service only for lawful purposes and in accordance with these Terms.</p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">4.2 Prohibited Use</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Everything you host on Overlabels ends up on a Twitch stream, so the simplest rule comes first: do not host anything you could not show
              on Twitch under the
              <a
                href="https://safety.twitch.tv/s/article/Community-Guidelines"
                target="_blank"
                rel="noopener noreferrer"
                class="text-purple-600 hover:underline dark:text-purple-400"
                >Twitch Community Guidelines</a
              >. On top of that, you may not host, show or link to:
            </p>
            <ul class="mb-4 list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Anything that sexualises, exploits or endangers minors. There are no exceptions to this rule.</li>
              <li>Sexual content or nudity</li>
              <li>Hate speech, racism, or content that attacks people for who they are</li>
              <li>Harassment, threats, or content that targets a specific person</li>
              <li>Anything that sells or promotes weapons, drugs or other illegal goods and services</li>
              <li>Gambling, scams, fraud or deceptive offers</li>
              <li>Other people's personal data, such as addresses, phone numbers or private messages</li>
              <li>Content that impersonates a person, a streamer or a brand</li>
              <li>Content you do not have the right to use, such as someone else's artwork, music or fonts</li>
            </ul>
            <p class="mb-2 text-gray-600 dark:text-gray-400">You also agree not to:</p>
            <ul class="mb-4 list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Break any law that applies to you or to Overlabels</li>
              <li>Upload malicious code, crypto miners, or anything built to harm or track the people who see your overlay</li>
              <li>Use Overlabels for spam, click farming, view or follow botting, or other fake engagement</li>
              <li>Claim to work for, speak for, or be connected to Overlabels, unless we have agreed to it in writing</li>
              <li>Try to get into accounts or systems that are not yours</li>
              <li>Interfere with or disrupt the Service</li>
              <li>Resell access to the hosted Service without our permission</li>
            </ul>
            <p class="text-gray-600 dark:text-gray-400">
              We decide whether content breaks these rules. When it does, we may remove the content or close the account that hosts it.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">5. Intellectual Property Rights</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">5.1 Our Property</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              The Service and its original content, features, and functionality are owned by Overlabels and are protected by international copyright,
              trademark, patent, trade secret, and other intellectual property laws.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">5.2 Your Content</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              You retain ownership of any content you create using our Service. By using the Service, you grant us a worldwide, non-exclusive,
              royalty-free license to use, reproduce, and display your content as necessary to provide the Service.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">5.3 Feedback</h3>
            <p class="text-gray-600 dark:text-gray-400">
              Any feedback, suggestions, or ideas you provide about the Service becomes our property and may be used without compensation or
              attribution.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">6. Privacy and Data Protection</h2>
            <p class="text-gray-600 dark:text-gray-400">
              Our Privacy Policy explains what data we collect, why, and who receives it. We process your data because it is needed to provide the
              Service you signed up for, and, for things like security and preventing abuse, because we have a legitimate interest in keeping the
              Service safe and working. Where we need your consent for something, we ask for it separately.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">7. Third-Party Services</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Overlabels works with other services, such as Twitch and the donation services you choose to connect. Every company that receives data,
              what it receives and why, is listed in our
              <a href="/privacy" class="text-purple-600 hover:underline dark:text-purple-400">Privacy Policy</a>.
            </p>
            <p class="text-gray-600 dark:text-gray-400">
              When you use another service through Overlabels, that service's own terms and privacy policy also apply. We are not responsible for how
              other services handle your data.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">8. Disclaimers and Limitations of Liability</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">8.1 Service Availability</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              The Service is provided "as is" and "as available" without warranties of any kind. We do not guarantee uninterrupted, secure, or
              error-free operation of the Service.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">8.2 Limitation of Liability</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              To the maximum extent permitted by law, Overlabels shall not be liable for any indirect, incidental, special, consequential, or punitive
              damages, including loss of profits, data, or goodwill.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">8.3 Indemnification</h3>
            <p class="text-gray-600 dark:text-gray-400">
              You agree to indemnify and hold harmless Overlabels from any claims, damages, or expenses arising from your use of the Service or
              violation of these Terms.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">9. Termination</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">9.1 Termination by You</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              You may terminate your account at any time through the account settings or by contacting support.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">9.2 Termination by Us</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">We may suspend or terminate your account immediately for:</p>
            <ul class="mb-4 list-disc space-y-2 pl-6 text-gray-600 dark:text-gray-400">
              <li>Violation of these Terms</li>
              <li>Fraudulent or illegal activity</li>
              <li>Non-payment of fees</li>
              <li>Harmful behavior toward other users or the Service</li>
            </ul>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">9.3 Effect of Termination</h3>
            <p class="text-gray-600 dark:text-gray-400">
              Upon termination, your right to use the Service will immediately cease. We may delete your account data after a reasonable period,
              except as required by law.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">10. Dispute Resolution</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">10.1 Informal Resolution</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">We encourage you to contact us first to resolve any disputes informally.</p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">10.2 Governing Law</h3>
            <p class="text-gray-600 dark:text-gray-400">
              These Terms are governed by Dutch law. If you are a consumer living in the EU, you also keep the protection of the mandatory consumer
              laws of the country you live in.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">11. Changes to Terms</h2>
            <p class="text-gray-600 dark:text-gray-400">
              We may change these Terms at any time. We will not notify you about changes: the "Last updated" date at the top of this page shows when
              they last changed. Continued use of the Service after a change means you accept the changed Terms.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">12. General Provisions</h2>
            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">12.1 Entire Agreement</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              These Terms constitute the entire agreement between you and Overlabels regarding the Service.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">12.2 Severability</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              If any provision of these Terms is found to be unenforceable, the remaining provisions will continue in effect.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">12.3 Waiver</h3>
            <p class="mb-4 text-gray-600 dark:text-gray-400">
              Our failure to enforce any right or provision of these Terms will not be considered a waiver.
            </p>

            <h3 class="mb-3 text-xl font-semibold text-gray-800 dark:text-gray-200">12.4 Assignment</h3>
            <p class="text-gray-600 dark:text-gray-400">
              You may not assign or transfer these Terms without our prior written consent. We may assign our rights and obligations without
              restriction.
            </p>
          </section>

          <section>
            <h2 class="mb-4 text-2xl font-semibold text-gray-900 dark:text-white">13. Contact Information</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-400">For questions about these Terms of Service, please contact us at:</p>
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
