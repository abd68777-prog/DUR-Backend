<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * إعدادات المتجر. صف واحد ثابت - ما في ولا حالة بيصير فيها أكتر من سجل.
 */
class Setting extends Model
{
    /** مفتاح الصف الوحيد. */
    public const ID = 1;

    /** الأقسام يلي بيتألف منها الرد. */
    public const SECTIONS = ['brand', 'contact', 'social', 'home_hero'];

    protected $fillable = self::SECTIONS;

    protected function casts(): array
    {
        return [
            'brand' => 'array',
            'contact' => 'array',
            'social' => 'array',
            'home_hero' => 'array',
        ];
    }

    /**
     * الصف الوحيد. الـ migration بيزرعه، بس منعيد إنشاءه لو انحذف يدوياً
     * حتى ما يوقع GET /api/settings.
     */
    public static function current(): self
    {
        if ($setting = static::query()->find(self::ID)) {
            return $setting;
        }

        $setting = new static(config('settings.defaults'));
        $setting->id = self::ID;
        $setting->save();

        return $setting;
    }
}
