<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('employee')->after('password');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('users', 'photo')) {
                $table->string('photo')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->dateTime('last_login_at')->nullable()->after('photo');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['username', 'role', 'is_active', 'phone', 'photo', 'last_login_at'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns === []) {
            return;
        }

        if (in_array('username', $columns, true)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['username']);
            });
        }

        Schema::table('users', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
