<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use App\Services\AiSearch\Vocabulary;
use PHPUnit\Framework\TestCase;

class PropertyTypeTest extends TestCase
{
    public function test_the_list_matches_the_database_enum_order(): void
    {
        $this->assertSame(['Apartment', 'Dormitory', 'House', 'Boarding House', 'Bedspace'], PropertyType::values());
    }

    public function test_condominium_is_not_offered(): void
    {
        $this->assertNotContains('Condominium', PropertyType::values());
    }

    public function test_options_can_lead_with_an_any_entry(): void
    {
        $options = PropertyType::options('Any type');

        $this->assertSame('Any type', $options['']);
        $this->assertSame('Dormitory', $options['Dormitory']);
        $this->assertCount(6, $options);
    }

    public function test_validation_rule_and_ai_vocabulary_read_the_same_list(): void
    {
        $this->assertSame('Apartment,Dormitory,House,Boarding House,Bedspace', PropertyType::rule());
        $this->assertSame(PropertyType::values(), Vocabulary::propertyTypes());
    }
}
