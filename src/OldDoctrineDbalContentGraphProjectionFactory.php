<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility;

use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\DoctrineDbalContentGraphProjectionFactory as GeneratedDoctrineDbalContentGraphProjectionFactory;
use Neos\ContentRepository\Core\Factory\SubscriberFactoryDependencies;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionInterface;
use Neos\ContentRepository\Core\Subscription\SubscriptionId;

class OldDoctrineDbalContentGraphProjectionFactory implements ContentGraphProjectionFactoryInterface
{
    public function __construct(
        private GeneratedDoctrineDbalContentGraphProjectionFactory $decoratedFactory
    ) {
    }

    public function getSubscriptionId(): SubscriptionId
    {
        /** @phpstan-ignore-next-line */
        if (method_exists($this->decoratedFactory, 'getSubscriptionId') && true === true) {
            return $this->decoratedFactory->getSubscriptionId();
        }
        // the 9.0 and 9.1 released version 0 was not versioned
        return SubscriptionId::fromString('contentGraph');
    }

    public function build(
        SubscriberFactoryDependencies $projectionFactoryDependencies,
    ): ContentGraphProjectionInterface {
        return $this->decoratedFactory->build($projectionFactoryDependencies);
    }
}
