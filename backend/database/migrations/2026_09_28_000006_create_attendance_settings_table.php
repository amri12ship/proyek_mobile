<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table): void {
            $table->id();
            $table->time('work_start')->default('08:00:00');
            $table->time('work_end')->default('17:00:00');
            $table->unsignedInteger('tolerance_minutes')->default(15);
            $table->unsignedInteger('radius_meter')->default(200);
            $table->decimal('max_accuracy_meter', 8, 2)->default(50);
            $table->unsignedInteger('ticket_ttl_seconds')->default(120);
            $table->unsignedInteger('max_ticket_per_day')->default(10);
            $table->boolean('require_selfie')->default(true);
            $table->string('timezone', 60)->default('Asia/Jakarta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
