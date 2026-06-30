$rows = App\Models\Reservation::whereIn('reservation_id', [3, 4])->get(['reservation_id','unit_id','tenant_id','reservation_date','duration_of_stay','occupants_count','reservation_status']);
foreach ($rows as $r) {
    echo $r->reservation_id . ' | unit:' . $r->unit_id . ' | date:' . $r->reservation_date . ' | duration:' . $r->duration_of_stay . ' | occupants:' . $r->occupants_count . ' | status:' . $r->reservation_status . PHP_EOL;
}
