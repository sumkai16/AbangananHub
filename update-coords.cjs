const fs = require('fs');

const coords = {
  'Labangon, Cebu City, Cebu': { lat: 10.3013, lng: 123.8837 },
  'P. del Rosario St., Cebu City, Cebu': { lat: 10.3010, lng: 123.8966 },
  'Cebu IT Park, Apas, Cebu City, Cebu': { lat: 10.3297, lng: 123.9056 },
  'Bakilid, Mandaue City, Cebu': { lat: 10.3340, lng: 123.9300 },
  'Banilad, Cebu City, Cebu': { lat: 10.3400, lng: 123.9100 },
  'Punta Princesa, Cebu City, Cebu': { lat: 10.2970, lng: 123.8770 },
  'San Isidro, Talisay City, Cebu': { lat: 10.2560, lng: 123.8430 },
  'SRP, Mambaling, Cebu City, Cebu': { lat: 10.2830, lng: 123.8750 },
  'Lahug, Cebu City, Cebu': { lat: 10.3280, lng: 123.8980 },
  'Osmena Blvd, Cebu City, Cebu': { lat: 10.3070, lng: 123.8930 },
  'Biasong, Talisay City, Cebu': { lat: 10.2490, lng: 123.8340 },
  'Cansojong, Talisay City, Cebu': { lat: 10.2520, lng: 123.8430 },
  'Dumlog, Talisay City, Cebu': { lat: 10.2420, lng: 123.8340 },
  'Jaclupan, Talisay City, Cebu': { lat: 10.2640, lng: 123.8180 },
  'Lagtang, Talisay City, Cebu': { lat: 10.2600, lng: 123.8310 },
  'Linao, Talisay City, Cebu': { lat: 10.2450, lng: 123.8240 },
  'Maghaway, Talisay City, Cebu': { lat: 10.2680, lng: 123.8200 },
  'Mohon, Talisay City, Cebu': { lat: 10.2520, lng: 123.8320 },
  'Pooc, Talisay City, Cebu': { lat: 10.2440, lng: 123.8330 },
  'Tabunok, Talisay City, Cebu': { lat: 10.2540, lng: 123.8480 },
  'Cadulawan, Minglanilla, Cebu': { lat: 10.2540, lng: 123.7910 },
  'Calajoan, Minglanilla, Cebu': { lat: 10.2450, lng: 123.7850 },
  'Camp 7, Minglanilla, Cebu': { lat: 10.2850, lng: 123.7650 },
  'Cuanos, Minglanilla, Cebu': { lat: 10.2450, lng: 123.7980 },
  'Guindaruhan, Minglanilla, Cebu': { lat: 10.2630, lng: 123.7690 },
  'Pakigne, Minglanilla, Cebu': { lat: 10.2550, lng: 123.8050 },
  'Poblacion, Minglanilla, Cebu': { lat: 10.2450, lng: 123.7950 },
  'Tubod, Minglanilla, Cebu': { lat: 10.2420, lng: 123.7890 },
  'Tulay, Minglanilla, Cebu': { lat: 10.2380, lng: 123.7820 },
  'Tunghaan, Minglanilla, Cebu': { lat: 10.2530, lng: 123.7920 },
  'Alpaco, Naga City, Cebu': { lat: 10.2220, lng: 123.7380 },
  'Bairan, Naga City, Cebu': { lat: 10.2310, lng: 123.7420 },
  'Cabungahan, Naga City, Cebu': { lat: 10.2300, lng: 123.7500 },
  'Colon, Naga City, Cebu': { lat: 10.2180, lng: 123.7580 },
  'Inayagan, Naga City, Cebu': { lat: 10.2270, lng: 123.7660 },
  'Mainit, Naga City, Cebu': { lat: 10.2220, lng: 123.7460 },
  'Pangdan, Naga City, Cebu': { lat: 10.2310, lng: 123.7580 },
  'Poblacion, Naga City, Cebu': { lat: 10.2080, lng: 123.7570 },
  'Tagjaguimit, Naga City, Cebu': { lat: 10.2350, lng: 123.7350 },
  'Uling, Naga City, Cebu': { lat: 10.2450, lng: 123.7250 }
};

let file = 'database/seeders/PropertySeeder.php';
let data = fs.readFileSync(file, 'utf8');

for (const [address, loc] of Object.entries(coords)) {
  let searchStr = `'address'             => '${address}',`;
  let escapedSearchStr = searchStr.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  
  // We want to match:
  // 'address' => '...',
  // 'latitude' => 10.xxxx,
  // 'longitude' => 123.xxxx,
  
  let regex = new RegExp(`('address'\\s*=>\\s*'${address}',\\s*'latitude'\\s*=>\\s*)[\\d\\.]+(,\\s*'longitude'\\s*=>\\s*)[\\d\\.]+`);
  
  data = data.replace(regex, `$1${loc.lat.toFixed(4)}$2${loc.lng.toFixed(4)}`);
}

fs.writeFileSync(file, data);
console.log('Done replacing coordinates');
