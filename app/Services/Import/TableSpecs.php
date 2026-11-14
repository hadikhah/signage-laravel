<?php

namespace App\Services\Import;

/**
 * Canonical import order. Parents before children (FK targets first).
 * Add new tables here — no other file needs to change.
 */
final class TableSpecs
{
    /** @return TableSpec[]  In import order. */
    public static function all(): array
    {
        return [
            new TableSpec(
                table: 'cities',
                csvFile: 'cities.csv',
                columns: ['id', 'name'],
                requiredCols: ['id', 'name'],
            ),
            new TableSpec(
                table: 'application_settings',
                csvFile: 'application_settings.csv',
                columns: ['id', 'bypass_all_content', 'active_base_content', 'last_update'],
                requiredCols: ['id'],
            ),
            new TableSpec(
                table: 'offices',
                csvFile: 'offices.csv',
                columns: ['id', 'name', 'city_id'],
                requiredCols: ['id', 'name', 'city_id'],
            ),
            new TableSpec(
                table: 'departments',
                csvFile: 'departments.csv',
                columns: ['id', 'department_name', 'daily_message', 'is_child', 'lock', 'parent_id', 'office_id'],
                requiredCols: ['id', 'department_name', 'office_id'],
            ),
            new TableSpec(
                table: 'monitors',
                csvFile: 'monitors.csv',
                columns: ['id', 'ip_address', 'host_name', 'department_id', 'mac_address', 'floor', 'wing', 'status', 'lock', 'description', 'platform', 'owner'],
                requiredCols: ['id', 'ip_address', 'department_id'],
            ),
            new TableSpec(
                table: 'contents',
                csvFile: 'contents.csv',
                columns: ['id', 'content_name', 'client_file_name', 'user_id', 'content_type', 'is_auto_login', 'deleted', 'order', 'uploaded_at', 'from_date', 'to_date', 'content_url_user_password_id'],
                requiredCols: ['id', 'content_name', 'content_type'],
            ),
            new TableSpec(
                table: 'monitor_contents',
                csvFile: 'monitor_contents.csv',
                columns: ['id', 'monitor_id', 'content_id', 'content_order', 'display_time', 'active'],
                requiredCols: ['id', 'monitor_id', 'content_id'],
            ),
            new TableSpec(
                table: 'views',
                csvFile: 'views.csv',
                columns: ['id', 'name'],
                requiredCols: ['id', 'name'],
            ),
            new TableSpec(
                table: 'view_contents',
                csvFile: 'view_contents.csv',
                columns: ['id', 'view_id', 'content_id'],
                requiredCols: ['id', 'view_id', 'content_id'],
            ),
            new TableSpec(
                table: 'monitor_views',
                csvFile: 'monitor_views.csv',
                columns: ['id', 'monitor_id', 'view_id'],
                requiredCols: ['id', 'monitor_id', 'view_id'],
            ),

            // --- share layer ----
            new TableSpec(
                table: 'share_contents',
                csvFile: 'share_contents.csv',
                columns: ['id', 'content_id'],
                requiredCols: ['id', 'content_id'],
            ),
            new TableSpec(
                table: 'share_monitors',
                csvFile: 'share_monitors.csv',
                columns: ['id', 'monitor_id'],
                requiredCols: ['id', 'monitor_id'],
            ),

            // --- base layer ----
            new TableSpec(
                table: 'base_contents',
                csvFile: 'base_contents.csv',
                columns: ['id', 'content_id'],
                requiredCols: ['id', 'content_id'],
            ),
            new TableSpec(
                table: 'base_monitors',
                csvFile: 'base_monitors.csv',
                columns: ['id', 'monitor_id'],
                requiredCols: ['id', 'monitor_id'],
            ),
        ];
    }

    public static function forTable(string $table): ?TableSpec
    {
        foreach (self::all() as $spec) {
            if ($spec->table === $table) {
                return $spec;
            }
        }

        return null;
    }
}
