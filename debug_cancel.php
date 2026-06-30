$r = App\Models\Reservation::where('reservation_date', '2026-07-10')->first();
echo 'reservation_id: ' . $r->reservation_id . PHP_EOL;
echo 'unit_id: ' . $r->unit_id . PHP_EOL;
echo 'status: ' . $r->reservation_status . PHP_EOL;
