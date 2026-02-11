<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'studinfo';

    protected $fillable = [
        'rfid',
        'lrn',
        'sid',
        'lastname',
        'firstname',
        'middlename',
        'suffix',
        'levelname',
        'sectionname',
        'gender',
        'picurl',
        'mcontactno',
        'fcontactno',
        'gcontactno'
    ];

    public $timestamps = false; // Assuming no created_at/updated_at columns
}