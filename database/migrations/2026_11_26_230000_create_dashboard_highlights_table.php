<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard highlights: short notes about features worth knowing, each with
 * one link, written by an admin at /admin/highlights. Seeded with Jasper's
 * first five (2026-11-26), frozen here as literals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_highlights', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('link_url');
            $table->string('link_label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['Ever wanted to update your title when a new follower comes in? You can do that with Overlabels - and oh so much more with the Living Title feature.', '/settings/title', 'Go set your Living Title in Settings'],
            ['Still using the StreamElements bot for your commands? Switch to the Overlabels bot! It has all features you expect, including !accountage, !followage and any custom command you create yourself.', '/settings/bot/commands', 'Go create a bot command'],
            ['Need a list of things? Your last followers, your 10 last subscribers, a grocery list? Overlabels is really good at keeping "lists of things" and they come with a boatload of features, chat commands, moderation features and you can even use them to run contests because "draw a winner" is built-in. It\'s incredibly powerful!', '/lists', 'Create a list of things'],
            ['Kits are awesome and you should install a kit on your account! A kit is a collection of 1 or more overlays and alerts with a design created by a professional designer and animator. Installing a kit is a few clicks and the results are amazing! Instant awesome alerts on your stream with 0 effort, let\'s go.', '/kits', 'Check out the Kits'],
            ['You can see your recent events on Overlabels in one handy overview! Even better: you can instantly replay an event like a recent follow or sub on your overlays to show an alert again. Bonus feature: the Events feed link you can generate from the Recent Events page is a perfect fit for your smartphone, so you can keep track of latest stream events from your phone.', '/recents', 'You need Recent Events in your life'],
        ];

        foreach ($rows as $i => [$body, $url, $label]) {
            DB::table('dashboard_highlights')->insert([
                'body' => $body,
                'link_url' => $url,
                'link_label' => $label,
                'sort_order' => ($i + 1) * 10,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_highlights');
    }
};
