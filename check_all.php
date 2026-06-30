$reservations = App\Models\Reservation::where('unit_id', 1)->orderBy('reservation_id')->get();
foreach ($reservations as $r) {
    echo $r->reservation_id . ' | date:' . $r->reservation_date->format('Y-m-d') . ' | status:' . $r->reservation_status . PHP_EOL;
}
