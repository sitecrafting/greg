<?php

/**
 * Greg\Calendar class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

use DateTime;
use DateTimeInterface;
use DateTimeImmutable;
use DateInterval;
use InvalidArgumentException;
use RRule\RRule;
use RRule\RSet;

/**
 * Calendar class for parsing/translating recurrence rules to
 * individual recurrence instances.
 *
 * NOTE: Calendar is not responsible for querying events, only for translating
 * their recurrence rules into separate instances.
 */
class Calendar {
  /**
   * Zero or more events and their respective recurrence rules
   *
   * @var array
   */
  protected $events;

  /**
   * Options configured for this instance
   *
   * @var array
   */
  protected $options;

  /**
   * Create a new Calendar for parsing recurrence rules
   *
   * @param array $events an array of event data
   * @param array $options options for this Calendar instance
   */
  public function __construct(array $events, array $options = []) {
    $this->events  = $events;
    $this->options = $options;
  }

  /**
   * Get an array of events, parsed out into individual recurrences
   *
   * @param array $opts optional additional constraints to place on the recurrences,
   * for example if querying by event_month when multi-month recurring events
   * show up in the query results. Supported options:
   * * earliest: date string
   * * latest: date string
   * * event_month: shorthand for: [
   *     'earliest' => (first of month),
   *     'latest'   => (last of month),
   *   ]
   * @return array
   */
  public function recurrences(array $opts = []) : array {
    $reducer = function(array $recurrences, array $event) use ($opts) : array {
      return $this->aggregate_recurrences($recurrences, $event, $opts);
    };

    $recurrences = array_reduce($this->events, $reducer, []);

    // sort by start date
    usort($recurrences, function(array $a, array $b) : int {
      return strcmp($a['start'], $b['start']);
    });

    return $recurrences;
  }

  /**
   * Compute recurrences for the given event, adding them to any previously
   * known $recurrences
   *
   * @param array $recurrences recurrences we've already collected
   * @param array $event the event series data to parse out and add
   * @param array $constraints extra constraints that each event should
   * fulfill to be included in the final aggregate
   * @return array the aggregated recurrence data
   */
  protected function aggregate_recurrences(
    array $recurrences,
    array $event,
    array $constraints
  ) : array {
    if (empty($event['recurrence'])) {
      // no recurrence rules to parse; just add this event
      // to the aggregate and return
      $recurrences[] = $event;
      return $recurrences;
    }

    $rules = $event['recurrence'];
    $rset  = new RSet();

    $override_durations = [];
    $rrules             = [];
    if (!empty($rules['overrides'])) {
      $start_date = gmdate('Y-m-d', strtotime($event['start']));
      $end_date   = gmdate('Y-m-d', strtotime($event['end']));

      foreach ($rules['overrides'] as $override) {
        // Support different durations for recurrences from overridden RRules.
        $override_days = $override['BYDAY'] ?? [];
        if (is_array($override_days) && !empty($override_days)) {
          foreach ($override_days as $day) {
            $override_durations[RRule::WEEKDAYS[$day] . '-' . $override['start']] = $this->duration(
              $start_date . ' ' . $override['start'],
              $end_date . ' ' . $override['end']
            );
          }
        } else {
          $override_durations[$override['start']] = $this->duration(
            $start_date . ' ' . $override['start'],
            $end_date . ' ' . $override['end']
          );
        }

        $rrules[] = new RRule([
          'DTSTART' => $start_date . ' ' . $override['start'],
          'BYDAY'   => $override_days,
          'FREQ'    => strtoupper($rules['frequency']),
          'UNTIL'   => $rules['until'],
        ]);
      }
    } else {
      $rrules = [
        new RRule([
          'DTSTART' => $event['start'],
          'FREQ'    => strtoupper($rules['frequency']),
          'UNTIL'   => $rules['until'],
        ]),
      ];

      $start_time             = gmdate('H:i:s', strtotime($event['start']));
      $durations[$start_time] = $this->duration($event['start'], $event['end']);
    }

    foreach ($rrules as $rrule) {
      $rset->addRRule($rrule);
    }

    foreach ($this->normalize_exceptions($event) as $exception) {
      $rset->addExDate($exception);
    }

    $duration = $this->duration($event['start'], $event['end']);

    // Get the human-readable description of the recurrence from the event
    // itself, or generate it from the RRule
    $description = $this->describe($event, $rrules[0]);

    foreach ($rset->getOccurrences() as $recurrence) {
      if ($override_durations) {
        $duration = $override_durations[$recurrence->format('N-H:i:s')]
          ?? $override_durations[$recurrence->format('H:i:s')];
      }
      $end        = $this->end_from_start($recurrence, $duration);
      $recurrence = array_merge($event, [
        'start'                  => $recurrence->format('Y-m-d H:i:s'),
        'end'                    => $end->format('Y-m-d H:i:s'),
        'recurrence_description' => $description,
      ]);

      if ($this->satisfies($recurrence, $constraints)) {
        $recurrences[] = $recurrence;
      }
    }

    return $recurrences;
  }

