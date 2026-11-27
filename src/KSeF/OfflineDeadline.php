<?php

declare(strict_types=1);

namespace Polski\KSeF;

// existing-checked: src/Invoice/KSeF/KSeFSyncService.php - the service holds the queue and calls this, but the calendar rules are pure date math that tests and the settings page need without a repository, an API client or WordPress; the spec names this class.

/**
 * When an offline invoice has to be in KSeF at the latest.
 *
 * Source: github.com/CIRFMF/ksef-docs, tryby-offline.md (10.07.2025), the
 * table "Zestawienie trybow" and "Termin przeslania faktury do KSeF przy
 * kolejnych zdarzeniach":
 *   offline24  next working day after the issue date (P_1),
 *   offline    next working day after the announced unavailability ends,
 *   awaryjny   7 working days after the announced failure ends; a later
 *              failure announcement starts the count again.
 * A failure announced while an offline24 or offline invoice waits moves its
 * deadline to 7 working days after that failure ends.
 *
 * Working days are Monday to Friday without Polish public holidays. The
 * documentation's own example (failure ending on Saturday 2025-07-12, deadline
 * Tuesday 2025-07-22) only works out with Saturday as a day off.
 *
 * The deadline is the end of that day in Europe/Warsaw. Null when it depends
 * on an end date nobody announced yet.
 */
final class OfflineDeadline
{
    public const MODES = ['offline24', 'offline', 'awaryjny'];

    private const FAILURE_WORKING_DAYS = 7;

    /**
     * @param \Closure(int): list<string>|null $holidays Y-m-d holidays of a year; defaults to the free plugin's list.
     */
    public static function for(
        string $mode,
        \DateTimeInterface $issueDate,
        ?\DateTimeInterface $outageEnd = null,
        ?\DateTimeInterface $failureEnd = null,
        ?\Closure $holidays = null,
    ): ?\DateTimeImmutable {
        $holidays ??= self::polishHolidays(...);

        $deadline = match ($mode) {
            'offline24' => self::addWorkingDays($issueDate, 1, $holidays),
            'offline' => $outageEnd !== null ? self::addWorkingDays($outageEnd, 1, $holidays) : null,
            'awaryjny' => $failureEnd !== null ? self::addWorkingDays($failureEnd, self::FAILURE_WORKING_DAYS, $holidays) : null,
            default => throw new \InvalidArgumentException("Unknown KSeF offline mode: {$mode}"), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer error, never rendered unescaped.
        };

        if ($failureEnd !== null && $mode !== 'awaryjny') {
            $afterFailure = self::addWorkingDays($failureEnd, self::FAILURE_WORKING_DAYS, $holidays);
            $deadline = $deadline === null || $afterFailure > $deadline ? $afterFailure : $deadline;
        }

        return $deadline;
    }

    /**
     * The calendar day $days working days after $from (in Warsaw), at 23:59:59 Warsaw time.
     *
     * @param \Closure(int): list<string> $holidays
     */
    private static function addWorkingDays(\DateTimeInterface $from, int $days, \Closure $holidays): \DateTimeImmutable
    {
        $zone = new \DateTimeZone('Europe/Warsaw');
        $day = \DateTimeImmutable::createFromInterface($from)->setTimezone($zone)->setTime(0, 0);
        $off = [];

        while ($days > 0) {
            $day = $day->modify('+1 day');
            $year = (int) $day->format('Y');
            $off[$year] ??= array_flip($holidays($year));

            if ((int) $day->format('N') < 6 && ! isset($off[$year][$day->format('Y-m-d')])) {
                $days--;
            }
        }

        return $day->setTime(23, 59, 59);
    }

    /**
     * Polish public holidays from the free plugin (with Christmas Eve from
     * 2025). It has them from Polski 1.48.0; an older free plugin gives
     * weekends only, which can only bring a deadline earlier, never later.
     *
     * @return list<string>
     */
    private static function polishHolidays(int $year): array
    {
        try {
            return \Polski\Service\DeliveryEstimateService::polishHolidays($year);
        } catch (\Error) {
            // The free plugin before 1.48.0 has the class but not this method.
            return [];
        }
    }
}
