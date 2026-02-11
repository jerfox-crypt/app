<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    protected $table = 'transaction_logs';
    
    protected $fillable = [
        'rfid',
        'person_id',
        'person_type',
        'status',
        'message',
        'metadata',
        'ip_address',
        'user_agent',
        'method',
        'endpoint'
    ];
    
    // If you have created_at and updated_at columns
    public $timestamps = true;
    
    // Cast metadata to array
    protected $casts = [
        'metadata' => 'array'
    ];
}