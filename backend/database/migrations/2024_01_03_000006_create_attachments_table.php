<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 50)->comment('mis. request, work_order');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('uploaded_at')->useCurrent();

            $table->index(['attachable_type', 'attachable_id'], 'idx_attachable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
