<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Layers } from 'lucide-vue-next';

const lastUpdated = 'January 13, 2025';
</script>

<template>
<div>
    <Head title="Privacy Policy - Overlabels">
        <meta name="description" content="Overlabels Privacy Policy - Learn how we collect, use, and protect your data." />
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
            <h1 class="text-4xl font-bold mb-2 text-gray-900 dark:text-white">Privacy Policy</h1>
            <p class="text-gray-600 dark:text-gray-400 mb-8">Last updated: {{ lastUpdated }}</p>

            <div class="prose prose-gray dark:prose-invert max-w-none space-y-8">
                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">1. Introduction</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Welcome to Overlabels ("we," "our," or "us"). We are committed to protecting your personal information and your right to privacy. 
                        This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our service.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">2. Information We Collect</h2>
                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">Information You Provide</h3>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2 mb-4">
                        <li>Twitch account information (username, user ID, email address)</li>
                        <li>Overlay templates and configurations you create</li>
                        <li>Communication data when you contact our support</li>
                    </ul>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">Information Collected Automatically</h3>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2 mb-4">
                        <li>Usage data and analytics (page views, feature usage)</li>
                        <li>Device information (browser type, operating system)</li>
                        <li>IP address and approximate location</li>
                        <li>Cookies and similar tracking technologies</li>
                    </ul>

                    <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">Information from Twitch</h3>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Stream data (follower count, subscriber count, viewer statistics)</li>
                        <li>Channel information and settings</li>
                        <li>EventSub notifications (follows, raids, subscriptions)</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">3. How We Use Your Information</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">We use your information to:</p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
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
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">4. Data Sharing and Disclosure</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">We do not sell your personal information. We may share your data with:</p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li><strong>Service Providers:</strong> Third-party services that help us operate our platform (hosting, analytics)</li>
                        <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
                        <li><strong>Business Transfers:</strong> In connection with a merger, sale, or acquisition</li>
                        <li><strong>Your Consent:</strong> With your explicit permission</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">5. Data Security</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        We implement appropriate technical and organizational measures to protect your personal information, including:
                    </p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Encryption of data in transit and at rest</li>
                        <li>Regular security audits and updates</li>
                        <li>Access controls and authentication</li>
                        <li>Secure API token management</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">6. Data Retention</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        We retain your personal information for as long as necessary to provide our services and fulfill the purposes outlined in this policy. 
                        When you delete your account, we will delete or anonymize your personal information within 30 days, except where we are required to retain it by law.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">7. Your Rights</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">You have the right to:</p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Access your personal information</li>
                        <li>Correct inaccurate data</li>
                        <li>Request deletion of your data</li>
                        <li>Object to processing of your data</li>
                        <li>Export your data in a portable format</li>
                        <li>Withdraw consent at any time</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">8. Cookies and Tracking</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        We use cookies and similar technologies to:
                    </p>
                    <ul class="list-disc pl-6 text-gray-600 dark:text-gray-400 space-y-2">
                        <li>Keep you logged in</li>
                        <li>Remember your preferences</li>
                        <li>Analyze usage patterns</li>
                        <li>Improve service performance</li>
                    </ul>
                    <p class="text-gray-600 dark:text-gray-400 mt-4">
                        You can control cookies through your browser settings, but disabling them may affect service functionality.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">9. Children's Privacy</h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Our service is not directed to individuals under 13 years of age. We do not knowingly collect personal information from children under 13. 
                        If you become aware that a child has provided us with personal information, please contact us.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">10. International Data Transfers</h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Your information may be transferred to and processed in countries other than your own. 
                        We ensure appropriate safeguards are in place to protect your information in accordance with this Privacy Policy.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">11. Changes to This Policy</h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page 
                        and updating the "Last updated" date. We encourage you to review this Privacy Policy periodically.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-white">12. Contact Us</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        If you have questions or concerns about this Privacy Policy, please contact us at:
                    </p>
                    <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300">
                            Email: privacy@overlabels.com<br>
                            Discord: <a href="https://discord.gg/overlabels" target="_blank" rel="noopener noreferrer" class="text-purple-600 dark:text-purple-400 hover:underline">discord.gg/overlabels</a>
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