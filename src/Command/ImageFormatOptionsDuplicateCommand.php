<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Entity\FormatOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'alengo:media:image-format-options:duplicate',
    description: 'Duplicate me_format_options entries from @2x to non-@2x format keys',
)]
class ImageFormatOptionsDuplicateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'source',
            's',
            InputOption::VALUE_REQUIRED,
            'Source YAML file path (relative to project root)',
            'config/app/image-formats.yaml',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $yamlPath = $this->projectDir . '/' . $input->getOption('source');
        if (!\file_exists($yamlPath)) {
            $io->error(\sprintf('Source file not found: %s', $yamlPath));

            return Command::FAILURE;
        }

        $yamlContent = Yaml::parseFile($yamlPath);
        $formats = $yamlContent['image_formats'] ?? [];

        if (empty($formats)) {
            $io->info('No formats defined in source file.');

            return Command::SUCCESS;
        }

        $repository = $this->entityManager->getRepository(FormatOptions::class);
        $duplicated = 0;

        foreach ($formats as $formatKey) {
            $sourceKey = $formatKey . '@2x';
            $sourceEntries = $repository->findBy(['formatKey' => $sourceKey]);

            foreach ($sourceEntries as $source) {
                $duplicate = $repository->findOneBy([
                    'formatKey' => $formatKey,
                    'fileVersion' => $source->getFileVersion(),
                ]);

                if (null === $duplicate) {
                    $duplicate = new FormatOptions();
                    $duplicate->setFormatKey($formatKey);
                    $duplicate->setFileVersion($source->getFileVersion());
                }

                $duplicate->setCropX($source->getCropX());
                $duplicate->setCropY($source->getCropY());
                $duplicate->setCropWidth($source->getCropWidth());
                $duplicate->setCropHeight($source->getCropHeight());

                $this->entityManager->persist($duplicate);
                ++$duplicated;

                $io->writeln(\sprintf(
                    '  %s → %s (fileVersion: %d)',
                    $sourceKey,
                    $formatKey,
                    $source->getFileVersion()->getId(),
                ));
            }
        }

        $this->entityManager->flush();
        $io->success(\sprintf('Duplicated %d entries.', $duplicated));

        return Command::SUCCESS;
    }
}
