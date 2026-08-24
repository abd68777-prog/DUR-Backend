<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;

class SettingsService
{
    public function current(): Setting
    {
        return Setting::current();
    }

    /**
     * تحديث جزئي: الحقول يلي ما انبعتت بتضل متل ما هي.
     *
     * هيك لوحة التحكم بتقدر تحفظ قسم الـ hero لحاله بدون ما تعيد إرسال
     * معلومات التواصل معه. إرسال null صراحةً بيفضّي الحقل.
     *
     * @param  array<string, mixed>  $data  الحقول المتحقّق منها فقط
     * @param  array<string, UploadedFile>  $media  ['video' => ..., 'poster' => ...]
     */
    public function update(array $data, array $media = []): Setting
    {
        $setting = $this->current();

        if ($media !== []) {
            $data['home_hero'] = array_merge(
                $data['home_hero'] ?? [],
                $this->uploadHeroMedia($media)
            );
        }

        foreach (Setting::SECTIONS as $section) {
            if (! array_key_exists($section, $data)) {
                continue;
            }

            // array_replace_recursive: المفاتيح المبعوتة بس هي يلي بتتبدّل،
            // والباقي بيضل من القيمة المخزّنة.
            $setting->{$section} = array_replace_recursive(
                (array) $setting->{$section},
                $data[$section]
            );
        }

        $setting->save();

        return $setting->fresh();
    }

    /**
     * بيرفع الفيديو/الصورة وبيرجّع روابط Cloudinary كاملة جاهزة للعرض.
     *
     * @param  array<string, UploadedFile>  $media
     * @return array<string, string>
     */
    private function uploadHeroMedia(array $media): array
    {
        $urls = [];

        if (isset($media['video'])) {
            // الـ adapter بيكشف نوع المورد من امتداد الملف وبيرفعه كـ video،
            // فلازم نبني الرابط بـ video() مش image() وإلا بيطلع مسار غلط.
            $path = $media['video']->store('settings', 'cloudinary');
            $urls['video_url'] = (string) cloudinary()->video($path)->toUrl();
        }

        if (isset($media['poster'])) {
            $path = $media['poster']->store('settings', 'cloudinary');
            $urls['poster_url'] = (string) cloudinary()->image($path)->toUrl();
        }

        return $urls;
    }
}
