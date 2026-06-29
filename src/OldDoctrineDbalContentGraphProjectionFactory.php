<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility;

use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\DoctrineDbalContentGraphProjectionFactory as GeneratedDoctrineDbalContentGraphProjectionFactory;
use Neos\ContentRepository\Core\Factory\SubscriberFactoryDependencies;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionInterface;

class OldDoctrineDbalContentGraphProjectionFactory implements ContentGraphProjectionFactoryInterface
{
    public function __construct(
        private GeneratedDoctrineDbalContentGraphProjectionFactory $decoratedFactory
    ) {
    }

    public function build(
        SubscriberFactoryDependencies $projectionFactoryDependencies,
    ): ContentGraphProjectionInterface {
        return $this->decoratedFactory->build($projectionFactoryDependencies);
    }
}
