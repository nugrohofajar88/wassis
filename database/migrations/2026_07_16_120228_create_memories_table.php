<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['short_term', 'long_term', 'relationship', 'style']);
            $table->text('content');
            $table->tinyInteger('importance_score')->default(5)->comment('1-10');
            $table->timestamp('expires_at')->nullable()->comment('Only for short_term');
            $table->timestamps();

            $table->index(['user_id', 'contact_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
