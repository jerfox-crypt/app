<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TapHistory extends Model
{
    protected $table = 'taphistory';
    
    protected $fillable = [
        'tdate',
        'ttime',
        'tapstate',
        'studid',
        'createddatetime',
        'createdby',
        'tapstatus',
        'deleted',
        'utype'
    ];
    
    // Use your custom timestamp column
    const CREATED_AT = 'createddatetime';
    const UPDATED_AT = null;
    
    protected $casts = [
        'tapstatus' => 'boolean',
        'deleted' => 'boolean',
        'utype' => 'integer'
    ];
}