<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Drafts;

use DraftSweeper\Drafts\DraftRepository;
use PHPUnit\Framework\TestCase;

final class DraftRepositoryTest extends TestCase
{
    public function test_query_args_exclude_drafts_with_future_drafts_meta(): void
    {
        $repo = new DraftRepository();
        $args = $repo->buildQueryArgs();

        $this->assertArrayHasKey('meta_query', $args);
        $this->assertSame(
            [
                [
                    'key'     => '_future_draft_remind_on',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            $args['meta_query'],
        );
    }

    public function test_query_args_keep_core_draft_filters(): void
    {
        // Drafts without the Future Drafts meta still match the underlying
        // post_status='draft' filter — the meta_query only narrows the set.
        $args = (new DraftRepository(25))->buildQueryArgs(7);

        $this->assertSame('post', $args['post_type']);
        $this->assertSame('draft', $args['post_status']);
        $this->assertSame(25, $args['posts_per_page']);
        $this->assertSame(7, $args['author']);
    }

    public function test_query_args_omit_author_when_user_id_not_given(): void
    {
        $args = (new DraftRepository())->buildQueryArgs();

        $this->assertArrayNotHasKey('author', $args);
    }
}
