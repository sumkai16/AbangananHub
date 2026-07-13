echo App\Models\User::whereHas('roles', function() { ->where('role', 'Landlord'); })->get(['user_id','first_name','last_name'])->toJson();
