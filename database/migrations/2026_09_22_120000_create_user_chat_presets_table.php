<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A streamer's own looks for the Twitch Chat product.
 *
 * The ten built-in looks are a constant (ChatPresets::PRESETS) and nothing
 * about them is stored; which one is active is derived by comparing the
 * overlay's controls to the bundles. A look the streamer tuned themselves was
 * stored nowhere, so the moment they clicked a built-in preset it was gone.
 *
 * One row is one named bundle: the values of the overlay's look controls at
 * the moment it was saved, keyed by control key exactly as a built-in preset
 * holds them. Applying one writes those controls through the same path the
 * built-in presets use. Nothing here says which row is active - that stays
 * derived, for the same reason.
 *
 * Per user, not per overlay: the product allows one install per account, and
 * a look outliving an uninstall-and-reinstall is the point of saving it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_chat_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->json('values');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chat_presets');
    }
};
