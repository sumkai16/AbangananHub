$reservation = App\Models\Reservation::where('reservation_date', '2026-06-30')->where('duration_of_stay', '1 Year')->first();
echo 'reservation_id: ' . $reservation->reservation_id . PHP_EOL;
echo 'Before - reservation status: ' . $reservation->reservation_status . PHP_EOL;
echo 'Before - unit_id on reservation: ' . $reservation->unit_id . PHP_EOL;
echo 'Before - unit availability: ' . ($reservation->unit ? $reservation->unit->availability_status : 'UNIT IS NULL') . PHP_EOL;
