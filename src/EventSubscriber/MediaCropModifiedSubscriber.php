<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaCropModifiedEvent;
use Sulu\Bundle\MediaBundle\Entity\FormatOptions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Syncs @2x crop settings to the corresponding base format key.
 *
 * When a crop is saved for e.g. "800x@2x", the same crop coordinates
 * are automatically applied to "800x" so both formats stay in sync.
 */
class MediaCropModifiedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MediaCropModifiedEvent::class => 'onMediaCropModified',
        ];
    }

    public function onMediaCropModified(MediaCropModifiedEvent $event): void
    {
        $formatKey = $event->getFormatKey();

        if (!\str_contains($formatKey, '@2x')) {
            return;
        }

        $targetKey = \str_replace('@2x', '', $formatKey);
        $media = $event->getMedia();
        $file = $media->getFiles()->first();

        if (!$file) {
            return;
        }

        $fileVersion = $file->getLatestFileVersion();
        if (!$fileVersion) {
            return;
        }

        $repository = $this->entityManager->getRepository(FormatOptions::class);

        $source = $repository->findOneBy([
            'formatKey' => $formatKey,
            'fileVersion' => $fileVersion,
        ]);

        if (!$source instanceof FormatOptions) {
            return;
        }

        $duplicate = $repository->findOneBy([
            'formatKey' => $targetKey,
            'fileVersion' => $fileVersion,
        ]);

        if (null === $duplicate) {
            $duplicate = new FormatOptions();
            $duplicate->setFormatKey($targetKey);
            $duplicate->setFileVersion($fileVersion);
        }

        $duplicate->setCropX($source->getCropX());
        $duplicate->setCropY($source->getCropY());
        $duplicate->setCropWidth($source->getCropWidth());
        $duplicate->setCropHeight($source->getCropHeight());

        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();
    }
}
