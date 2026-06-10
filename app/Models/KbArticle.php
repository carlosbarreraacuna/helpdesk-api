<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KbArticle extends Model
{
    use HasFactory;
    protected $table = 'kb_articles';

    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'subcategory_id',
        'author_id',
        'status',
        'current_version',
        'published_at',
        'views_count',
        'useful_count',
        'not_useful_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'current_version' => 'integer',
        'views_count' => 'integer',
        'useful_count' => 'integer',
        'not_useful_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title) . '-' . Str::random(5);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(KbSubcategory::class, 'subcategory_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function versions()
    {
        return $this->hasMany(KbArticleVersion::class, 'article_id')->orderBy('version_number', 'desc');
    }

    public function publishedVersion()
    {
        return $this->hasOne(KbArticleVersion::class, 'article_id')->where('is_published', true);
    }

    public function tags()
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tags', 'article_id', 'tag_id');
    }

    public function votes()
    {
        return $this->hasMany(KbArticleVote::class, 'article_id');
    }

    public function linkedTickets()
    {
        return $this->belongsToMany(Ticket::class, 'ticket_kb_articles', 'article_id', 'ticket_id')
            ->withPivot('linked_by', 'linked_at');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
