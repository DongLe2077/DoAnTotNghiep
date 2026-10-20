<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\InvoiceFilterRules;

class InvoiceIndexRequest extends OwnerRequest
{
    use InvoiceFilterRules;
}
