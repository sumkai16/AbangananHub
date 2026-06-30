$r = App\Models\Reservation::create([
    'property_id' => 1,
    'unit_id' => 1,
    'tenant_id' => 5,
    'reservation_date' => now()->addDays(20),
    'duration_of_stay' => '3 Months',
    'occupants_count' => 1,
    'reservation_status' => 'Pending',
]);
echo 'Created reservation_id ' . $r->reservation_id . PHP_EOL;
