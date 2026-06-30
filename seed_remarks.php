$r = App\Models\Reservation::create([
    'property_id' => 1,
    'unit_id' => 1,
    'tenant_id' => 5,
    'reservation_date' => now()->addDays(30),
    'duration_of_stay' => '1 Year',
    'occupants_count' => 2,
    'reservation_status' => 'Pending',
    'remarks' => 'Has a small dog, hoping that is okay with the landlord.',
]);
echo 'Created reservation_id ' . $r->reservation_id . PHP_EOL;
