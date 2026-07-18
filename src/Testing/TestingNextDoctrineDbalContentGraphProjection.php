<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Testing;

use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\DoctrineDbalContentGraphProjection;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;

/**
 * @internal only for testing
 */
final class TestingNextDoctrineDbalContentGraphProjection extends DoctrineDbalContentGraphProjection implements ContentGraphProjectionInterface
{
    public function getState(): ContentGraphReadModelInterface
    {
        return $this->contentGraphReadModel;
    }
}
