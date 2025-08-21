<?php

/**
 * Copyright © Youwe. All rights reserved.
 * https://www.youweagency.com
 */

declare(strict_types=1);

namespace Youwe\Composer\Tests;

use Composer\IO\IOInterface;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Youwe\Composer\FileInstaller;
use Youwe\FileMapping\FileMappingInterface;
use Youwe\FileMapping\FileMappingReaderInterface;

#[CoversClass(FileInstaller::class)]
class FileInstallerTest extends TestCase
{
    public function testConstructor(): void
    {
        /** @var FileMappingReaderInterface&MockObject $reader */
        $reader = $this->createMock(FileMappingReaderInterface::class);
        $this->assertInstanceOf(
            FileInstaller::class,
            new FileInstaller($reader)
        );
    }

    public function testInstallFile(): void
    {
        /** @var FileMappingReaderInterface&MockObject $reader */
        $reader    = $this->createMock(FileMappingReaderInterface::class);
        $installer = new FileInstaller($reader);

        $fs = vfsStream::setup(
            sha1(__METHOD__),
            null,
            [
                'source' => [
                    'foo.php' => 'Foo',
                ],
                'destination' => [],
            ]
        );

        /** @var FileMappingInterface&MockObject $mapping */
        $mapping = $this->createMock(FileMappingInterface::class);
        $mapping
            ->expects($this->once())
            ->method('getSource')
            ->willReturn(
                $fs->getChild('source/foo.php')->url()
            );

        $mapping
            ->expects($this->once())
            ->method('getDestination')
            ->willReturn(
                $fs->getChild('destination')->url() . '/foo.php'
            );

        $installer->installFile($mapping);

        $this->assertStringEqualsFile(
            $fs->getChild('destination/foo.php')->url(),
            'Foo'
        );
    }

    public function testInstallFileWhenDestinationPathNotExistsYet(): void
    {
        /** @var FileMappingReaderInterface&MockObject $reader */
        $reader = $this->createMock(FileMappingReaderInterface::class);
        $installer = new FileInstaller($reader);

        $fs = vfsStream::setup(
            sha1(__METHOD__),
            null,
            [
                'source' => [
                    'foo.php' => 'Foo',
                ],
                'destination' => [],
            ]
        );

        /** @var FileMappingInterface&MockObject $mapping */
        $mapping = $this->createMock(FileMappingInterface::class);
        $mapping
            ->expects($this->once())
            ->method('getSource')
            ->willReturn(
                $fs->getChild('source/foo.php')->url()
            );

        $mapping
            ->expects($this->once())
            ->method('getDestination')
            ->willReturn(
                $fs->getChild('destination')->url() . '/path/to/foo.php'
            );

        $installer->installFile($mapping);

        $this->assertStringEqualsFile(
            $fs->getChild('destination/path/to/foo.php')->url(),
            'Foo'
        );
    }

    public function testInstall(): void
    {
        /** @var FileMappingReaderInterface&MockObject $reader */
        $reader    = $this->createMock(FileMappingReaderInterface::class);
        $installer = new FileInstaller($reader);

        $fs = vfsStream::setup(
            sha1(__METHOD__),
            null,
            [
                'source' => [
                    'foo.php' => 'Foo'
                ],
                'destination' => []
            ]
        );

        /** @var FileMappingInterface&MockObject $mapping */
        $mapping = $this->createMock(FileMappingInterface::class);
        $mapping
            ->expects($this->once())
            ->method('getSource')
            ->willReturn(
                $fs->getChild('source/foo.php')->url()
            );

        $mapping
            ->expects($this->exactly(3))
            ->method('getDestination')
            ->willReturn(
                $fs->getChild('destination')->url() . '/foo.php'
            );

        $mapping
            ->expects($this->once())
            ->method('getRelativeDestination')
            ->willReturn('foo.php');

        $reader
            ->expects($this->exactly(4))
            ->method('valid')
            ->willReturnOnConsecutiveCalls(true, false, true, false);

        $reader
            ->expects($this->exactly(2))
            ->method('current')
            ->willReturn($mapping);

        /** @var IOInterface|PHPUnit_Framework_MockObject_MockObject $io */
        $io = $this->createMock(IOInterface::class);
        $io
            ->expects($this->once())
            ->method('write')
            ->with($this->isString());

        $installer->install($io);

        $this->assertStringEqualsFile(
            $fs->getChild('destination/foo.php')->url(),
            'Foo'
        );

        $installer->install($io);
    }
}
