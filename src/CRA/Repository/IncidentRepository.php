<?php

declare(strict_types=1);

namespace Polski\CRA\Repository;

use Polski\CRA\Model\Incident;
use wpdb;

defined('ABSPATH') || exit;

final class IncidentRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_cra_incidents';
    }

    public function find(int $id): ?Incident
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table, statement prepared with %i and %d placeholders.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tableName(),
                $id,
            ),
            ARRAY_A,
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return is_array($row) ? Incident::fromRow($row) : null;
    }

    /**
     * @return list<Incident>
     */
    public function all(int $limit = 200): array
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table, statement prepared with %i and %d placeholders.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i ORDER BY discovered_at DESC LIMIT %d',
                $this->tableName(),
                $limit,
            ),
            ARRAY_A,
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $row): Incident => Incident::fromRow($row),
            $rows,
        ));
    }

    public function save(Incident $incident): int
    {
        $data = $incident->toDbRow();
        $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($incident->id === null) {
            $this->wpdb->insert($this->tableName(), $data, $formats);

            return (int) $this->wpdb->insert_id;
        }

        $this->wpdb->update($this->tableName(), $data, ['id' => $incident->id], $formats, ['%d']);

        return $incident->id;
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete($this->tableName(), ['id' => $id], ['%d']);
    }
}