  /**
   * Whether the given event/recurrence satisfies all $constraints
   *
   * @param array $recurrence the recurrence/event in question
   * @param array $constraints the constraints by which events should be limited
   * @return bool
   */
  protected function satisfies(array $recurrence, array $constraints) : bool {
    if (!empty($constraints['event_month'])) {
      $month = date_create_immutable($constraints['event_month']);
      if (!$month) {
        // We can't parse this; assume no match.
        return false;
      }

      $constraints['earliest'] = $month->format('Y-m-01 00:00:00');

      $constraints['latest'] = $month
        ->modify('+1 month')
        ->modify('-1 second')
        ->format('Y-m-d 23:59:59');
    }

    if (
      !empty($constraints['earliest'])
      && $recurrence['start'] < $constraints['earliest']
    ) {
      return false;
    }

    if (
      !empty($constraints['latest'])
      && $recurrence['start'] > $constraints['latest']
    ) {
      return false;
    }

    return true;
  }

  /**
   * Get a DateInterval given start and end date strings
   *
   * @param string $start start date string
   * @param string $end end date string
   * @return DateInterval the derived duration
   * @throws InvalidArgumentException if $start or $end is invalid
   */
  protected function duration(string $start, string $end) : DateInterval {
    // TODO option to create from format
    $from = date_create_immutable($start);
    $to   = date_create_immutable($end);

    if (empty($from)) {
      throw new InvalidArgumentException(sprintf(
        'Invalid date string: %s',
        $start
      ));
    }
    if (empty($to)) {
      throw new InvalidArgumentException(sprintf(
        'Invalid date string: %s',
        $end
      ));
    }

    return $from->diff($to);
  }

  /**
   * Calculate an end DateTimeImmutable object given start and duration
   *
   * @param DateTime $start the start date
   * @param DateInterval $duration how long
   * @return DateTimeImmutable
   */
  protected function end_from_start(DateTime $start, DateInterval $duration) : DateTimeImmutable {
    return DateTimeImmutable::createFromMutable($start)->add($duration);
  }

  /**
   * Get the recurrence description for the given event series
   *
   * @param array $event raw event series data
   * @param RRule $rrule the RRULE representing this event
   * @return string
   */
  protected function describe(array $event, RRule $rrule) : string {
    return $event['recurrence_description'] ?? $rrule->humanReadable([
      'date_formatter' => function(DateTimeInterface $date) : string {
        return $date->format($this->options['human_readable_format'] ?? 'M j, Y');
      },
    ]);
  }

  /**
   * Normalize exceptions dates, dealing with nested arrays and mismatched
   * times.
   *
   * @param array $event a single recurring event array
   * @return Array<DateTimeImmutable>
   */
  protected function normalize_exceptions(array $event) : array {
    $rules      = $event['recurrence'];
    $exceptions = $rules['exceptions'] ?? [];

    $normalized = array_reduce($exceptions, function($exceptions, $row) {
      // Handle special case where exception date is nested one level deep,
      // as in ACF.
      $row_exceptions = is_array($row) ? array_values($row) : [$row];
      return array_merge($exceptions, $row_exceptions);
    }, []);

    // Align each exception time component to that of start, at the appropriate
    // granularity based on frequencty, so that RRULE can properly parse
    // exception times.
    $aligned = array_map(function($exception) use ($event, $rules) {
      return $this->align_time($exception, $event['start'], $rules['frequency']);
    }, $normalized);

    // Filter out empty values, which could have been returned by parsing a
    // badly formatted date string.
    return array_filter($aligned);
  }

  /**
   * Align start time of datetime $dt to that of $start based on $freq.
   *
   * @param string $dt the dateime string to align
   * @param string $start a datetime string to align to
   * @param string $freq one of:
   * - "SECONDLY"
   * - "MINUTELY"
   * - "HOURLY"
   * - "DAILY"
   * - "WEEKLY"
   * - "MONTHLY"
   * - "YEARLY"
   * @return DateTimeImmutable|false
   */
  public function align_time(string $dt, string $start, string $freq) {
    // Align time component to various granularities, based on $freq
    $alignments = [
      'SECONDLY' => ['Y-m-d H:i:s', ''], // keep $dt as-is
      'MINUTELY' => ['Y-m-d H:i', ':s'], // align seconds
      'HOURLY'   => ['Y-m-d H', ':i:s'], // align minutes
      'DAILY'    => ['Y-m-d ', 'H:i:s'], // align hours
      'WEEKLY'   => ['Y-m-d ', 'H:i:s'], // align hours
      'MONTHLY'  => ['Y-m', '-d H:i:s'], // align day
      'YEARLY'   => ['Y', '-m-d H:i:s'], // align month
    ];

    // Keep as-is by default.
    [$dt_fmt, $start_fmt] = $alignments[strtoupper($freq)] ?? $alignments['SECONDLY'];

    // Note: for parsing datetimes, we're failing over to 0 to avoid warnings.
    // If we get an invalid date as a result, we'll filter it out later.
    $dt_str    = gmdate($dt_fmt, strtotime($dt) ?: 0);
    $start_str = gmdate($start_fmt, strtotime($start) ?: 0);
    return date_create_immutable($dt_str . $start_str);
  }
}
