<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A policy's own house rules become titled sections (Curfew, Visitors, ...), each a list of rules, instead of one flat
 * list. The property's listed rules still come first and are never replaced. Existing flat extras become one section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_templates', function (Blueprint $table) {
            $table->json('house_rule_sections')->nullable()->after('notice_days');
        });

        DB::table('lease_templates')->whereNotNull('extra_house_rules')->orderBy('lease_template_id')->each(function ($row) {
            $rules = array_values(array_filter((array) json_decode($row->extra_house_rules, true)));
            if ($rules) {
                DB::table('lease_templates')->where('lease_template_id', $row->lease_template_id)
                    ->update(['house_rule_sections' => json_encode([['title' => 'Additional rules', 'rules' => $rules]])]);
            }
        });

        Schema::table('lease_templates', fn (Blueprint $table) => $table->dropColumn('extra_house_rules'));
    }

    public function down(): void
    {
        Schema::table('lease_templates', fn (Blueprint $table) => $table->json('extra_house_rules')->nullable()->after('notice_days'));

        DB::table('lease_templates')->whereNotNull('house_rule_sections')->orderBy('lease_template_id')->each(function ($row) {
            $flat = collect((array) json_decode($row->house_rule_sections, true))->flatMap(fn ($s) => $s['rules'] ?? [])->values()->all();
            DB::table('lease_templates')->where('lease_template_id', $row->lease_template_id)
                ->update(['extra_house_rules' => $flat ? json_encode($flat) : null]);
        });

        Schema::table('lease_templates', fn (Blueprint $table) => $table->dropColumn('house_rule_sections'));
    }
};
