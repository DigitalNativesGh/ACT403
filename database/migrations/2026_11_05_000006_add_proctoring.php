<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedInteger('raw_score')->nullable()->after('score'); // correct answers before deductions
            $table->unsignedInteger('penalty')->default(0)->after('raw_score');
            $table->unsignedSmallInteger('talk_count')->default(0)->after('penalty');
            $table->unsignedSmallInteger('head_turn_count')->default(0)->after('talk_count');
            $table->string('closed_reason')->nullable()->after('submitted_at');
        });

        Schema::create('proctor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                     // talking | head_turn | detector_unavailable
            $table->unsignedTinyInteger('penalty')->default(0);
            $table->dateTime('created_at');
            $table->index(['attempt_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctor_events');
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['raw_score', 'penalty', 'talk_count', 'head_turn_count', 'closed_reason']);
        });
    }
};
