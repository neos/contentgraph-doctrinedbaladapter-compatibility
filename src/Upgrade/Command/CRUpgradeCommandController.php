<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Upgrade;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\Upgrade\Command\CRUpgradeContextFactory;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\Upgrade\EventsDeduplicateBaseWorkspaceChanges\EventsDeduplicateBaseWorkspaceChangesUpgrade;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\Upgrade\EventsRecordedAtToUtc\EventsRecordedAtToUtcUpgrade;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * Copy of the Neos 9.2 CR upgrades distributed as pre-patch
 *
 * ~ ~ ~ ~ ~ ~ ~
 *
 * Provides destructive tooling to upgrade the content repository database for a new Neos release.
 *
 * While there is tooling for trivial schema adjustment see "cr:setup" the addition of new db columns without defaults
 * requires adding values inferred by the event stream which is handled by these advanced upgrades.
 *
 * Also rewriting events of the DBAL event-store if deemed required is part of this upgrade tooling.
 *
 * ~ ~ ~ ~ ~ ~ ~
 *
 * Please do ensure you have a backup of your database at hand.
 */
final class CRUpgradeCommandController extends CommandController
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected CRUpgradeContextFactory $upgradeContextFactory;

    /**
     * Optional upgrade to adjust event time stamps and node dates to UTC
     *
     * https://github.com/neos/neos-development-collection/pull/5716
     *
     * By storing "recordedAt" as datetime field we lost its original timezone information.
     * But we can make the assumption that its timezone should be the same as the one encoded in the ATOM metadata field "initiatingTimeStamp"
     *
     * The upgrade first groups all events by the ATOM offset found in "initiatingTimeStamp".
     * If all events are UTC "+00:00" the upgrade is not necessary. For all non UTC groups we convert the "recordedAt" datetime field
     * to the datetime in the UTC timezone.
     *
     * The upgrade must not be executed multiple times as it would remove the offset to match UTC again for the "recordedAt" datetime even if they are already meant to be UTC.
     * To prevent this from happening we compare the "recordedAt" and "initiatingTimeStamp" and if they are equal considering timezones we know the upgrade was run.
     *
     * Included in June 2026 - part of the bugfix 9.0.13, 9.1.6 and minor 9.2.0 release
     *
     * @param string $contentRepository Identifier of the Content Repository to upgrade
     */
    public function eventsRecordedAtToUtcCommand(string $contentRepository = 'default', bool $force = false): void
    {
        $context = $this->contentRepositoryRegistry->buildService(
            ContentRepositoryId::fromString($contentRepository),
            $this->upgradeContextFactory
        );

        if (!$force && !$this->output->askConfirmation(sprintf('> This will rewrite events of content repository "%s" to use UTC dates consistently and backup the original events. This will take even on big sites less than 5 minutes. To have the UTC changes applied to the graph a replay needs to be done which will take quite some time. Are you sure to proceed? (y/n) ', $context->contentRepositoryId->value), false)) {
            $this->outputLine('<comment>Abort.</comment>');
            return;
        }

        $upgrade = new EventsRecordedAtToUtcUpgrade(
            $context,
            $this->output->outputLine(...)
        );

        $upgrade->execute(
            force: $force
        );
    }

    /**
     * Upgrade to deduplicate parallel base workspace changes
     *
     * https://github.com/neos/neos-development-collection/issues/5877
     *
     * Workspace operations, also ChangeBaseWorkspace were not thread safe before the addition of workspace versioning
     * in the read model and thus safe soft constraint checks.
     * That resulted in the possibility that a single workspace was changed two or more times in parallel by the same user.
     * Because each ChangeBaseWorkspace can be slow and cleans up at the end the content stream via ContentStreamWasRemoved,
     * that results in possibly multiple illegal ContentStreamWasRemoved events and in total a fully illegal ChangeBaseWorkspace.
     *
     * The upgrade first identifies any duplicate ContentStreamWasRemoved events on a single stream.
     * If found we assume check via their unique prefixed correlation id that they belong to a ChangeBaseWorkspace sequence.
     * Then we find all events of the ChangeBaseWorkspace sequences and that occurred during these the content stream removals.
     * If there ar no other concurrent changes on our workspace - which would have been illegal as well - and it's truly
     * the race condition as understood with only the events a ChangeBaseWorkspace emits we continue.
     * We identify the last and thus valid ChangeBaseWorkspace sequence and delete all events from the previous illegal sequence(s).
     * The ChangeBaseWorkspace sequences can even be interlaced due to the race condition but the correlation id identifies the last sequence to keep uniquely.
     *
     * After the migration is applied the workspace will have the same new base workspace as without the migration,
     * but we deduplicated any temporary changes that happened in a race condition. No content on the workspaces is affected.
     *
     * Included in June 2026 - part of the minor 9.2.0 release
     *
     * @param string $contentRepository Identifier of the Content Repository to migrate
     */
    public function eventsDeduplicateBaseWorkspaceChangesCommand(string $contentRepository = 'default', bool $dryRun = false, bool $force = false): void
    {
        if ($dryRun && $force) {
            $this->outputLine('<comment>Abort. Cannot force a dry run;)</comment>');
            return;
        }

        $context = $this->contentRepositoryRegistry->buildService(
            ContentRepositoryId::fromString($contentRepository),
            $this->upgradeContextFactory
        );

        if ((!$dryRun && !$force) && !$this->output->askConfirmation(sprintf('> This will rewrite events of content repository "%s" to remove duplicated base workspace changes and backup the original events. This will take even on big sites less than 5 minutes. Are you sure to proceed? (y/n) ', $context->contentRepositoryId->value), false)) {
            $this->outputLine('<comment>Abort.</comment>');
            return;
        }

        $upgrade = new EventsDeduplicateBaseWorkspaceChangesUpgrade(
            $context,
            $this->output->outputLine(...)
        );

        $upgrade->execute(
            dryRun: $dryRun
        );
    }
}
