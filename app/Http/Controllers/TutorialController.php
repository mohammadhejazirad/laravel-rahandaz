<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tutorials\TutorialRepository;
use App\Http\PaginatorPayload;
use App\Models\TutorialBookmark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class TutorialController extends Controller
{
    public function index(Request $request, TutorialRepository $repository): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'bookmarked' => ['nullable', 'in:0,1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $bookmarks = TutorialBookmark::query()->where('user_id', $request->user()->id)->pluck('tutorial_slug');
        $all = $repository->all();
        $terms = preg_split('/\s+/u', $repository->normalize(trim($filters['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rows = $all->filter(function (array $row) use ($filters, $terms, $repository, $bookmarks): bool {
            if (($filters['category'] ?? '') !== '' && $row['category'] !== $filters['category']) {
                return false;
            }
            if (($filters['bookmarked'] ?? '0') === '1' && ! $bookmarks->contains($row['slug'])) {
                return false;
            }
            $text = $repository->normalize($row['title'].' '.$row['summary'].' '.$row['category'].' '.$row['content'].' '.implode(' ', $row['tags']));
            foreach ($terms as $term) {
                if (! str_contains($text, $term)) {
                    return false;
                }
            }

            return true;
        })->map(function (array $row) use ($bookmarks): array {
            unset($row['content']);

            return [...$row, 'bookmarked' => $bookmarks->contains($row['slug'])];
        })->values();
        $page = (int) ($filters['page'] ?? 1);
        $paginator = new LengthAwarePaginator($rows->slice(($page - 1) * 12, 12)->values(), $rows->count(), 12, $page, ['path' => route('tutorials.index')]);
        $paginator->appends($request->query());

        return Inertia::render('tutorials/index', [
            'tutorials' => ['data' => $paginator->items(), 'meta' => PaginatorPayload::meta($paginator)],
            'categories' => $all->pluck('category')->unique()->values()->all(),
            'filters' => ['q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? '', 'bookmarked' => ($filters['bookmarked'] ?? '0') === '1'],
        ]);
    }

    public function show(Request $request, string $slug, TutorialRepository $repository): Response
    {
        $entry = $repository->find($slug);

        return Inertia::render('tutorials/show', [
            'tutorial' => [...$entry, 'bookmarked' => TutorialBookmark::query()->where('user_id', $request->user()->id)->where('tutorial_slug', $slug)->exists()],
            'related' => $repository->all()->where('category', $entry['category'])->where('slug', '!=', $slug)->take(4)->map(fn (array $row): array => ['slug' => $row['slug'], 'title' => $row['title']])->values()->all(),
        ]);
    }

    public function bookmark(Request $request, string $slug, TutorialRepository $repository): RedirectResponse
    {
        $repository->find($slug);
        $data = $request->validate(['bookmarked' => ['required', 'boolean']]);
        if ($data['bookmarked']) {
            TutorialBookmark::query()->upsert([['user_id' => $request->user()->id, 'tutorial_slug' => $slug, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'tutorial_slug'], ['updated_at']);
        } else {
            TutorialBookmark::query()->where('user_id', $request->user()->id)->where('tutorial_slug', $slug)->delete();
        }

        return back()->with('status', $data['bookmarked'] ? 'آموزش نشان شد.' : 'نشان آموزش حذف شد.');
    }
}
