$reservations = App\Models\Reservation::where('unit_id', 1)->orderBy('reservation_id')->get();
foreach ($reservations as $r) {
    echo $r->reservation_id . ' | date:' . $r->reservation_date->format('Y-m-d') . ' | duration:' . $r->duration_of_stay . ' | status:' . $r->reservation_status . PHP_EOL;
}
echo '---' . PHP_EOL;
$unit = App\Models\PropertyUnit::find(1);
echo 'unit_id 1 current availability_status: ' . $unit->availability_status . PHP_EOL;
