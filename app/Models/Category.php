<?php

namespace App\Models;

use App\Concerns\ClearsCaches;
use App\Support\CacheKeys;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use ClearsCaches, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
    ];

    /**
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class)->orderBy('position');
    }

    public function clearCaches(): void
    {
        //
    }

    /**
     * @return list<string>
     */
    public function cacheTags(): array
    {
        return [
            CacheKeys::TAG_SIDEBAR,
        ];
    }
}
