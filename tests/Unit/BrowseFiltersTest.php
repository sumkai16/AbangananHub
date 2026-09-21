<?php

namespace Tests\Unit;

use App\Support\BrowseFilters;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class BrowseFiltersTest extends TestCase
{
    private function request(array $query): Request
    {
        return Request::create('/properties', 'GET', $query);
    }

    public function test_known_values_pass_through(): void
    {
        $f = BrowseFilters::fromRequest($this->request([
            'living' => 'Shared', 'for' => 'Women', 'furnishing' => 'Furnished', 'rules' => ['pets', 'curfew'],
        ]));

        $this->assertSame(['living' => 'Shared', 'for' => 'Women', 'furnishing' => 'Furnished', 'rules' => ['pets', 'curfew']], $f);
    }

    public function test_unknown_values_are_dropped(): void
    {
        $f = BrowseFilters::fromRequest($this->request([
            'living' => 'Evil', 'for' => 'Aliens', 'furnishing' => "x' OR 1=1", 'rules' => ['zzz', 'pets'],
        ]));

        $this->assertNull($f['living']);
        $this->assertNull($f['for']);
        $this->assertNull($f['furnishing']);
        $this->assertSame(['pets'], $f['rules']);
    }

    public function test_array_where_a_string_is_expected_is_ignored(): void
    {
        $f = BrowseFilters::fromRequest($this->request(['living' => ['Shared'], 'for' => ['Men'], 'rules' => 'pets']));

        $this->assertNull($f['living']);
        $this->assertNull($f['for']);
        $this->assertSame(['pets'], $f['rules']);
    }

    public function test_active_count_includes_amenities_and_every_new_filter(): void
    {
        $count = BrowseFilters::activeCount($this->request([
            'amenities' => [1, 2], 'living' => 'Private', 'for' => 'Men', 'furnishing' => 'Unfurnished', 'rules' => ['pets', 'smoking'],
        ]));

        $this->assertSame(7, $count);
        $this->assertSame(0, BrowseFilters::activeCount($this->request([])));
    }
}
