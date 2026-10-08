<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();
            $table->foreignId('location_validation_id')->nullable()->constrained('location_validations')->nullOnDelete();
            $table->date('attendance_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_in_distance', 8, 2)->nullable();
            $table->decimal('check_in_accuracy', 8, 2)->nullable();
            $table->string('check_in_selfie')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->decimal('check_out_distance', 8, 2)->nullable();
            $table->decimal('check_out_accuracy', 8, 2)->nullable();
            $table->string('check_out_selfie')->nullable();
            $table->unsignedInteger('work_minutes')->nullable();
            $table->string('status', 20)->default('hadir');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
