<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;

class CategoryService
{
    public function create(array $data, ?UploadedFile $image = null): Category
    {
        if ($image) {
            $data['image'] = $this->uploadImage($image);
        }

        return Category::create($data);
    }

    public function update(Category $category, array $data, ?UploadedFile $image = null): Category
    {
        if ($image) {
            $data['image'] = $this->uploadImage($image);
        }

        $category->update($data);

        return $category->fresh();
    }

    private function uploadImage(UploadedFile $image): string
    {
        $path = $image->store('categories', 'cloudinary');

        return (string) cloudinary()->image($path)->toUrl();
    }
}
