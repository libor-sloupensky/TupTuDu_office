<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('users', 'sys_users');
        Schema::rename('password_reset_tokens', 'sys_password_reset_tokens');
        Schema::rename('sessions', 'sys_sessions');

        Schema::table('sys_users', function (Blueprint $table) {
            $table->renameColumn('name', 'jmeno');
        });

        Schema::table('sys_users', function (Blueprint $table) {
            $table->string('prijmeni')->after('jmeno');
            $table->string('telefon', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn(['prijmeni', 'telefon']);
        });

        Schema::table('sys_users', function (Blueprint $table) {
            $table->renameColumn('jmeno', 'name');
        });

        Schema::rename('sys_users', 'users');
        Schema::rename('sys_password_reset_tokens', 'password_reset_tokens');
        Schema::rename('sys_sessions', 'sessions');
    }
};
