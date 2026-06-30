$r1 = App\Models\Reservation::create([
    'property_id' => 1,
    'unit_id' => 1,
    'tenant_id' => 5,
    'reservation_date' => now()->addDays(10),
    'duration_of_stay' => '6 Months',
    'occupants_count' => 1,
    'reservation_status' => 'Pending',
]);
$r2 = App\Models\Reservation::create([
    'property_id' => 1,
    'unit_id' => 1,
    'tenant_id' => 5,
    'reservation_date' => now()->addDays(15),
    'duration_of_stay' => '1 Year',
    'occupants_count' => 1,
    'reservation_status' => 'Pending',
]);
echo 'Created reservation_id ' . $r1->reservation_id . ' and ' . $r2->reservation_id . PHP_EOL;
