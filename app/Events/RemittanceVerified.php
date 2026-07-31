<?php

namespace App\Events;

use App\Models\Remittance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RemittanceVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(public Remittance $remittance)
    {
    }
}
