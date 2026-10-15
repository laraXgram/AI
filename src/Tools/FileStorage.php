<?php

namespace LaraGram\Ai\Tools;

use LaraGram\Contracts\Filesystem\Filesystem;
use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Tools\Filesystem\CopyFile;
use LaraGram\Ai\Tools\Filesystem\DeleteFile;
use LaraGram\Ai\Tools\Filesystem\FileExists;
use LaraGram\Ai\Tools\Filesystem\GetFileMetadata;
use LaraGram\Ai\Tools\Filesystem\GetFileUrl;
use LaraGram\Ai\Tools\Filesystem\ListFiles;
use LaraGram\Ai\Tools\Filesystem\MoveFile;
use LaraGram\Ai\Tools\Filesystem\ReadFile;
use LaraGram\Ai\Tools\Filesystem\WriteFile;

class FileStorage
{
    /**
     * Create all of the file storage tools for the given disk.
     *
     * @return Collection<int, Tool>
     */
    public static function all(string|Filesystem|null $disk = null): Collection
    {
        return static::readOnly($disk)->merge([
            new WriteFile($disk),
            new DeleteFile($disk),
            new CopyFile($disk),
            new MoveFile($disk),
        ]);
    }

    /**
     * Create the read-only file storage tools for the given disk.
     *
     * @return Collection<int, Tool>
     */
    public static function readOnly(string|Filesystem|null $disk = null): Collection
    {
        return new Collection([
            new ListFiles($disk),
            new ReadFile($disk),
            new FileExists($disk),
            new GetFileMetadata($disk),
            new GetFileUrl($disk),
        ]);
    }
}
