<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $table = 'teacher';
    
    protected $fillable = [
        'rfid',
        'lastname',
        'firstname',
        'middlename',
        'picurl'
    ];
    
    public $timestamps = false;
}