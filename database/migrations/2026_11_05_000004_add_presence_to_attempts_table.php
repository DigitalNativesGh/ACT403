<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dateTime('last_seen_at')->nullable()->after('submitted_at'); // heartbeat from the quiz page
            $table->boolean('camera_on')->default(false)->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['last_seen_at', 'camera_on']);
        });
    }
};
