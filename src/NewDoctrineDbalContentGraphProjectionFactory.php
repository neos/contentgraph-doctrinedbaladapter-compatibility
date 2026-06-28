<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility;

use Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjection;
use Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory;
use Neos\ContentRepository\Core\Factory\SubscriberFactoryDependencies;
use Neos\ContentRepository\Core\Projection\ProjectionFactoryInterface;

/**
 * @implements ProjectionFactoryInterface<DoctrineDbalContentGraphProjection>
 */
class NewDoctrineDbalContentGraphProjectionFactory implements ProjectionFactoryInterface
{
    public function __construct(
        private DoctrineDbalContentGraphProjectionFactory $decoratedFactory
    ) {
    }

    public function build(
        SubscriberFactoryDependencies $projectionFactoryDependencies,
        array $options,
    ): DoctrineDbalContentGraphProjection {
        $requiredGraphSubscriptionId = $options['requiredGraphSubscriptionId'] ?? throw new \RuntimeException('"requiredGraphSubscriptionId" to validate is not set', 1782634978);

        if ($this->decoratedFactory->getSubscriptionId()->value !== $requiredGraphSubscriptionId) {
            throw new \RuntimeException(sprintf('Expected contentGraph subscription id %s but got %s', $this->decoratedFactory->getSubscriptionId()->value, $requiredGraphSubscriptionId), 1782635009);
        }

        return $this->decoratedFactory->build($projectionFactoryDependencies);
    }
}
