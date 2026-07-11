<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_bases';
    
    protected $fillable = [
        'title',
        'category',
        'content',
        'file_path', // 💡 حتماً این خط اینجا باشد
        'is_active',
        'vector_id',
    ];
}