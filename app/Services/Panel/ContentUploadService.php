<?php

namespace App\Services\Panel;

use App\Enums\ContentType;
use App\Exceptions\Panel\ContentDeletedException;
use App\Exceptions\Panel\ContentOwnershipException;
use App\Exceptions\Panel\FileStorageException;
use App\Exceptions\Panel\UnsupportedContentTypeException;
use App\Models\Content;
use App\Models\ContentUrlUserPassword;
use App\Models\Monitor;
use App\Models\MonitorContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Panel write path.
 *
 * Concurrency contract (both delete() and attachToMonitor()):
 *   - Every operation that reads or mutates a Content row's `deleted` state
 *     must first acquire its row lock via `->lockForUpdate()`.
 *   - Ownership / deleted-state checks happen AFTER the lock, on the
 *     re-fetched row — never on a stale model passed into the method.
 *   - This is what makes "attach" and "delete" coordinate: they serialize
 *     on the same content row, so attach cannot observe pre-delete state
 *     after a concurrent delete commits.
 *
 * File deletion happens OUTSIDE the transaction so a DB rollback cannot
 * leave a ghost row (content alive but file gone). Orphan files are
 * recoverable — signage:doctor reports them.
 */
final class ContentUploadService
{
    private const IMAGE_DISPLAY = 10;

    private const VIDEO_DISPLAY = 0;

    private const URL_DISPLAY = 10;

    public function uploadFile(Monitor $monitor, UploadedFile $file, ContentType $type): Content
    {
        $this->assertFileUploadType($type);

        $storedPath = null;

        try {
            return DB::transaction(function () use ($monitor, $file, $type, &$storedPath) {
                $order = $this->lockMonitorAndNextOrder($monitor);
                $content = $this->createFileContent($file, $type, $order);

                $storedPath = "files/{$content->id}/{$content->content_name}";
                $this->storeFile($file, $content);

                $this->attachToMonitor($monitor, $content, $type, $order);

                return $content->fresh(['contentUrlUserPassword', 'user']);
            });
        } catch (\Throwable $e) {
            // Transaction rolled back. Any file we wrote is orphaned — remove it.
            if ($storedPath !== null) {
                try {
                    Storage::disk('media')->delete($storedPath);
                } catch (\Throwable) {
                    // best effort
                }
            }
            throw $e;
        }
    }

    public function createUrl(
        Monitor $monitor,
        string $url,
        ?string $userName = null,
        ?string $password = null,
    ): Content {
        return DB::transaction(function () use ($monitor, $url, $userName, $password) {
            $order = $this->lockMonitorAndNextOrder($monitor);
            $content = $this->createUrlContent($url, $userName, $password, $order);

            $this->attachToMonitor($monitor, $content, ContentType::Url, $order);

            return $content->fresh(['contentUrlUserPassword', 'user']);
        });
    }

