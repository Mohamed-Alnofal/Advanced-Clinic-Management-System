<?php

namespace Modules\Drug\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'category',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * الأدوية التابعة لهذا التصنيف
     * علاقة Many-to-Many مع جدول category_drug
     */
    public function drugs()
    {
        return $this->belongsToMany(
            Drug::class,
            'category_drug',
            'category_id',
            'drug_id'
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * البحث عن تصنيف باسم مشابه
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where('category', 'LIKE', "%{$keyword}%");
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * عدد الأدوية في هذا التصنيف
     */
    public function getDrugsCountAttribute()
    {
        return $this->drugs()->count();
    }
}