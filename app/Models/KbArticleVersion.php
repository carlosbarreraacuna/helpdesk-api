<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbArticleVersion extends Model
{
    protected $table = 'kb_article_versions';

    protected $fillable = [
        'article_id',
        'version_number',
        'title',
        'content',
        'change_summary',
        'editor_id',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'version_number' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
