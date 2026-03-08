<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbArticleVote extends Model
{
    protected $table = 'kb_article_votes';

    protected $fillable = [
        'article_id',
        'user_id',
        'vote',
    ];

    protected $casts = [
        'vote' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
