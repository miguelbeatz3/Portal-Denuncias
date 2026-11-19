<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportInternalNote extends Model
{
    public const UPDATED_AT = null;
    public const MAX_LENGTH = 4000;

    protected $fillable = ['author_id', 'author_name', 'body'];
}
