<?php

namespace Visualbuilder\ExportScheduler\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'owner_type', 'owner_id'];

    protected $table = 'documents';

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
