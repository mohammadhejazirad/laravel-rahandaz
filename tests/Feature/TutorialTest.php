<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tutorials\TutorialRepository;
use App\Models\TutorialBookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class TutorialTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_search_in_content_and_category_is_paginated_and_normalizes_persian(): void
    {
        [$user] = $this->panelAccount();
        $this->actingAs($user)->get(route('tutorials.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('tutorials/index')->has('tutorials.data', 12)->where('tutorials.meta.total', 22));
        $this->get(route('tutorials.index', ['q' => 'symlink']))->assertInertia(fn (Assert $p) => $p->where('tutorials.meta.total', fn ($total) => $total > 0));
        $this->get(route('tutorials.index', ['q' => 'پشتيبان']))->assertInertia(fn (Assert $p) => $p->where('tutorials.meta.total', fn ($total) => $total > 0));
        $this->get(route('tutorials.index', ['category' => 'Git']))->assertInertia(fn (Assert $p) => $p->has('tutorials.data', 2));
        $this->get(route('tutorials.index', ['q' => str_repeat('x', 121)]))->assertSessionHasErrors('q');
        $this->get(route('tutorials.index', ['q' => 'unfindablephrase']))->assertInertia(fn (Assert $p) => $p->has('tutorials.data', 0));
        $this->get(route('tutorials.index', ['page' => 2]))->assertInertia(fn (Assert $p) => $p->where('tutorials.meta.current_page', 2)->has('tutorials.data', 10));
    }

    public function test_bookmark_is_idempotent_private_and_persists_across_refresh(): void
    {
        [$user] = $this->panelAccount();
        [$other] = $this->panelAccount();
        $url = route('tutorials.bookmark', 'backups');
        $this->actingAs($user)->put($url, ['bookmarked' => true])->assertRedirect();
        $this->put($url, ['bookmarked' => true])->assertRedirect();
        $this->assertSame(1, TutorialBookmark::query()->count());
        $this->get(route('tutorials.show', 'backups'))->assertInertia(fn (Assert $p) => $p->component('tutorials/show')->where('tutorial.bookmarked', true)->where('tutorial.slug', 'backups'));
        $this->get(route('tutorials.index', ['bookmarked' => 1]))->assertInertia(fn (Assert $p) => $p->has('tutorials.data', 1));
        $this->flushSession();
        $this->actingAs($other)->put($url, ['bookmarked' => false])->assertRedirect();
        $this->get(route('tutorials.index', ['bookmarked' => 1]))->assertInertia(fn (Assert $p) => $p->has('tutorials.data', 0));
        $this->assertSame(1, TutorialBookmark::query()->count());
        $this->flushSession();
        $this->actingAs($user)->put($url, ['bookmarked' => false])->assertRedirect();
        $this->assertSame(0, TutorialBookmark::query()->count());
    }

    public function test_unknown_content_and_unauthenticated_requests_cannot_write_bookmarks(): void
    {
        $this->get(route('tutorials.index'))->assertRedirect(route('login'));
        [$user] = $this->panelAccount();
        $this->actingAs($user)->get(route('tutorials.show', 'unknown'))->assertNotFound();
        $this->put(route('tutorials.bookmark', 'unknown'), ['bookmarked' => true])->assertNotFound();
        $this->assertSame(0, TutorialBookmark::query()->count());
    }

    public function test_all_repository_content_and_contextual_destinations_exist(): void
    {
        $all = app(TutorialRepository::class)->all();
        $this->assertSame($all->count(), $all->pluck('slug')->unique()->count());
        foreach ($all as $article) {
            $this->assertGreaterThan(600, mb_strlen($article['content']), $article['slug']);
            $this->assertStringNotContainsString('<script', $article['content']);
        }
        preg_match_all("/'([a-z]+(?:-[a-z]+)*)'/", file_get_contents(resource_path('js/components/help-link.tsx')), $matches);
        foreach ($matches[1] as $slug) {
            if (in_array($slug, ['react', 'lucide-react'], true)) {
                continue;
            }
            $this->assertNotNull($all->firstWhere('slug', $slug), $slug);
        }
    }
}
