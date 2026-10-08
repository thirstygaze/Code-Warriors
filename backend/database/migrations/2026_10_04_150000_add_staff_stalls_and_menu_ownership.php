<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('stall_name', 120)->nullable();
        });

        DB::table('staff')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->select('staff.user_id', 'users.name')
            ->get()
            ->each(fn (object $staff) => DB::table('staff')
                ->where('user_id', $staff->user_id)
                ->update(['stall_name' => $staff->name."'s Stall"]));

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('owner_staff_id')
                ->nullable()
                ->constrained('staff', 'user_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_staff_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('stall_name');
        });
    }
};
