<?php

namespace Visualbuilder\ExportScheduler\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = ['full_name'];

    protected $table = 'contacts';
}
