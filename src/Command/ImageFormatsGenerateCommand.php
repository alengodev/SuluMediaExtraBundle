<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'alengo:media:image-formats:generate',
    description: 'Generate image formats XML from YAML configuration',
)]
class ImageFormatsGenerateCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source YAML file path', 'config/app/image-formats.yaml')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Target XML file path', 'config/image-formats.xml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourcePath = $this->projectDir . '/' . $input->getOption('source');
        $targetPath = $this->projectDir . '/' . $input->getOption('target');

        if (!\file_exists($sourcePath)) {
            $io->error(\sprintf('Source file not found: %s', $sourcePath));

            return Command::FAILURE;
        }

        $yamlContent = Yaml::parseFile($sourcePath);
        $formats = $yamlContent['image_formats'] ?? [];

        if (empty($formats)) {
            $io->warning('No image formats found in source file.');

            return Command::SUCCESS;
        }

        $existingFormats = $this->getExistingFormats($targetPath);
        $newFormats = [];

        foreach ($formats as $format) {
            if (!\in_array($format, $existingFormats, true)) {
                $newFormats[] = $format;
            }
        }

        if ([] === $newFormats) {
            $io->info('All formats already exist in target file.');

            return Command::SUCCESS;
        }

        $this->appendFormatsToXml($targetPath, $newFormats);

        $io->success(\sprintf('Added %d new format(s) to %s', \count($newFormats), $targetPath));

        foreach ($newFormats as $format) {
            $io->writeln(\sprintf('  - %s (+ @2x)', $format));
        }

        return Command::SUCCESS;
    }

    private function getExistingFormats(string $targetPath): array
    {
        if (!\file_exists($targetPath)) {
            return [];
        }

        $xml = \simplexml_load_file($targetPath);
        if (false === $xml) {
            return [];
        }

        $formats = [];
        foreach ($xml->format as $format) {
            $formats[] = \str_replace('@2x', '', (string) $format['key']);
        }

        return \array_unique($formats);
    }

    private function appendFormatsToXml(string $targetPath, array $newFormats): void
    {
        $newXml = '';
        foreach ($newFormats as $format) {
            [$x, $y] = $this->parseDimensions($format);
            $newXml .= $this->generateFormatXml($format, $x, $y);
            $newXml .= $this->generateFormatXml($format . '@2x', $x * 2, $y * 2);
        }

        if (!\file_exists($targetPath)) {
            \file_put_contents($targetPath, "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<formats>\n" . $newXml . "</formats>\n");

            return;
        }

        $content = \file_get_contents($targetPath);
        $insertPosition = \is_string($content) ? \strrpos($content, '</formats>') : false;

        if (false === $insertPosition) {
            throw new \RuntimeException(\sprintf('No closing </formats> tag found in "%s".', $targetPath));
        }

        \file_put_contents(
            $targetPath,
            \substr($content, 0, $insertPosition) . $newXml . "</formats>\n",
        );
    }

    private function parseDimensions(string $format): array
    {
        if (\str_starts_with($format, 'x')) {
            return [0, (int) \substr($format, 1)];
        }

        $parts = \explode('x', $format);

        return [(int) $parts[0], (int) ($parts[1] ?? 0)];
    }

    private function generateFormatXml(string $key, int $x, int $y): string
    {
        if ($x > 0 && $y > 0) {
            $scale = \sprintf('<scale x="%d" y="%d"/>', $x, $y);
        } elseif ($x > 0) {
            $scale = \sprintf('<scale x="%d"/>', $x);
        } else {
            $scale = \sprintf('<scale y="%d"/>', $y);
        }

        $internal = \str_contains($key, '@2x') ? '' : ' internal="true"';

        return \sprintf(
            "    <format key=\"%s\"%s>\n        %s\n    </format>\n",
            $key,
            $internal,
            $scale,
        );
    }
}
