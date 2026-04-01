<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $conversation_id
 * @property int|null $user_id
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property string $feedback_type
 * @property \Carbon\CarbonImmutable|null $created_at
 */
class FeedbackAiCost extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    protected $fillable = [
        'conversation_id',
        'user_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'feedback_type',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForConversation(Builder $query, string $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
