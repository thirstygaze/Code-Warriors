<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->index();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('student_number')->unique();
            $table->string('grade_level')->nullable();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('employee_number')->nullable()->unique();
            $table->string('position')->default('Canteen staff');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('category_name', 80)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->foreignId('category_id')->constrained('categories', 'category_id')->restrictOnDelete();
            $table->string('item_name', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('availability')->default(true);
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->index(['category_id', 'availability']);
        });

        Schema::create('inventory', function (Blueprint $table) {
            $table->id('inventory_id');
            $table->foreignId('item_id')->unique()->constrained('menu_items', 'item_id')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(5);
            $table->timestamps();
        });

        Schema::create('menu_specials', function (Blueprint $table) {
            $table->id('special_id');
            $table->foreignId('item_id')->constrained('menu_items', 'item_id')->cascadeOnDelete();
            $table->decimal('special_price', 10, 2);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['item_id', 'start_date', 'end_date']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('order_date');
            $table->time('pickup_time');
            $table->string('status', 24)->default('pending')->index();
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
            $table->index(['user_id', 'order_date']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->foreignId('order_id')->constrained('orders', 'order_id')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('menu_items', 'item_id')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
            $table->index(['order_id', 'item_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('order_id')->unique()->constrained('orders', 'order_id')->cascadeOnDelete();
            $table->string('payment_method', 40)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_status', 24)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders', 'order_id')->nullOnDelete();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_read']);
        });

        Schema::create('staff_orders', function (Blueprint $table) {
            $table->id('staff_order_id');
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders', 'order_id')->cascadeOnDelete();
            $table->dateTime('assigned_at');
            $table->string('action', 40);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_orders');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('menu_specials');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('students');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
