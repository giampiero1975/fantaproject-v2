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
            $table->string('slug')->nullable()->after('name');
            $table->integer('js_cost')->default(1)->after('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proxy_services', function (Blueprint $table) {
            //
        });
    }
};
