<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short-lived WebRTC handshake messages between the monitor page and
     * candidates. Video itself never passes through the server.
     */
    public function up(): void
    {
        Schema::create('rtc_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->string('viewer', 40);     // one id per open monitor tab
            $table->string('ref', 40);        // matches an answer to its offer
            $table->string('type', 10);       // offer | answer
            $table->text('sdp');
            $table->dateTime('created_at')->index();
            $table->index(['attempt_id', 'type']);
            $table->index(['viewer', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rtc_signals');
    }
};
