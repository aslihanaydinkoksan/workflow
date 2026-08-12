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
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_role_id')->nullable()->after('assigned_to');
            // Biz roles tablosuyla foreign key yapmıyoruz, çünkü rol silinirse bile nullOnDelete veya cascade gerekir. 
            // Kullanıcı "eski veriler dursun" dediği için foreign constraint eklemiyoruz veya nullOnDelete yapıyoruz.
            // Fakat Spatie/Permissions paketinin tablosu "roles" olduğu için constraint atabiliriz.
            $table->foreign('assigned_role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_role_id']);
            $table->dropColumn('assigned_role_id');
        });
    }
};
