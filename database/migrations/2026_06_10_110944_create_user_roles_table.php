<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->enum('role', ['Tenant', 'Landlord', 'Admin']);
            $table->timestamp('assigned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};