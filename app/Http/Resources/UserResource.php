<?php

namespace App\Http\Resources;

class UserResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'user_id'            => $this->attr('user_id'),
            'first_name'             => $this->attr('first_name'),
            'last_name'              => $this->attr('last_name'),
            'email'                  => $this->attr('email'),
            'email_verified_at'      => $this->attr('email_verified_at'),
            'contact_number'         => $this->attr('contact_number'),
            'gcash_number'           => $this->attr('gcash_number'),
            'gcash_account_name'     => $this->attr('gcash_account_name'),
            'profile_picture'        => $this->attr('profile_picture'),
            'account_status'         => $this->attr('account_status'),
            'bio'                    => $this->attr('bio'),
            'profile_visibility'     => $this->attr('profile_visibility'),
            'is_walk_in'             => $this->attr('is_walk_in'),
            'created_by_landlord_id' => $this->attr('created_by_landlord_id'),
            'provider'               => $this->attr('provider'),
            'provider_id'            => $this->attr('provider_id'),
            'created_at'             => $this->attr('created_at'),
            'updated_at'             => $this->attr('updated_at'),

            'rentalBusiness'         => $this->whenLoaded('rentalBusiness'),
        ];
    }
}
