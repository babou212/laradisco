<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * Create a new category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $maxPosition = Category::max('position') ?? -1;
        $category = Category::create([
            'name' => $request->validated('name'),
            'position' => $maxPosition + 1,
        ]);

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a category.
     */
    public function update(StoreCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();

        return (new CategoryResource($category->fresh()))
            ->response();
    }

    /**
     * Delete a category and its channels.
     */
    public function destroy(Request $request, Category $category): JsonResponse|Response
    {
        $this->authorize('delete', $category);

        $category->channels()->delete();
        $category->delete();

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();

        return $this->noContentResponse();
    }
}
