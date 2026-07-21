<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCategoryRequest;
use App\Http\Requests\Settings\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CurrentOrganization;
use App\Support\Cast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('viewLibrary', $organization);

        $categories = Category::orderedTree($organization, withProjectsCount: true)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'parentId' => $category->parent_id,
                'depth' => Cast::int($category->getAttribute('depth')),
                'projectsCount' => Cast::int($category->getAttribute('projects_count')),
            ])
            ->values()
            ->all();

        return response()->json($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        $category = $organization->categories()->create($request->validated());

        return response()->json($this->payload($category), 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return response()->json($this->payload($category->refresh()));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'parentId' => $category->parent_id,
        ];
    }
}
