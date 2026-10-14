<?php

namespace App\Models;

use App\Enums\KbArticleStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\KbArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property KbArticleStatus $status
 */
#[Fillable(['category_id', 'title', 'body', 'status'])]
class KbArticle extends Model
{
    /** @use HasFactory<KbArticleFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Stamp published_at the first time the article is published; clear it when
     * the article goes back to draft. Archived articles keep their date.
     */
    protected static function booted(): void
    {
        static::saving(function (KbArticle $article): void {
            if ($article->status === KbArticleStatus::Published) {
                $article->published_at ??= now();
            } elseif ($article->status === KbArticleStatus::Draft) {
                $article->published_at = null;
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KbArticleStatus::class,
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
