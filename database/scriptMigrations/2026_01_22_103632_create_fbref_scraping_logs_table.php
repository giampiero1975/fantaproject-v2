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
        Schema::create('fbref_scraping_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('player_id')->nullable()->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('fbref_id')->nullable()->index();
            $table->enum('scrape_type', ['team', 'player_intensive'])->index();
            $table->text('url');
            $table->integer('http_status')->nullable();
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending')->index();
            $table->text('error_details')->nullable();
            $table->decimal('execution_time_seconds')->nullable();
            $table->integer('payload_size_bytes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fbref_scraping_logs');
    }
};
