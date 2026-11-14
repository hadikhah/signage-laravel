<?php

namespace App\Legacy;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Builds the legacy response envelope.
 *
 * Every API controller response goes through one of these helpers —
 * never hand-build ['ResultCode' => ..., 'Message' => ..., 'Data' => ...]
 * in a controller. This class is the executable form of the envelope
 * contract declared in Codes.php.
 */
final class Envelope
{
    public static function list(mixed $data, int $code = Codes::SUCCESS_LIST): array
    {
        return self::build($code, null, $data);
    }

    public static function paginated(LengthAwarePaginator $page, int $code = Codes::SUCCESS_LIST): array
    {
        return self::build($code, null, [
            Codes::KEY_ITEMS => $page->items(),
            Codes::KEY_TOTAL_COUNT => $page->total(),
            Codes::KEY_PAGE_INDEX => $page->currentPage(),
            Codes::KEY_ROW_NUMBER => $page->perPage(),
        ]);
    }

    public static function error(int $code, string $message): array
    {
        return self::build($code, $message, null);
    }

    private static function build(int $code, ?string $message, mixed $data): array
    {
        return [
            Codes::KEY_RESULT_CODE => $code,
            Codes::KEY_MESSAGE => $message,
            Codes::KEY_DATA => $data,
        ];
    }

    public static function playback(mixed $data): array
    {
        return self::build(Codes::SUCCESS_PLAYBACK, null, $data);
    }
}
