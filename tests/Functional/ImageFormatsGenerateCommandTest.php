<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\Tests\Functional;

use Alengo\SuluMediaExtraBundle\Command\ImageFormatsGenerateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end test of the YAML -> image-formats.xml generation. This command has
 * no Sulu dependencies, so it runs without a kernel and exercises the real
 * parsing, scaling and XML-append logic.
 */
class ImageFormatsGenerateCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = \sys_get_temp_dir() . '/alengo-media-extra-gen-' . \uniqid('', true);
        \mkdir($this->projectDir . '/config/app', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function testGeneratesFormatsWithDoubledVariants(): void
    {
        \file_put_contents(
            $this->projectDir . '/config/app/image-formats.yaml',
            "image_formats:\n    - 400x300\n    - 800x\n    - x600\n",
        );

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--source' => 'config/app/image-formats.yaml',
            '--target' => 'config/image-formats.xml',
        ]);

        self::assertSame(0, $exitCode);

        $xml = \file_get_contents($this->projectDir . '/config/image-formats.xml');
        self::assertIsString($xml);

        // base format is marked internal, @2x variant is not
        self::assertStringContainsString('<format key="400x300" internal="true">', $xml);
        self::assertStringContainsString('<scale x="400" y="300"/>', $xml);
        self::assertStringContainsString('<format key="400x300@2x">', $xml);
        self::assertStringContainsString('<scale x="800" y="600"/>', $xml);

        // single-axis formats keep only the defined axis
        self::assertStringContainsString('<format key="800x" internal="true">', $xml);
        self::assertStringContainsString('<scale x="800"/>', $xml);
        self::assertStringContainsString('<scale x="1600"/>', $xml);
        self::assertStringContainsString('<format key="x600" internal="true">', $xml);
        self::assertStringContainsString('<scale y="600"/>', $xml);
        self::assertStringContainsString('<scale y="1200"/>', $xml);

        // result is well-formed XML
        self::assertInstanceOf(\SimpleXMLElement::class, \simplexml_load_string($xml));
    }

    public function testExistingFormatsAreNotDuplicated(): void
    {
        \file_put_contents(
            $this->projectDir . '/config/app/image-formats.yaml',
            "image_formats:\n    - 400x300\n",
        );
        \file_put_contents(
            $this->projectDir . '/config/image-formats.xml',
            "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<formats>\n"
            . "    <format key=\"400x300\" internal=\"true\">\n        <scale x=\"400\" y=\"300\"/>\n    </format>\n"
            . "</formats>\n",
        );

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--source' => 'config/app/image-formats.yaml',
            '--target' => 'config/image-formats.xml',
        ]);

        self::assertSame(0, $exitCode);

        $xml = (string) \file_get_contents($this->projectDir . '/config/image-formats.xml');
        self::assertSame(1, \substr_count($xml, '<format key="400x300"'));
        self::assertStringContainsString('already exist', $tester->getDisplay());
    }

    public function testMissingSourceFileFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--source' => 'config/app/does-not-exist.yaml']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Source file not found', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        // CommandTester drives the command directly, avoiding Application::add()
        // which was removed in Symfony 8 — so the test runs on 6.4, 7.x and 8.x.
        return new CommandTester(new ImageFormatsGenerateCommand($this->projectDir));
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }

        \rmdir($dir);
    }
}
