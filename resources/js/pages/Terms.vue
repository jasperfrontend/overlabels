<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Layers } from 'lucide-vue-next';

const lastUpdated = 'January 13, 2025';
const effectiveDate = 'January 13, 2025';
</script>

<template>
<div>
    <Head title="Terms of Service - Overlabels">
        <meta name="description" content="Overlabels Terms of Service - Read our terms and conditions for using our stream overlay platform." />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-b from-white to-gray-50 dark:from-gray-950 dark:to-gray-900">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full border-b bg-white/80 backdrop-blur-md dark:bg-gray-950/80 dark:border-gray-800">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-2">
                        <Layers class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                        <span class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 dark:from-purple-400 dark:to-pink-400 bg-clip-text text-transparent">
                            Overlabels
                        </span>
                    </Link>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/" class="gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Back to Home
                        </Link>
                    </Button>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container mx-auto px-6 py-12 max-w-4xl">
            <h1 class="text-4xl font-bold mb-2 text-gray-900 dark:text-white">Terms of Service</h1>
            <p class="text-gray-600 dark:text-gray-400 mb-8">
                Last updated: {{ lastUpdated }} | Effective date: {{ effectiveDate }}
            </p>

            <div class="prose prose-gray dark:prose-invert max-w-none space-y-8">
                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">1. Agreement to Terms</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        By accessing or using Overlabels ("Service"), you agree to be bound by these Terms of Service ("Terms"). 
                        If you disagree with any part of these terms, you do not have permission to access the Service.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">2. Description of Service</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Overlabels provides a platform for creating, managing, and displaying stream overlays for Twitch broadcasters. 
                        Our Service includes:
                    </p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Overlay template creation and customization tools</li>
                        <li>Real-time integration with Twitch EventSub</li>
                        <li>Browser source URLs for OBS and streaming software</li>
                        <li>Analytics and performance tracking</li>
                        <li>Template storage and management</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">3. Account Registration</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">3.1 Account Creation</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        To use our Service, you must authenticate through Twitch OAuth. You are responsible for maintaining 
                        the security of your Twitch account and for all activities that occur under your account.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">3.2 Account Requirements</h3>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2 mb-4">
                        <li>You must be at least 13 years old to use our Service</li>
                        <li>You must have a valid Twitch account in good standing</li>
                        <li>You must provide accurate and complete information</li>
                        <li>You are responsible for all activity under your account</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">4. Acceptable Use Policy</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">4.1 Permitted Use</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">You may use our Service only for lawful purposes and in accordance with these Terms.</p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">4.2 Prohibited Use</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-2">You agree not to:</p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Violate any applicable laws or regulations</li>
                        <li>Infringe on intellectual property rights</li>
                        <li>Upload malicious code or harmful content</li>
                        <li>Attempt to gain unauthorized access to our systems</li>
                        <li>Interfere with or disrupt the Service</li>
                        <li>Create overlays containing hate speech, harassment, or inappropriate content</li>
                        <li>Use the Service for spam or fraudulent purposes</li>
                        <li>Reverse engineer or attempt to extract source code</li>
                        <li>Resell or redistribute the Service without permission</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">5. Subscription and Pricing</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">5.1 Free Tier</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        We offer a free tier with limited features. Free accounts may have restrictions on the number of overlays, 
                        templates, and available features.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">5.2 Paid Subscriptions</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Paid subscriptions provide access to additional features and resources. Subscription fees are billed in advance 
                        on a monthly or annual basis and are non-refundable except as required by law.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">5.3 Billing</h3>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Payments are processed through secure third-party payment processors</li>
                        <li>You authorize us to charge your payment method for recurring fees</li>
                        <li>Price changes will be notified 30 days in advance</li>
                        <li>Failed payments may result in service suspension</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">6. Intellectual Property Rights</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">6.1 Our Property</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The Service and its original content, features, and functionality are owned by Overlabels and are protected 
                        by international copyright, trademark, patent, trade secret, and other intellectual property laws.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">6.2 Your Content</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        You retain ownership of any content you create using our Service. By using the Service, you grant us a 
                        worldwide, non-exclusive, royalty-free license to use, reproduce, and display your content as necessary 
                        to provide the Service.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">6.3 Feedback</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Any feedback, suggestions, or ideas you provide about the Service becomes our property and may be used 
                        without compensation or attribution.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">7. Privacy and Data Protection</h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Your use of the Service is also governed by our Privacy Policy. By using the Service, you consent to 
                        the collection and use of information as detailed in the Privacy Policy.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">8. Third-Party Services</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Our Service integrates with third-party services, including:
                    </p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2 mb-4">
                        <li>Twitch API and EventSub services</li>
                        <li>Payment processors</li>
                        <li>Analytics providers</li>
                        <li>Cloud hosting services</li>
                    </ul>
                    <p class="text-gray-600 dark:text-gray-400">
                        Your use of these third-party services is subject to their respective terms and privacy policies. 
                        We are not responsible for the practices of third-party services.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">9. Disclaimers and Limitations of Liability</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">9.1 Service Availability</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The Service is provided "as is" and "as available" without warranties of any kind. We do not guarantee 
                        uninterrupted, secure, or error-free operation of the Service.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">9.2 Limitation of Liability</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        To the maximum extent permitted by law, Overlabels shall not be liable for any indirect, incidental, 
                        special, consequential, or punitive damages, including loss of profits, data, or goodwill.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">9.3 Indemnification</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        You agree to indemnify and hold harmless Overlabels from any claims, damages, or expenses arising from 
                        your use of the Service or violation of these Terms.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">10. Termination</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">10.1 Termination by You</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        You may terminate your account at any time through the account settings or by contacting support.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">10.2 Termination by Us</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        We may suspend or terminate your account immediately for:
                    </p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2 mb-4">
                        <li>Violation of these Terms</li>
                        <li>Fraudulent or illegal activity</li>
                        <li>Non-payment of fees</li>
                        <li>Harmful behavior toward other users or the Service</li>
                    </ul>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">10.3 Effect of Termination</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Upon termination, your right to use the Service will immediately cease. We may delete your account data 
                        after a reasonable period, except as required by law.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">11. Dispute Resolution</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">11.1 Informal Resolution</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        We encourage you to contact us first to resolve any disputes informally.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">11.2 Binding Arbitration</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Any disputes not resolved informally shall be resolved through binding arbitration in accordance with 
                        the rules of the American Arbitration Association.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">11.3 Governing Law</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        These Terms shall be governed by the laws of the United States and the State of Delaware, without regard 
                        to conflict of law principles.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">12. Changes to Terms</h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        We reserve the right to modify these Terms at any time. Material changes will be notified via email or 
                        through the Service at least 30 days before taking effect. Continued use of the Service after changes 
                        constitutes acceptance of the modified Terms.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">13. General Provisions</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">13.1 Entire Agreement</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        These Terms constitute the entire agreement between you and Overlabels regarding the Service.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">13.2 Severability</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        If any provision of these Terms is found to be unenforceable, the remaining provisions will continue in effect.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">13.3 Waiver</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Our failure to enforce any right or provision of these Terms will not be considered a waiver.
                    </p>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">13.4 Assignment</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        You may not assign or transfer these Terms without our prior written consent. We may assign our rights 
                        and obligations without restriction.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">14. Contact Information</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        For questions about these Terms of Service, please contact us at:
                    </p>
                    <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300">
                            Email: legal@overlabels.com<br>
                            Discord: <a href="https://discord.gg/overlabels" target="_blank" rel="noopener noreferrer" class="text-purple-600 dark:text-purple-400 hover:underline">discord.gg/overlabels</a><br>
                            Support: support@overlabels.com
                        </p>
                    </div>
                </section>
            </div>

            <div class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-800">
                <Button variant="outline" asChild>
                    <Link href="/" class="gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back to Home
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</div>
</template>