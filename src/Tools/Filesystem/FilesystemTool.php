<?php

namespace LaraGram\Ai\Tools\Filesystem;

use LaraGram\Contracts\Filesystem\Filesystem;
use LaraGram\Support\Facades\Storage;
use LaraGram\Ai\Approvals\Approval;
use LaraGram\Ai\Concerns\InteractsWithApprovals;
use LaraGram\Ai\Contracts\Approvable;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Tools\Request;
use Throwable;

abstract class FilesystemTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(protected Filesystem|string|null $disk = null) {}

    /**
     * Determine whether the given path points to a file, not a directory.
     */
    protected function fileExists(Filesystem $disk, string $path): bool
    {
        if (method_exists($disk, 'fileExists')) {
            return $disk->fileExists($path);
        }

        try {
            $disk->size($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve the filesystem disk the tool operates on.
     */
    protected function disk(): Filesystem
    {
        return $this->disk instanceof Filesystem
            ? $this->disk
            : Storage::disk($this->disk);
    }

    /**
     * Determine whether the tool needs approval for the given request.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return false;
    }
}
