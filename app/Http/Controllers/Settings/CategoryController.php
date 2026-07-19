<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCategoryRequest;
use App\Http\Requests\Settings\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Organization;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function index(Request $request): Response
    {
        $organization = $this->current->for($request->user());
        $this->authorize('viewLibrary', $organization);

        return Inertia::render('settings/Categories', [
            'categories' => $this->tree($organization),
            'canManage' => $request->user()->can('update', $organization),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $organization = $this->current->for($request->user());
        $this->authorize('update', $organization);

        $organization?->categories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('categories.index');
    }

    /**
     * The organization's categories flattened depth-first, each carrying its
     * nesting depth (for indentation) and how many projects sit directly in it.
     *
     * @return array<int, array{id: int, name: string, parentId: int|null, depth: int, projectsCount: int}>
     */
    private function tree(?Organization $organization): array
    {
        return Category::orderedTree($organization, withProjectsCount: true)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'parentId' => $category->parent_id,
                'depth' => (int) $category->getAttribute('depth'),
                'projectsCount' => (int) $category->getAttribute('projects_count'),
            ])
            ->values()
            ->all();
    }
}
