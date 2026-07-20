<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Tests\Functional;

use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\NextDoctrineDbalContentGraphProjectionReadModel;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

class RenameTablesMigrationTest extends AbstractContentRepositoryProjectionTestCase
{
    use RenameTablesMigrationTrait;

    /** @test */
    public function renameTablesNoOp()
    {
        $this->configureContentRepositories(<<<YAML
        contentRepositories:
          t_compatibility:
            eventStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\EventStore\DoctrineEventStoreFactory
            nodeTypeManager:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory
            contentDimensionSource:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory
            authProvider:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeAuthProviderFactory
            clock:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\Clock\SystemClockFactory
            subscriptionStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\SubscriptionStore\SubscriptionStoreFactory
            propertyConverters: {}
            contentGraphProjection:
              factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory
              catchUpHooks: {}
        YAML);

        $this->eventStore->setup();
        $this->subscriptionEngine->setup();
        $this->subscriptionEngine->boot();

        $this->contentRepository->handle(CreateRootWorkspace::create(WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-live')));
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('live')));

        # only old projection exists or tables were already migration nothing to do
        $this->getRenameTablesMigration(self::$contentRepositoryId)->executeUp();

        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('live')));
    }

    /** @test */
    public function renameTablesUpAndDown()
    {
        $this->configureContentRepositories(<<<YAML
        contentRepositories:
          t_compatibility:
            eventStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\EventStore\DoctrineEventStoreFactory
            nodeTypeManager:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory
            contentDimensionSource:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory
            authProvider:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeAuthProviderFactory
            clock:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\Clock\SystemClockFactory
            subscriptionStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\SubscriptionStore\SubscriptionStoreFactory
            propertyConverters: {}
            contentGraphProjection:
              factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory
              catchUpHooks: {}
            projections:
              "contentGraph_92":
                factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\NextDoctrineDbalContentGraphProjectionFactory
        YAML);

        $this->eventStore->setup();
        $this->subscriptionEngine->setup();
        $this->subscriptionEngine->boot();

        $this->contentRepository->handle(CreateRootWorkspace::create(WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-live')));
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('live')));
        $newContentGraphReadModel = $this->contentRepository->projectionState(NextDoctrineDbalContentGraphProjectionReadModel::class)->contentGraphReadModel;
        self::assertNotNull($newContentGraphReadModel->findWorkspaceByName(WorkspaceName::fromString('live')));

        $this->getRenameTablesMigration(self::$contentRepositoryId)->executeUp();
        $this->getRenameTablesMigration(self::$contentRepositoryId)->executeDown();

        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('live')));
        $newContentGraphReadModel = $this->contentRepository->projectionState(NextDoctrineDbalContentGraphProjectionReadModel::class)->contentGraphReadModel;
        self::assertNotNull($newContentGraphReadModel->findWorkspaceByName(WorkspaceName::fromString('live')));
    }
}
