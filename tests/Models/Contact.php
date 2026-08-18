<?php

namespace Visualbuilder\ExportScheduler\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['full_name'];

    protected $table = 'contacts';
}
