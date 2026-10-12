<?php

namespace App\Services\AiSearch;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Turns the plain-text summary into safe HTML: every listing title that appears in it becomes a link
 * to that listing (coral with a dashed underline, not bold), and prices and distances are bold. Works on the template summary and on an AI one
 * alike, because it only looks at the finished text and the listings we showed.
 *
 * The text is escaped first, so nothing a model (or a listing title) says can inject markup.
 */
class SummaryFormatter
{
    /**
     * @param  Collection<int, Property>  $properties  the listings the summary may name
     * @param  bool  $onDark  link colours for the navy summary card; false for the light "nothing matches" card
     */
    public static function html(string $text, Collection $properties, bool $onDark = true): HtmlString
    {
        $html = e($text);

        // Longest titles first, so "Studio in IT Park" is not eaten by a shorter title inside it.
        $byTitle = $properties->unique('property_id')->keyBy(fn (Property $p) => e($p->title))
            ->sortByDesc(fn ($p, $title) => mb_strlen($title));

        if ($byTitle->isNotEmpty()) {
            $pattern = '/' . $byTitle->keys()->map(fn ($t) => preg_quote($t, '/'))->implode('|') . '/u';
            $class = $onDark
                ? 'text-[#FF8A66] underline decoration-dashed decoration-[#FF8A66]/60 decoration-1 underline-offset-4 hover:text-white hover:decoration-white'
                : 'text-[#B35A3D] underline decoration-dashed decoration-[#B35A3D]/50 decoration-1 underline-offset-4 hover:text-[#060D26] hover:decoration-[#060D26]';

            $html = preg_replace_callback($pattern, fn ($m) => sprintf(
                '<a href="%s" class="%s">%s</a>',
                e(route('properties.show', $byTitle[$m[0]]->property_id)), $class, $m[0],
            ), $html) ?? $html;
        }

        // Bold the facts people scan for: ₱ amounts (with /month) and distances. Skips anything inside a tag.
        $html = preg_replace('/(?<![\w"=\/>-])(₱\d[\d,]*(?:\/month)?|\d+(?:\.\d+)? km)(?![^<]*>)/u', '<strong class="font-bold">$1</strong>', $html) ?? $html;

        return new HtmlString($html);
    }
}
