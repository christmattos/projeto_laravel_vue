<?php
namespace App\Models;

use App\Models\TaskImage;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'done'];

    public function images()
    {
        return $this->hasMany(TaskImage::class);
    }
}
