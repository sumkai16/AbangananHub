{{--
    Wraps a multi-page printable document (lease, tenant agreement). On screen it is invisible.
    On paper it gives every page the same top and bottom margin while the @page itself has none, which is
    what makes Chrome and Edge leave out their own date / URL / page-number header and footer. A table's
    <thead> and <tfoot> repeat on each printed page, so they carry the spacers. The side margin is padding
    on the body. Sizes live in resources/css/app.css (.print-doc). One-page documents use .print-sheet instead.
--}}
<div class="print-doc">
    <table class="print-doc-table">
        <thead><tr><td><div class="print-doc-gap"></div></td></tr></thead>
        <tbody><tr><td class="print-doc-body">{{ $slot }}</td></tr></tbody>
        <tfoot><tr><td><div class="print-doc-gap"></div></td></tr></tfoot>
    </table>
</div>
