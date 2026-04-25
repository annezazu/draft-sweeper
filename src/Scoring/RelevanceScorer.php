<?php
declare(strict_types=1);

namespace DraftSweeper\Scoring;

final class RelevanceScorer
{
    /**
     * Cosine similarity between a draft's term IDs (presence vector) and
     * a weighted vector of term IDs aggregated across recently published posts.
     *
     * @param int[]              $draftTermIds   Distinct term IDs on the draft.
     * @param array<int, int>    $recentTermFreq Map of term ID -> frequency in recent posts.
     */
    public function score(array $draftTermIds, array $recentTermFreq): float
    {
        if ($draftTermIds === [] || $recentTermFreq === []) {
            return 0.0;
        }

        $dot = 0.0;
        $draftMagSq = 0.0;
        foreach ($draftTermIds as $id) {
            $draftMagSq += 1.0;
            if (isset($recentTermFreq[$id])) {
                $dot += $recentTermFreq[$id];
            }
        }

        $recentMagSq = 0.0;
        foreach ($recentTermFreq as $freq) {
            $recentMagSq += $freq * $freq;
        }

        if ($draftMagSq === 0.0 || $recentMagSq === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($draftMagSq) * sqrt($recentMagSq));
    }
}
