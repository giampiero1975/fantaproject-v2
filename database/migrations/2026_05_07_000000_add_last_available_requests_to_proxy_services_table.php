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
        Schema::table('proxy_services', function (Blueprint $table) {
            if (!Schema::hasColumn('proxy_services', 'last_available_requests')) {
                $table->integer('last_available_requests')->nullable()->default(0)->after('current_usage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proxy_services', function (Blueprint $table) {
            if (Schema::hasColumn('proxy_services', 'last_available_requests')) {
                $table->dropColumn('last_available_requests');
            }
        });
    }
};
