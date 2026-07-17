<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false)->change();
        });

        // Opt-in model: auto-reply should only ever run for contacts the owner has explicitly
        // reviewed and enabled, not everyone by default. Reset existing rows accordingly.
        DB::table('contacts')->update(['ai_enabled' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(true)->change();
        });
    }
};
