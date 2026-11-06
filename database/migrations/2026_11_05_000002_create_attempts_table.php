<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->string('index_number', 30)->unique();
            $table->string('uin', 30);
            $table->json('question_ids');          // order shown to this student
            $table->json('answers')->nullable();   // {question_id: "A"}
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('total');
            $table->dateTime('started_at');
            $table->dateTime('ends_at');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
