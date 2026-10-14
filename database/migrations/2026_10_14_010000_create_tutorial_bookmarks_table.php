<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tutorial_slug', 100);
            $table->timestampsTz();
            $table->unique(['user_id', 'tutorial_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_bookmarks');
    }
};
