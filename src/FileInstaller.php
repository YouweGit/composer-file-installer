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
    public const MAPPING_OPTION_MERGE_LINE_BY_LINE = 'merge-line-by-line';
    public const MAPPING_OPTION_FORCE_OVERWRITE = 'force-overwrite';

    /**
     * Constructor.
     *
     * @param FileMappingReaderInterface $mappingReader
     */
    public function __construct(
        private readonly FileMappingReaderInterface $mappingReader,
    ) {}

    /**
     * Install the deployer files.
     *
     * @param IOInterface $io
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function install(IOInterface $io): void
    {
        foreach ($this->mappingReader as $mapping) {
            if (in_array(self::MAPPING_OPTION_MERGE_LINE_BY_LINE, $mapping->getOptions(), true)) {
                $this->mergeLineByLine($mapping);
                $done = 'Merged with existing';
            } elseif (file_exists($mapping->getDestination())) {
                if (in_array(self::MAPPING_OPTION_FORCE_OVERWRITE, $mapping->getOptions(), true)) {
                    $this->installFile($mapping);
                    $done = 'Overwritten existing';
                } else {
                    $done = 'Skipped existing';
                }
            } else {
                $this->installFile($mapping);
                $done = 'Installed';
            }

            $io->write(
                sprintf(
                    '<info>%s:</info> %s',
                    $done,
                    $mapping->getRelativeDestination(),
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
    public function installFile(FileMappingInterface $mapping): void
    {
        $this->ensureDirectoryExists($mapping);

        $inputFile  = new SplFileObject($mapping->getSource(), 'r');
        $targetFile = new SplFileObject($mapping->getDestination(), 'w');

        foreach ($inputFile as $input) {
            $targetFile->fwrite($input);
        }
    }

    public function mergeLineByLine(FileMappingInterface $mapping): void
    {
        if (!file_exists($mapping->getDestination())) {
            $this->installFile($mapping);
            return;
        }

        $inputFile = new SplFileObject($mapping->getSource(), 'r');
        if (!$inputFile->isFile()) {
            throw new RuntimeException("Merge line-by-line is only available on regular files, '" . $inputFile->getRealPath() . "' is a ". $inputFile->getType());
        }

        $targetFile = new SplFileObject($mapping->getDestination(), 'a+');
        $existingLines = array_map(self::stripCommentFromLine(...), iterator_to_array($targetFile));

        $targetFile->fseek($targetFile->getSize() - 1);
        $fileEndsWithNewLine = $targetFile->fgetc() === "\n";
        // Note: cursor in targetFile is now at end

        foreach ($inputFile as $line) {
            if (empty(trim($line)) || in_array(self::stripCommentFromLine($line), $existingLines)) {
                continue;
            }

            if (!$fileEndsWithNewLine) {
                $targetFile->fwrite("\n");
                $fileEndsWithNewLine = true;
            }
            $targetFile->fwrite(rtrim($line, "\r\n") . "\n");
        }
    }

    private function ensureDirectoryExists(FileMappingInterface $mapping): void
    {
        $parent = dirname($mapping->getDestination());

        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new RuntimeException("Directory \"$parent\" could not be created");
        }
    }

    /**
     * @internal
     */
    public static function stripCommentFromLine(string $line): string
    {
        // Regex format: ^open_comment|close_comment$
        // Where open_comment = '//' or '#' or '/**' or '/*' or '*' followed by whitespace
        // Where close_comment = whitespace followed by '*/'
        return preg_replace('/^(\/\/|#|\/\*\*|\/\*|\*)\s*|\s*(\*\/)$/', '', trim($line));
    }
}
