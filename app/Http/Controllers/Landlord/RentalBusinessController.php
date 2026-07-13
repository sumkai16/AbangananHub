<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateRentalBusinessRequest;
use App\Models\RentalBusiness;
use Cloudinary\Cloudinary as CloudinaryClient;
class RentalBusinessController extends Controller
{
    public function edit()
    {
        $business = RentalBusiness::where('landlord_id', auth()->id())->firstOrFail();

        return view('landlord.business.edit', compact('business'));
    }

    public function update(UpdateRentalBusinessRequest $request)
    {
        $business = RentalBusiness::where('landlord_id', auth()->id())->firstOrFail();

        $data = $request->safe()->except('logo');
        if ($request->hasFile('logo')) {
            $cloudinary = new CloudinaryClient(config('cloudinary.cloud_url'));

            if ($business->logo_public_id) {
                $cloudinary->uploadApi()->destroy($business->logo_public_id);
            }

            $result = $cloudinary->uploadApi()->upload(
                $request->file('logo')->getRealPath(),
                [
                    'folder' => 'abanganganhub/business-logos',
                    'transformation' => [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                    ],
                ]
            );

            $data['logo_url'] = $result['secure_url'];
            $data['logo_public_id'] = $result['public_id'];
        }

        $business->update($data);

        return redirect()->route('landlord.business.edit')->with('success', 'Business information updated.');
    }
}