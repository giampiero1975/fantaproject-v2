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
            $table->dropColumn('username');
            $table->dropColumn('password');
            $table->dropColumn('account_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proxy_services', function (Blueprint $table) {
            $table->string('account_endpoint')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
        });
    }
};
