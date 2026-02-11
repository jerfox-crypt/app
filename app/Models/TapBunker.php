<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TapBunker extends Model
{
    protected $table = 'tapbunker';
    
    // Since you have auto-increment id, no need to set primary key
    // Laravel will automatically use 'id' as primary key
    
    // Fillable columns
    protected $fillable = [
        'message',
        'receiver', 
        'smsstatus',
        'xml',
        'createddatetime',
        'rfid',
        'tapstate'
    ];
    
    // Disable Laravel's default timestamps (created_at, updated_at)
    // because you have your own createddatetime column
    public $timestamps = false;
    
    // Cast the createddatetime to Carbon instance
    protected $casts = [
        'createddatetime' => 'datetime',
        'smsstatus' => 'integer'
    ];
    
    // If you want to use createddatetime as a timestamp
    const CREATED_AT = 'createddatetime';
    const UPDATED_AT = null;
}