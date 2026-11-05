<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_activities', function (Blueprint $table) {
            $table->id('activity_id');

            // The listing this happened on. The history goes with the property when it is deleted.
            $table->unsignedBigInteger('property_id');

            // Deliberately no foreign key: removing a unit must not erase the line that says it was removed.
            // The unit's label is written into `summary`.
            $table->unsignedBigInteger('unit_id')->nullable();

            // Who did it. Set null (not cascade) so a deleted account does not erase the record; the name is
            // snapshotted below.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->enum('actor_role', ['landlord', 'tenant', 'admin', 'system']);
            $table->string('actor_name');

            $table->string('action', 60);                  // 'unit.updated', 'payment.recorded', ...
            $table->string('summary');                     // the sentence the page shows, as is
            $table->json('metadata')->nullable();          // changed field names, amounts, ids for links

            // No updated_at: rows are written once. The only edit is the roll-up of several photos or the
            // months of one payment into the line already written seconds earlier.
            $table->timestamp('created_at')->nullable();

            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->foreign('actor_id')->references('user_id')->on('users')->onDelete('set null');

            $table->index(['property_id', 'created_at']);
            $table->index('actor_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_activities');
    }
};
