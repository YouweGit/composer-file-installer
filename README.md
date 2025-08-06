# composer-file-installer
Install files in a project as part of a `composer install` or `composer update`. 
Uses the [youwe/file-mapping](https://github.com/YouweGit/file-mapping) package for moving files according to 
a source -> destination mapping. The Composer `IOInterface` supplies the file installer with the capability to 
write the files and supply end-users with output messages.

## Usage

Create a mapping config file according to the [youwe/file-mapping](https://github.com/YouweGit/file-mapping) documentation.
```text
file1.php
file2.php:force-overwrite
{dot,.}gitignore:merge-line-by-line
```

### Available options

| Option               | Explanation                                                                                                                                                                                                                                                                                     |
|----------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `merge-line-by-line` | Performs a line-by-line merge. Every line from the source that doesn't exist in the destination yet will be copied over. Comments will be ignored (will not be copied again). The destination file will be created if it doesn't exist yet. This is particularly useful for `.gitignore` files. |
| `force-overwrite`    | Forces an overwrite, even if the file already exists on the destination.                                                                                                                                                                                                                        |
| (no option given)    | The file will be copied over if it didn't exist yet and will be left untouched otherwise.                                                                                                                                                                                                       |

### Code example
```php
<?php

use Youwe\FileMapping\UnixFileMappingReader;
use Youwe\Composer\FileInstaller;

// Create a file mapping.
$mappingFilePaths = new UnixFileMapping(
     __DIR__ . '/../folder/files',
     getcwd(),
     './path/to/mapping-config'
);

// Get a file mapping reader.
$reader = new UnixFileMappingReader($sourceDirectory, $targetDirectory, $mappingFilePaths);

// Get an installer, supply with the file mapping reader.
$installer = new FileInstaller($reader);

// Install according to mapping, supply with Composer IOInterface.
$installer->install($io);
```
