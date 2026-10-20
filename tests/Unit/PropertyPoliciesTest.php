<?php

namespace Tests\Unit;

use App\Support\PropertyPolicies;
use PHPUnit\Framework\TestCase;

class PropertyPoliciesTest extends TestCase
{
    public function test_presets_come_back_in_canonical_order_and_unknown_ones_are_dropped(): void
    {
        $rules = PropertyPolicies::compileRules(['Curfew', 'No Smoking', 'Made Up'], null);

        $this->assertSame(['No Smoking', 'Curfew'], $rules);
    }

    public function test_custom_lines_are_trimmed_deduplicated_and_capped(): void
    {
        $rules = PropertyPolicies::compileRules(['No Pets'], "  No cooking after 10 PM \n\nno pets\nNo cooking after 10 pm\n" . str_repeat('x', 300));

        $this->assertSame('No Pets', $rules[0]);
        $this->assertSame('No cooking after 10 PM', $rules[1]);          // deduped case-insensitively
        $this->assertCount(3, $rules);                                     // "no pets" repeats a preset -> dropped
        $this->assertSame(PropertyPolicies::MAX_CUSTOM_RULE_LENGTH, mb_strlen($rules[2]));
    }

    public function test_at_most_ten_custom_rules_are_kept(): void
    {
        $lines = implode("\n", array_map(fn ($i) => "Rule $i", range(1, 25)));

        $this->assertCount(PropertyPolicies::MAX_CUSTOM_RULES, PropertyPolicies::compileRules([], $lines));
    }

    public function test_split_is_the_inverse_of_compile(): void
    {
        $compiled = PropertyPolicies::compileRules(['No Pets', 'Curfew'], "Keep it clean\nNo cooking after 10 PM");
        $split = PropertyPolicies::splitRules($compiled);

        $this->assertSame(['No Pets', 'Curfew'], $split['presets']);
        $this->assertSame("Keep it clean\nNo cooking after 10 PM", $split['custom']);
    }

    public function test_a_property_with_no_rules_splits_to_empty(): void
    {
        $this->assertSame(['presets' => [], 'custom' => ''], PropertyPolicies::splitRules(null));
    }

    public function test_form_defaults_for_a_new_property(): void
    {
        $values = PropertyPolicies::formValues(null);

        $this->assertNull($values['living_arrangement']);
        $this->assertSame('No Preference', $values['occupancy_preference']);
        $this->assertSame([], $values['house_rules']);
    }

    public function test_options_match_what_the_form_offers(): void
    {
        $this->assertSame(['Private', 'Shared', 'Mixed'], array_keys(PropertyPolicies::LIVING_ARRANGEMENTS));
        $this->assertSame(['No Preference', 'Men Only', 'Women Only'], array_keys(PropertyPolicies::OCCUPANCY_PREFERENCES));
        $this->assertContains('No Overnight Guests', PropertyPolicies::HOUSE_RULES);
    }
}
