<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base for every API Resource in this app.
 *
 * Several endpoints load relations with a partial select() (e.g.
 * 'user_id,first_name,last_name,profile_picture'), and Eloquent's own
 * toArray() reflects that: an unselected column is simply absent from the
 * array, not present as null. A Resource with a fixed field list would
 * normally emit every field, turning "absent" into "null" and changing the
 * wire shape for every screen that already relies on the leaner payload.
 *
 * attr() preserves the original behavior: a column that wasn't loaded is
 * dropped from the response instead of serialized as null. A column that
 * really is null in the database still comes through as null, unchanged.
 */
abstract class ApiResource extends JsonResource
{
    private const MISSING = "\0missing\0";

    protected function attr(string $key): mixed
    {
        $attributes = $this->resource->getAttributes();

        return array_key_exists($key, $attributes)
            ? $this->resource->getAttribute($key)
            : self::MISSING;
    }

    public function resolve($request = null): array
    {
        return array_filter(
            parent::resolve($request),
            fn ($value) => $value !== self::MISSING
        );
    }
}
