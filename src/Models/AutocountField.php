<?php

namespace Jiannius\Autocount\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Jiannius\Autocount\Enums\AutocountFieldType;

class AutocountField extends Model
{
    use HasFactory;
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'type' => AutocountFieldType::class,
    ];

    /**
     * Get the parent model
     */
    public function parent() : MorphTo
    {
        return $this->morphTo();
    }
}
