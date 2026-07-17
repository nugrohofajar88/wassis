<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('style_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('formality_level')->default(3)->comment('1 (very casual) - 5 (very formal)');
            $table->string('preferred_tone')->nullable()->comment('e.g. warm, professional, playful');
            $table->boolean('uses_emoji')->default(true);
            $table->string('typical_language')->nullable()->comment('e.g. Indonesian, English, Javanese');
            $table->text('summary')->nullable();
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('style_profiles');
    }
};
