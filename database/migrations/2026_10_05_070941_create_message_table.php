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
        Schema::create('message', function (Blueprint $table) {
            $table->id('id_message');
            $table->foreignId('id_conversation')->constrained('conversation', 'id_conversation')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->enum('type_message', [
                'text',
                'image',
                'video',
                'audio',
                'file'
            ])->default('text');
            $table->text('message')->nullable();
            $table->string('attachment')->nullable();

            $table->foreignId('reply_to_id')->nullable()->constrained('message', 'id_message')->nullOnDelete();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();

            $table->index(['id_conversation', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message');
    }
};
