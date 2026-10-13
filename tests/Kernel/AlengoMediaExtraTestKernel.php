<?php

declare(strict_types=1);

namespace Alengo\SuluMediaExtraBundle\Tests\Kernel;

use Alengo\SuluMediaExtraBundle\AlengoMediaExtraBundle;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanupInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal kernel that boots only FrameworkBundle plus this bundle.
 *
 * The four Sulu services the bundle autowires are registered as public
 * synthetic definitions so the container compiles; tests inject mocks for
 * them after boot. This keeps the smoke test free of a database or a full
 * Sulu kernel while still exercising the real service wiring.
 */
class AlengoMediaExtraTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new AlengoMediaExtraBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
                'secrets' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ]);

            foreach ([
                EntityManagerInterface::class,
                MediaRepositoryInterface::class,
                StorageInterface::class,
                PathCleanupInterface::class,
            ] as $id) {
                $definition = new Definition();
                $definition->setSynthetic(true);
                $definition->setPublic(true);
                $container->setDefinition($id, $definition);
            }
        });
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return \sys_get_temp_dir() . '/alengo-media-extra-tests/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return \sys_get_temp_dir() . '/alengo-media-extra-tests/log';
    }
}
