<?php

namespace App\Models;

use App\Support\MedicineCategory;
use App\Support\MedicineIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_name',
        'brand_name',
        'dosage',
        'manufacturer',
        'requiresPrescription',
        'cold_chain_required',
        'category',
        'categories',
        'identity_key',
    ];

    protected $casts = [
        'requiresPrescription' => 'boolean',
        'cold_chain_required' => 'boolean',
        'categories' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Medicine $medicine): void {
            $identityKey = MedicineIdentity::key(
                $medicine->medicine_name,
                $medicine->brand_name,
                $medicine->dosage
            );

            $legacyDuplicate = $medicine->exists
                && $medicine->getOriginal('identity_key') === null
                && self::query()
                    ->where('identity_key', $identityKey)
                    ->whereKeyNot($medicine->getKey())
                    ->exists();

            $medicine->identity_key = $legacyDuplicate ? null : $identityKey;
        });
    }

    /**
     * Return all assigned categories, falling back to the legacy single category.
     *
     * @return array<int, string>
     */
    public function getCategoryNamesAttribute(): array
    {
        $categories = $this->categories;
        if (! is_array($categories) || $categories === []) {
            $categories = [$this->attributes['category'] ?? null];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($category): string => trim((string) $category), $categories),
            static fn (string $category): bool => $category !== ''
        )));
    }

    public function getCategoryDisplayAttribute(): string
    {
        return implode(', ', $this->category_names);
    }

    public function scopeWhereCategory(Builder $query, string $category): Builder
    {
        $category = MedicineCategory::canonicalOptions()[
            MedicineCategory::optionValue($category)
        ] ?? $category;
        $jsonValue = json_encode($category);
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();
        $jsonColumn = $driver === 'pgsql' ? 'categories::text' : 'categories';
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->where(function (Builder $categoryQuery) use ($category, $jsonValue, $jsonColumn, $likeOperator): void {
            $categoryQuery->whereRaw('LOWER(TRIM(category)) = LOWER(TRIM(?))', [$category])
                ->orWhereRaw($jsonColumn.' '.$likeOperator.' ?', ['%'.$jsonValue.'%']);
        });
    }

    /**
     * Get the inventory items for the medicine.
     */
    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryBatches()
    {
        return $this->hasManyThrough(InventoryBatch::class, InventoryItem::class);
    }

    /**
     * Check if medicine requires prescription.
     */
    public function getRequiresPrescriptionAttribute($value)
    {
        return (bool) $value;
    }
}
