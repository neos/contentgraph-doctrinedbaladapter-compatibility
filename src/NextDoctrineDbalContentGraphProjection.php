<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility;

use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\DoctrineDbalContentGraphProjection;
use Neos\ContentRepository\Core\Projection\ProjectionInterface;

/**
 * @implements ProjectionInterface<NextDoctrineDbalContentGraphProjectionReadModel>
 */
final class NextDoctrineDbalContentGraphProjection extends DoctrineDbalContentGraphProjection implements ProjectionInterface
{
    public function getState(): NextDoctrineDbalContentGraphProjectionReadModel
    {
        return new NextDoctrineDbalContentGraphProjectionReadModel($this->contentGraphReadModel);
    }
}
