$r = App\Models\Reservation::find(2);
$r->update(['reservation_status' => 'Cancelled']);
$unit = App\Models\PropertyUnit::find(1);
$unit->update(['availability_status' => 'Available']);
echo 'Reset complete.' . PHP_EOL;
