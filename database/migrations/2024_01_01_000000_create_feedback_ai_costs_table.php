<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_ai_costs', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('model');
            $table->unsignedInteger('prompt_tokens');
            $table->unsignedInteger('completion_tokens');
            $table->unsignedInteger('total_tokens');
            $table->string('feedback_type');
            $table->timestamp('created_at')->useCurrent()->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_ai_costs');
    }
};
