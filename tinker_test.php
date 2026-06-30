$unit = App\Models\PropertyUnit::with('property')->first();
echo 'unit_id: ' . $unit->unit_id . PHP_EOL;
echo 'property_id: ' . $unit->property_id . PHP_EOL;
echo 'unit label: ' . $unit->unit_label . PHP_EOL;
$tenants = App\Models\User::whereHas('roles', fn($q) => $q->where('role', 'Tenant'))->get(['user_id','first_name','last_name']);
echo 'Tenants:' . PHP_EOL;
foreach ($tenants as $t) { echo $t->user_id . ': ' . $t->first_name . ' ' . $t->last_name . PHP_EOL; }
