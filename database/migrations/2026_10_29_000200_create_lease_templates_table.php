<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lease template library (plans/lease-templates-page.md). A landlord writes named leases once and assigns them to
 * properties or units. Replaces properties.lease_template (added the same day): each property's wording becomes a
 * template named after the property and assigned to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_templates', function (Blueprint $table) {
            $table->id('lease_template_id');
            $table->foreignId('landlord_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('name', 100);
            // Sanitized HTML with merge fields (App\Support\LeaseBody). Null = the standard clauses.
            $table->longText('body')->nullable();
            // Null on any rule = use the property's value, then the platform default.
            $table->unsignedTinyInteger('rent_grace_days')->nullable();
            $table->string('late_fee_type', 10)->nullable(); // none | fixed | percent
            $table->decimal('late_fee_value', 8, 2)->nullable();
            $table->unsignedSmallInteger('notice_days')->nullable();
            $table->json('extra_house_rules')->nullable();
            $table->timestamps();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('lease_template_id')->nullable()->constrained('lease_templates', 'lease_template_id')->nullOnDelete();
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->foreignId('lease_template_id')->nullable()->constrained('lease_templates', 'lease_template_id')->nullOnDelete();
        });

        DB::table('properties')->whereNotNull('lease_template')->orderBy('property_id')->each(function ($property) {
            $id = DB::table('lease_templates')->insertGetId([
                'landlord_id' => $property->landlord_id,
                'name' => Str::limit($property->title, 88, '').' lease',
                'body' => $property->lease_template,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('properties')->where('property_id', $property->property_id)->update(['lease_template_id' => $id]);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('lease_template');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->longText('lease_template')->nullable();
        });

        DB::table('properties')->whereNotNull('lease_template_id')->orderBy('property_id')->each(function ($property) {
            DB::table('properties')->where('property_id', $property->property_id)->update([
                'lease_template' => DB::table('lease_templates')->where('lease_template_id', $property->lease_template_id)->value('body'),
            ]);
        });

        Schema::table('property_units', fn (Blueprint $table) => $table->dropConstrainedForeignId('lease_template_id'));
        Schema::table('properties', fn (Blueprint $table) => $table->dropConstrainedForeignId('lease_template_id'));
        Schema::dropIfExists('lease_templates');
    }
};
