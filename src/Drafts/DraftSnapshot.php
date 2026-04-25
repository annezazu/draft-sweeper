<?php
declare(strict_types=1);

namespace DraftSweeper\Drafts;

final class DraftSnapshot
{
    /**
     * @param int[] $termIds
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $editLink,
        public readonly int $wordCount,
        public readonly bool $hasTitle,
        public readonly bool $hasExcerpt,
        public readonly bool $hasFeaturedImage,
        public readonly int $categoryCount,
        public readonly int $tagCount,
        public readonly array $termIds,
        public readonly int $daysSinceModified,
        public readonly string $modifiedHuman,
        public readonly string $startedHuman,
        public readonly string $excerpt,
    ) {
    }
}
