<?php

namespace App\Http\Controllers;

use App\Enums\KbArticleStatus;
use App\Jobs\EmbedModel;
use App\Models\Category;
use App\Models\KbArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KbArticleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KbArticle::class);

        $search = $request->string('search')->trim()->value();

        return Inertia::render('kb/index', [
            'articles' => KbArticle::with('category:id,name', 'author:id,name')
                ->when($search !== '', fn ($query) => $query->where('title', 'ilike', '%'.$search.'%'))
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', KbArticle::class);

        return Inertia::render('kb/form', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', KbArticle::class);

        $article = new KbArticle($request->validate($this->rules()));
        $article->slug = $this->uniqueSlug($article->title);
        $article->author_id = $request->user()->id;
        $article->save();

        EmbedModel::dispatch($article);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Article created.')]);

        return to_route('kb.edit', $article);
    }

    public function edit(KbArticle $article): Response
    {
        Gate::authorize('update', $article);

        return Inertia::render('kb/form', [
            'article' => $article,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, KbArticle $article): RedirectResponse
    {
        Gate::authorize('update', $article);

        $article->update($request->validate($this->rules()));

        if ($article->wasChanged(['title', 'body'])) {
            EmbedModel::dispatch($article);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Article saved.')]);

        return back();
    }

    public function destroy(KbArticle $article): RedirectResponse
    {
        Gate::authorize('delete', $article);

        $article->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Article deleted.')]);

        return to_route('kb.index');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'category_id' => ['nullable', Rule::in(Category::pluck('id'))],
            'status' => ['required', Rule::enum(KbArticleStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(KbArticleStatus::cases(), 'value'),
        ];
    }

    /**
     * The title as a slug, with -2, -3, … added if the organization already uses it.
     * The slug is fixed at creation so links to the article keep working.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;

        for ($i = 2; KbArticle::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
