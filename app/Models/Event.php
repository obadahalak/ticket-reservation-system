<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
class Event extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'price', 'available_tickets'];


}