    public function delete(Monitor $monitor, Content $content): DeletionResult
    {
        $fileToDelete = null;
        $wasDeleted = false;

        DB::transaction(function () use ($monitor, $content, &$fileToDelete, &$wasDeleted) {
            // 1. Lock content row FIRST. All operations touching `deleted`
            //    must acquire this lock before reading state.
            $locked = Content::query()
                ->where('id', $content->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new ContentOwnershipException('Content no longer exists');
            }

            // 2. Ownership check happens AFTER the lock — the pivot could
            //    have been detached by another request between the model
            //    being passed in and this transaction starting. A pre-lock
            //    check would be racy.
            $owns = MonitorContent::query()
                ->where('monitor_id', $monitor->id)
                ->where('content_id', $locked->id)
                ->exists();

            if (! $owns) {
                throw new ContentOwnershipException('Content does not belong to this monitor');
            }

            // 3. Detach from this monitor
            MonitorContent::query()
                ->where('monitor_id', $monitor->id)
                ->where('content_id', $locked->id)
                ->delete();

            // 4. If still attached elsewhere, content stays alive
            $stillUsed = MonitorContent::query()
                ->where('content_id', $locked->id)
                ->exists();

            if ($stillUsed) {
                return;
            }

            // 5. Orphaned — soft delete + queue file for removal after commit
            $locked->update(['deleted' => true]);
            $wasDeleted = true;

            if ($locked->content_type !== ContentType::Url->value) {
                $fileToDelete = "files/{$locked->id}/{$locked->content_name}";
            }
        });

        // File deletion AFTER commit. If the transaction rolled back, the
        // content row still says "alive" — we must NOT have deleted the file.
        if ($fileToDelete !== null) {
            try {
                $removed = Storage::disk('media')->delete($fileToDelete);

                // Filesystem::delete() returns false for "exists but couldn't
                // delete" (permissions, locked file). It returns true for both
                // "deleted successfully" and "already absent" — both desired.
                if ($removed === false) {
                    report(new FileStorageException(
                        "Failed to delete media file: {$fileToDelete}",
                    ));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return new DeletionResult(detached: true, deleted: $wasDeleted);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function assertFileUploadType(ContentType $type): void
    {
        if (! in_array($type, [ContentType::Image, ContentType::Video], true)) {
            throw new UnsupportedContentTypeException(
                "Content type {$type->name} is not a file upload",
            );
        }
    }

    // -------------------------------------------------------------------------
    // Ordering
    // -------------------------------------------------------------------------

    private function lockMonitorAndNextOrder(Monitor $monitor): int
    {
        // SELECT ... FOR UPDATE on the monitor row — serializes concurrent
        // uploads to the same monitor. Different monitors do not contend.
        Monitor::query()->where('id', $monitor->id)->lockForUpdate()->first();

        return ((int) MonitorContent::query()
            ->where('monitor_id', $monitor->id)
            ->max('content_order')) + 1;
    }

    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    private function createFileContent(UploadedFile $file, ContentType $type, int $order): Content
    {
        // hashName() = {random-hash}.{guessExtension()}
        // guessExtension() comes from server-detected MIME, not the client's
        // claimed filename — spoofed extensions are ignored.
        $storedName = $file->hashName();

        return Content::query()->create([
            'content_name' => $storedName,
            'client_file_name' => $file->getClientOriginalName(),
            'user_id' => null,
            'content_type' => $type->value,
            'is_auto_login' => false,
            'deleted' => false,
            'order' => $order,
            'uploaded_at' => now(),
            'from_date' => null,
            'to_date' => null,
            'content_url_user_password_id' => null,
        ]);
    }

    private function storeFile(UploadedFile $file, Content $content): void
    {
        $written = $file->storeAs(
            "files/{$content->id}",
            $content->content_name,
            'media',
        );

        if ($written === false) {
            throw new FileStorageException('Failed to write uploaded file to disk');
        }
    }

    private function createUrlContent(
        string $url,
        ?string $userName,
        ?string $password,
        int $order,
    ): Content {
        $userName = $userName !== null && $userName !== '' ? $userName : null;
        $password = $password !== null && $password !== '' ? $password : null;

        $cred = ContentUrlUserPassword::query()->create([
            'url' => $url,
            'user_name' => $userName,
            'password' => $password,
        ]);

        $hostname = parse_url($url, PHP_URL_HOST) ?: 'URL Content';

        return Content::query()->create([
            'content_name' => $hostname,
            'client_file_name' => null,
            'user_id' => null,
            'content_type' => ContentType::Url->value,
            'is_auto_login' => $userName !== null || $password !== null,
            'deleted' => false,
            'order' => $order,
            'uploaded_at' => now(),
            'from_date' => null,
            'to_date' => null,
            'content_url_user_password_id' => $cred->id,
        ]);
    }

    /**
     * Attach content to a monitor.
     *
     * The content row is re-locked and re-fetched BEFORE checking `deleted`,
     * using the same lock convention as delete(). This guarantees that any
     * concurrent delete() either commits before we hold the lock (we see
     * deleted=true → throw) or blocks until we commit (it sees our pivot).
     *
     * Currently unreachable in practice — every caller creates new content
     * inside the same transaction — but the pattern is required for any
     * future "attach existing content" feature.
     *
     * @throws ContentDeletedException when the locked row is deleted/absent
     */
    private function attachToMonitor(
        Monitor $monitor,
        Content $content,
        ContentType $type,
        int $order,
    ): MonitorContent {
        $locked = Content::query()
            ->where('id', $content->id)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new ContentDeletedException('Content no longer exists');
        }

        if ($locked->deleted) {
            throw new ContentDeletedException('Cannot attach deleted content');
        }

        return MonitorContent::query()->create([
            'monitor_id' => $monitor->id,
            'content_id' => $locked->id,
            'content_order' => $order,
            'display_time' => $this->defaultDisplayTime($type),
            'active' => true,
        ]);
    }

    private function defaultDisplayTime(ContentType $type): int
    {
        return match ($type) {
            ContentType::Image => self::IMAGE_DISPLAY,
            ContentType::Video => self::VIDEO_DISPLAY,
            ContentType::Url => self::URL_DISPLAY,
            default => 0,
        };
    }
}
