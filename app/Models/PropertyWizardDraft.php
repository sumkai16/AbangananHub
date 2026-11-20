<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A landlord's saved answers from wizard step 1 (Info), kept until Location creates the Draft property. */
class PropertyWizardDraft extends Model
{
    protected $fillable = ['landlord_id', 'payload'];

    protected $casts = ['payload' => 'array'];
}
