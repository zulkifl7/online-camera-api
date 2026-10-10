<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('cover_mobile_key')->nullable()->after('uploads_enabled');
            $table->string('cover_desktop_key')->nullable()->after('cover_mobile_key');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['cover_mobile_key', 'cover_desktop_key']);
        });
    }
};
