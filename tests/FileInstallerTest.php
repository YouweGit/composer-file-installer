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
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
                    'foo.php' => "Lorum ipsum\nDolor sit amet",
                ],
                'destination' => [],
            ]
        );

        /** @var FileMappingInterface&MockObject $mapping */
        $mapping = $this->createMock(FileMappingInterface::class);
        $mapping
            ->expects($this->atLeastOnce())
            ->method('getSource')
            ->willReturn(
                $fs->getChild('source/foo.php')->url()
            );

        $mapping
            ->expects($this->atLeastOnce())
            ->method('getDestination')
            ->willReturn(
                $fs->getChild('destination')->url() . '/foo.php'
            );

        $installer->installFile($mapping);

        $this->assertStringEqualsFile(
            $fs->getChild('destination/foo.php')->url(),
            "Lorum ipsum\nDolor sit amet"
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
                    'foo.php' => "Lorum ipsum\nDolor sit amet",
                ],
                'destination' => [],
            ]
        );

        /** @var FileMappingInterface&MockObject $mapping */
        $mapping = $this->createMock(FileMappingInterface::class);
        $mapping
            ->expects($this->atLeastOnce())
            ->method('getSource')
            ->willReturn(
                $fs->getChild('source/foo.php')->url()
            );

        $mapping
            ->expects($this->atLeastOnce())
            ->method('getDestination')
            ->willReturn(
                $fs->getChild('destination')->url() . '/path/to/foo.php'
            );

        $installer->installFile($mapping);

        $this->assertStringEqualsFile(
            $fs->getChild('destination/path/to/foo.php')->url(),
            "Lorum ipsum\nDolor sit amet"
        );
    }

    public function testMergeLineByLine(): void
    {
        /** @var FileMappingReaderInterface&MockObject $reader */
        $reader    = $this->createMock(FileMappingReaderInterface::class);
        $installer = new FileInstaller($reader);

        $fs = vfsStream::setup(
            sha1(__METHOD__),
            null,
            [
                'source' => [
                    'dotgitignore' => "vendor\ncomposer.lock\n\n.env.local\n.env.*.local\nother.txt\n# suggestion.txt\n# suggestion2.txt\n",
                ],
                'destination' => [
                    '.gitignore' => "composer.lock\nvendor\n\napp.config.local.php\nsuggestion.txt\n# other.txt",
                ],
            ]
        );

        /** @var FileMappingInterface&MockObject $mapping */
        $mapping = $this->createMock(FileMappingInterface::class);
        $mapping
            ->expects($this->atLeastOnce())
            ->method('getSource')
            ->willReturn(
                $fs->getChild('source/dotgitignore')->url()
            );

        $mapping
            ->expects($this->atLeastOnce())
            ->method('getDestination')
            ->willReturn(
                $fs->getChild('destination/.gitignore')->url()
            );

        $installer->mergeLineByLine($mapping);

        // - It will leave the composer.lock and vendor as is, these exist in both template and destination (albeit in different order)
        // - It will leave the app.config.local.php as is, that exist only in destination
        // - It will leave the (uncommented) suggestion.txt, that exist commented in template and was uncommented in destination already
        // - It will leave the (commented) other.txt, that exist in template but was commented in destination
        // - It will add .env.local and .env.*.local, that exist in template but wasn't in the destination yet
        // - It will add the # suggestion2.txt, that exist in template but wasn't in the destination yet
        $this->assertSame(
            "composer.lock\nvendor\n\napp.config.local.php\nsuggestion.txt\n# other.txt\n.env.local\n.env.*.local\n# suggestion2.txt\n",
            file_get_contents($fs->getChild('destination/.gitignore')->url())
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
                    'foo.php' => 'Foo',
                    'bar.php' => 'Bar',
                    'other.php' => 'Other',
                    'dotgitignore' => '.env.local',
                ],
                'destination' => [
                    'bar.php' => 'Custom',
                    'other.php' => 'Overwrite me',
                    '.gitignore' => 'vendor',
                ]
            ]
        );

        $createMapping = function (array $options, string $source, string $destination) use ($fs): FileMappingInterface {
            $mapping = $this->createMock(FileMappingInterface::class);
            $mapping
                ->expects($this->atLeastOnce())
                ->method('getOptions')
                ->willReturn($options);
            $mapping
                ->expects($this->any())
                ->method('getSource')
                ->willReturn($fs->getChild('source')->url() . '/' . $source);
            $mapping
                ->expects($this->atLeastOnce())
                ->method('getDestination')
                ->willReturn($fs->getChild('destination')->url() . '/' . $destination);
            return $mapping;
        };

        $mapping1 = $createMapping([], 'foo.php', 'foo.php');
        $mapping2 = $createMapping([], 'bar.php', 'bar.php');
        $mapping3 = $createMapping([FileInstaller::MAPPING_OPTION_FORCE_OVERWRITE], 'other.php', 'other.php');
        $mapping4 = $createMapping([FileInstaller::MAPPING_OPTION_MERGE_LINE_BY_LINE], 'dotgitignore', '.gitignore');

        $reader
            ->expects($this->exactly(5))
            ->method('valid')
            ->willReturnOnConsecutiveCalls(true, true, true, true, false);

        $reader
            ->expects($this->exactly(4))
            ->method('current')
            ->willReturnOnConsecutiveCalls($mapping1, $mapping2, $mapping3, $mapping4);

        /** @var IOInterface&MockObject $io */
        $io = $this->createMock(IOInterface::class);
        $io
            ->expects($this->exactly(4))
            ->method('write')
            ->with($this->isString());

        $installer->install($io);

        $this->assertSame(
            'Foo',
            file_get_contents($fs->getChild('destination/foo.php')->url()),
            'foo.php should be created'
        );
        $this->assertSame(
            'Custom',
            file_get_contents($fs->getChild('destination/bar.php')->url()),
            'Existing bar.php should not be overwritten'
        );
        $this->assertSame(
            'Other',
            file_get_contents($fs->getChild('destination/other.php')->url()),
            'Existing other.php should be replaced'
        );
        $this->assertSame(
            "vendor\n.env.local\n",
            file_get_contents($fs->getChild('destination/.gitignore')->url()),
            'Existing .gitignore should be merged'
        );
    }

    #[TestWith(['hello world', 'hello world'], 'Plain text')]
    #[TestWith(['  some line  ', 'some line'], 'Normal line is trimmed')]
    #[TestWith([' # this is commented  ', 'this is commented'], 'Left # is stripped')]
    #[TestWith(['# this is commented #', 'this is commented #'], 'Right # is not stripped')]
    #[TestWith([' // php comment  ', 'php comment'], 'Left // is stripped')]
    #[TestWith([' /** php docblock */ ', 'php docblock'], '/** and */ are stripped')]
    #[TestWith([' /* php comment 2 */ ', 'php comment 2'], '/* and */ are stripped')]
    #[TestWith(['ended comment */ ', 'ended comment'], 'ending */ is stripped')]
    #[TestWith([' * continued comment block ', 'continued comment block'], 'Left * is stripped')]
    #[TestWith([' * continued ending block */ ', 'continued ending block'], 'Left * and ending */ is stripped')]
    #[TestWith([' $ other weird characters % are not stripped @ ', '$ other weird characters % are not stripped @'], 'Other chars are not stripped')]
    public function testStripCommentFromLine(string $line, string $expected): void
    {
        $this->assertSame($expected, FileInstaller::stripCommentFromLine($line));
    }
}
