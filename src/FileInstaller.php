<?php

/**
 * Copyright © Youwe. All rights reserved.
 * https://www.youweagency.com
 */

declare(strict_types=1);

namespace Youwe\Composer;

use Composer\IO\IOInterface;
use RuntimeException;
use SplFileObject;
use Youwe\FileMapping\FileMappingInterface;
use Youwe\FileMapping\FileMappingReaderInterface;

class FileInstaller
{
    /** @var FileMappingReaderInterface */
    private $mappingReader;

    /**
     * Constructor.
     *
     * @param FileMappingReaderInterface $mappingReader
     */
    public function __construct(FileMappingReaderInterface $mappingReader)
    {
        $this->mappingReader = $mappingReader;
    }

    /**
     * Install the deployer files.
     *
     * @param IOInterface $io
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function install(IOInterface $io)
    {
        foreach ($this->mappingReader as $mapping) {
            if (file_exists($mapping->getDestination())) {
                continue;
            }

            $this->installFile($mapping);

            $io->write(
                sprintf(
                    '<info>Installed:</info> %s',
                    $mapping->getRelativeDestination()
                )
            );
        }
    }

    /**
     * Install the given file if it does not exist.
     *
     * @param FileMappingInterface $mapping
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function installFile(FileMappingInterface $mapping)
    {
        $destination = $mapping->getDestination();
        $parent = dirname($destination);

        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new RuntimeException("Directory \"$parent\" could not be created");
        }

        $inputFile  = new SplFileObject($mapping->getSource(), 'r');
        $targetFile = new SplFileObject($destination, 'w+');

        foreach ($inputFile as $input) {
            $targetFile->fwrite($input);
        }
    }
}
