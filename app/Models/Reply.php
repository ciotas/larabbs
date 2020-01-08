<?php

namespace App\Models;

class Reply extends Model
{
    protected $fillable = ['content'];

    public function topic()
    {
        $this->belongsTo(Topic::class);
    }

    public function user()
    {
        $this->belongsTo(User::class);
    }
}
