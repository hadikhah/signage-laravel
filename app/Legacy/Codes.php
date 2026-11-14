<?php

namespace App\Legacy;

/**
 * Legacy API value contract — result codes, envelope keys, pagination keys.
 *
 * NOT a PHP interface. A bag of constants that the legacy Angular client
 * depends on. See docs/decisions/0001-api-contract-assumptions.md for the
 * evidence behind each value.
 *
 * TODO(contract): update this file — not the controllers — when the real
 * contract is confirmed.
 */
final class Codes
{
    // ---- Success (confirmed) ------------------------------------------------
    public const SUCCESS_LIST = 5000;   // Step 5 sample

    public const SUCCESS_PLAYBACK = 4000;   // Step 7 sample

    // ---- Errors (inferred — TODO(contract): confirm) ------------------------
    public const ERROR_SERVER = 5001;

    public const ERROR_NOT_FOUND = 4004;

    public const ERROR_VALIDATION = 4001;

    // ---- Envelope keys (confirmed by Step 5 + Step 7) -----------------------
    public const KEY_RESULT_CODE = 'ResultCode';

    public const KEY_MESSAGE = 'Message';

    public const KEY_DATA = 'Data';

    // ---- Pagination response keys (inferred — TODO(contract): confirm) ------
    public const KEY_ITEMS = 'Items';

    public const KEY_TOTAL_COUNT = 'TotalCount';

    public const KEY_PAGE_INDEX = 'PageIndex';

    public const KEY_ROW_NUMBER = 'RowNumber';
}
