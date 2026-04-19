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
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is already prepared above.
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? Incident::fromRow($row) : null;
    }

    /**
     * @return list<Incident>
     */
    public function all(int $limit = 200): array
    {
        $table = $this->tableName();
        $sql = $this->wpdb->prepare("SELECT * FROM {$table} ORDER BY discovered_at DESC LIMIT %d", $limit);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is already prepared above.
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

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
