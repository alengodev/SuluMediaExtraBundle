<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\Tests\Functional;

use Alengo\SuluMediaExtraBundle\Command\ImageFormatOptionsDuplicateCommand;
use Alengo\SuluMediaExtraBundle\Command\ImageFormatsGenerateCommand;
use Alengo\SuluMediaExtraBundle\Command\MediaRenameCommand;
use Alengo\SuluMediaExtraBundle\EventSubscriber\MediaCropModifiedSubscriber;
use Alengo\SuluMediaExtraBundle\Tests\Kernel\AlengoMediaExtraTestKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanupInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots the bundle inside a real Symfony kernel and asserts every service
 * compiles and resolves to the expected class. This catches DI / autowiring
 * regressions against whatever Symfony and Sulu versions are installed.
 */
class ServiceWiringTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AlengoMediaExtraTestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $container->set(EntityManagerInterface::class, $this->createMock(EntityManagerInterface::class));
        $container->set(MediaRepositoryInterface::class, $this->createMock(MediaRepositoryInterface::class));
        $container->set(StorageInterface::class, $this->createMock(StorageInterface::class));
        $container->set(PathCleanupInterface::class, $this->createMock(PathCleanupInterface::class));
    }

    /**
     * @param class-string $serviceId
     * @param class-string $expectedClass
     */
    #[DataProvider('serviceProvider')]
    public function testServiceIsWired(string $serviceId, string $expectedClass): void
    {
        $service = self::getContainer()->get($serviceId);

        self::assertInstanceOf($expectedClass, $service);
    }

    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function serviceProvider(): iterable
    {
        yield 'crop subscriber' => [MediaCropModifiedSubscriber::class, MediaCropModifiedSubscriber::class];
        yield 'image-formats:generate command' => [ImageFormatsGenerateCommand::class, ImageFormatsGenerateCommand::class];
        yield 'image-format-options:duplicate command' => [ImageFormatOptionsDuplicateCommand::class, ImageFormatOptionsDuplicateCommand::class];
        yield 'media:rename command' => [MediaRenameCommand::class, MediaRenameCommand::class];
    }

    public function testCropSubscriberListensToCropModifiedEvent(): void
    {
        $events = MediaCropModifiedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(\Sulu\Bundle\MediaBundle\Domain\Event\MediaCropModifiedEvent::class, $events);
    }
}
