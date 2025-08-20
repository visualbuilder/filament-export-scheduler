<?php

namespace Visualbuilder\ExportScheduler\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Organisation extends Model
{

    protected $fillable = ['name', 'primary_contact_id'];

    protected $table = 'organisations';

    public function primary_contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }
}
