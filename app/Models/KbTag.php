<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KbTag extends Model
{
    protected $table = 'kb_tags';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function articles()
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tags', 'tag_id', 'article_id');
    }
}
