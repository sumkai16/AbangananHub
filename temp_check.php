foreach (DB::select('SHOW COLUMNS FROM conversations') as $col) { if ($col->Field === 'status') print_r($col); }
