<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanupInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alengo:media:rename',
    description: 'Rename a media file (changes the filename in database and storage)',
)]
class MediaRenameCommand extends Command
{
    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly StorageInterface $storage,
        private readonly PathCleanupInterface $pathCleanup,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('mediaId', InputArgument::REQUIRED, 'The ID of the media to rename')
            ->addArgument('newName', InputArgument::REQUIRED, 'The new filename (without extension — extension is preserved from original)')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale for filename normalization', 'en')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be done without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $mediaId = (int) $input->getArgument('mediaId');
        $newName = $input->getArgument('newName');
        $locale = $input->getOption('locale');
        $dryRun = $input->getOption('dry-run');

        $media = $this->mediaRepository->findMediaById($mediaId);
        if (null === $media) {
            $io->error(\sprintf('Media with ID %d not found.', $mediaId));

            return Command::FAILURE;
        }

        $file = $media->getFiles()->first();
        if (false === $file) {
            $io->error('Media has no file.');

            return Command::FAILURE;
        }

        /** @var FileVersion|null $fileVersion */
        $fileVersion = $file->getFileVersions()->filter(
            static fn (FileVersion $fv) => $fv->getVersion() === $file->getVersion(),
        )->first() ?: null;

        if (null === $fileVersion) {
            $io->error('Could not find active file version.');

            return Command::FAILURE;
        }

        $oldName = $fileVersion->getName();
        $oldStorageOptions = $fileVersion->getStorageOptions();
        $oldExtension = \pathinfo($oldName, \PATHINFO_EXTENSION);
        $newBaseName = \pathinfo((string) $newName, \PATHINFO_FILENAME) ?: $newName;
        $newDisplayName = '' !== $oldExtension ? $newBaseName . '.' . $oldExtension : $newBaseName;
        $normalizedFileName = $this->getNormalizedFileName($newDisplayName, $locale);

        $io->section('Media Rename');
        $io->table(
            ['Property', 'Old Value', 'New Value'],
            [
                ['Media ID', $mediaId, $mediaId],
                ['Display Name', $oldName, $newDisplayName],
                ['Storage Filename', $oldStorageOptions['fileName'] ?? 'N/A', $normalizedFileName],
            ],
        );

        if ($oldExtension && \str_contains((string) $newName, '.')) {
            $providedExtension = \pathinfo((string) $newName, \PATHINFO_EXTENSION);
            if ($providedExtension !== $oldExtension) {
                $io->note(\sprintf(
                    'Extension ".%s" was ignored. Original extension ".%s" is preserved.',
                    $providedExtension,
                    $oldExtension,
                ));
            }
        }

        if ($dryRun) {
            $io->warning('Dry run mode — no changes will be made.');

            return Command::SUCCESS;
        }

        if (!$io->confirm('Do you want to proceed with the rename?', false)) {
            $io->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        try {
            $newStorageOptions = $this->storage->move(
                $oldStorageOptions,
                \array_merge($oldStorageOptions, ['fileName' => $normalizedFileName]),
            );

            $fileVersion->setName($newDisplayName);
            $fileVersion->setStorageOptions($newStorageOptions);
            $fileVersion->increaseSubVersion();

            $this->entityManager->flush();

            $io->success(\sprintf(
                'Successfully renamed media %d from "%s" to "%s".',
                $mediaId,
                $oldName,
                $newDisplayName,
            ));
            $io->note('The format cache has been invalidated (subVersion increased). Cached image formats will be regenerated on next request.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('Failed to rename media: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function getNormalizedFileName(string $originalFileName, string $locale): string
    {
        if (\str_contains($originalFileName, '.')) {
            $pathParts = \pathinfo($originalFileName);
            $fileName = $this->pathCleanup->cleanup($pathParts['filename'], $locale);
            $fileName .= '.' . ($pathParts['extension'] ?? '');
        } else {
            $fileName = $this->pathCleanup->cleanup($originalFileName, $locale);
        }

        return $fileName;
    }
}
