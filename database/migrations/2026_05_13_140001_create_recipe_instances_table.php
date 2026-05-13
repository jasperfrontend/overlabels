<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Installer-supplied, snake_case. Unique within (user, recipe) so
            // the same user can install Coin Flip twice as long as the slugs
            // differ. Doc Q4: "each instance should have its own specific
            // name, even if Johnny calls both of his Recipes LOLWHEEL".
            $table->string('instance_slug', 50);
            $table->string('label', 255)->nullable();
            // Maps manifest refs to materialised primitive row IDs:
            //   {"option_sets": {"coin": 42}, "pickers": {"flipper": 17}}
            // Used by the bridge listener to translate manifest-side
            // control_exports.from paths into real picker IDs.
            $table->jsonb('primitive_map')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps();

            $table->unique(['user_id', 'recipe_id', 'instance_slug']);
            $table->index('user_id');
        });

        // Nullable FK on the three primitive/control tables so an uninstall
        // cascades cleanly: delete the recipe_instance row and every owned
        // option_set / picker / overlay_control row vanishes with it. NULL
        // means the row was hand-authored, not installed via a recipe.
        Schema::table('option_sets', function (Blueprint $table) {
            $table->foreignId('recipe_instance_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('pickers', function (Blueprint $table) {
            $table->foreignId('recipe_instance_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('overlay_controls', function (Blueprint $table) {
            $table->foreignId('recipe_instance_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index('recipe_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_controls', function (Blueprint $table) {
            $table->dropForeign(['recipe_instance_id']);
            $table->dropIndex(['recipe_instance_id']);
            $table->dropColumn('recipe_instance_id');
        });

        Schema::table('pickers', function (Blueprint $table) {
            $table->dropForeign(['recipe_instance_id']);
            $table->dropColumn('recipe_instance_id');
        });

        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropForeign(['recipe_instance_id']);
            $table->dropColumn('recipe_instance_id');
        });

        Schema::dropIfExists('recipe_instances');
    }
};
