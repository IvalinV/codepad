<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('snippet_renders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snippet_id')->constrained()->cascadeOnDelete();
            $table->string('theme');
            $table->json('content');
            $table->string('hash');
            $table->timestamps();

            $table->unique(['snippet_id', 'theme']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snippet_renders');
    }
};
