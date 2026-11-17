<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'active'];
    protected $casts = ['active' => 'boolean'];
}
