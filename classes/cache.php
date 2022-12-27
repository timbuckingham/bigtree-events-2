<?php
	namespace BTXEvents;
	use BigTreeCMS, SQL;

	/*
		Class: BTXEvents\Cache
			Provides an interface for managing the recurrence cache of events.
	*/

	class Cache {

		private static $Days = [
			"Sunday",
			"Monday",
			"Tuesday",
			"Wednesday",
			"Thursday",
			"Friday",
			"Saturday"
		];

		private static $Ordinals = [
			"1" => "First",
			"2" => "Second",
			"3" => "Third",
			"4" => "Fourth",
			"5" => "Fifth"
		];

		/*
			Function: getNextRecurrence
				Finds the next time an event exists.

			Parameters:
				type - The recurrence type.
				rule - The recurrence rule.
				time - The start time to begin looking from (in seconds since Unix epoch).

			Returns:
				The next occurence of the event in seconds since Unix epoch.
		*/

		public static function getNextRecurrence(?string $type, $rule, ?int $time = null)
		{
			if (!$time) {
				$time = time();
			}

			if (!$type) {
				return false;
			}

			// Daily Recurrence
			if ($type == "daily") {
				return $time;
			}

			// Weekly / Biweekly Recurrence
			if ($type == "weekly" || $type == "biweekly") {
				$current_day_of_week = date("w", $time);

				if (in_array($current_day_of_week, $rule)) {
					return $time;
				}

				return static::getNextRecurrence($type, $rule, strtotime(date("Y-m-d", $time)." +1 day"));
			}

			// Monthly Recurrence
			if ($type == "monthly") {
				// If the detail is numeric, it's simply the (x)th day of the month.
				if (is_numeric($rule)) {
					$current_day_of_month = date("j", $time);

					// Move to the next month if this month's has passed
					if ($current_day_of_month > $rule) {
						return strtotime(date("Y-m-$rule", strtotime(date("Y-m-1", $time)." +1 month")));
					} else {
						return strtotime(date("Y-m-$rule", $time));
					}

					// We need to calculate a more crazy date like the second Thursday of each month.
				} else {
					$next = strtotime(static::$Ordinals[$rule["week"]]." ".static::$Days[$rule["day"]]." of ".date("F Y", $time));

					if ($next > $time) {
						return $next;
					}

					return strtotime(static::$Ordinals[$rule["week"]]." ".static::$Days[$rule["day"]]." of ".date("F Y", strtotime(date("Y-m-1", $time)." +1 month")));
				}
			}

			// Yearly Recurrence
			if ($type == "yearly") {
				$next = strtotime(date("Y", $time)."-".$rule);

				if ($next < $time) {
					$next = strtotime((date("Y", $time) + 1)."-".$rule);
				}

				return $next;
			}

			return false;
		}

		/*
			Function: getTimes
				Returns the start and end timestamps for an event.

			Parameters:
				item - Event array.
				start_date - The date of the occurence.
				end_date - The end date of the occurence.

			Returns:
				An array of timestamps (first being start, second being end)
		*/

		public static function getTimes(array $item, string $start_date, ?string $end_date): array
		{
			// If they didn't enter an end date, we're going to assume it ends the same day it starts
			if ($end_date == "0000-00-00" || !$end_date) {
				$end_date = $start_date;
			}

			// If it's an all day event or we don't know the start time, set the end time to 11:59
			if ($item["all_day"] || !$item["start_time"]) {
				$start_date = strtotime($start_date." 00:00:00");
				$end_date = strtotime($end_date." 23:59:59");
			} else {
				$start_date = strtotime($start_date." ".$item["start_time"]);

				// If we have an end time, let's see if it's actually the next day.
				if ($item["end_time"]) {
					if (strtotime($item["start_time"]) < strtotime($item["end_time"]) && $start_date == $end_date) {
						$end_date = strtotime($start_date." ".$item["end_time"]." +1 day");
					} else {
						$end_date = strtotime($end_date." ".$item["end_time"]);
					}
				} else {
					$end_date = strtotime($end_date." 23:59:59");
				}
			}

			$start_date = date("Y-m-d H:i:s", $start_date);
			$end_date = date("Y-m-d H:i:s", $end_date);

			return [$start_date, $end_date];
		}

		protected static function insertRecord(int $id, string $title_route, string $start, string $end,
											   ?string $end_date, ?string $end_time, bool $all_day, ?int $rule = null,
											   bool $canceled = false)
		{
			$date_route = date("Y-m-d", strtotime($start));
			$time_route = date("Hi", strtotime($start));
			$route = $title_route."-".$date_route."-".$time_route;
			$rule = $rule ?: null;
			$end_date = $end_date ?: null;
			$end_time = $end_time ?: null;
			$all_day = $all_day ? "on" : "";

			if ($canceled) {
				SQL::insert("btx_events_date_cache_canceled", [
					"event" => $id,
					"start" => $start,
					"end" => $end,
					"all_day" => $all_day,
					"date" => $date_route,
					"rule" => $rule
				]);
			} else {
				SQL::insert("btx_events_date_cache", [
					"event" => $id,
					"start" => $start,
					"end" => $end,
					"title_route" => $title_route,
					"date_route" => $date_route,
					"route" => $route,
					"end_date" => $end_date,
					"end_time" => $end_time,
					"all_day" => $all_day,
					"rule" => $rule
				]);
			}
		}

		/*
			Function: processEvent
				Caches an event.

			Parameters:
				id - The id of the event to cache.
		*/

		public static function processEvent(int $id): void
		{
			// Delete existing cache
			SQL::delete("btx_events_date_cache", ["event" => $id]);
			SQL::delete("btx_events_date_cache_canceled", ["event" => $id]);

			// Get defaults
			$event = SQL::fetch("SELECT * FROM btx_events_events WHERE id = ?", $id);

			// Cache initial date
			if ($event["start_date"]) {
				[$start_date, $end_date] = static::getTimes($event, $event["start_date"], $event["end_date"]);
				static::insertRecord($event["id"], $event["route"], $start_date, $end_date, $event["end_date"], $event["end_time"], $event["all_day"]);
			}

			// Run recurrence rules
			$recurrence_rules = SQL::fetchAll("SELECT * FROM btx_events_recurrence_rules
											   WHERE event = ? AND (recurring_end_date IS NULL OR recurring_end_date > CURDATE())", $id);

			foreach ($recurrence_rules as $recurrence_rule) {
				$type = $recurrence_rule["type"];
				$rule = json_decode($recurrence_rule["rule"], true);

				// Get parent event information if the rule doesn't specify
				if ($recurrence_rule["all_day"]) {
					$recurrence_rule["start_time"] = "";
					$recurrence_rule["end_time"] = "";
				} else {
					if (!$recurrence_rule["start_time"]) {
						$recurrence_rule["start_time"] = $event["start_time"];
						$recurrence_rule["end_time"] = $event["end_time"];
						$recurrence_rule["all_day"] = $event["all_day"];
					}
				}

				if ($type == "specific") {
					[$start_date, $end_date] = static::getTimes($recurrence_rule, $recurrence_rule["start_date"], $recurrence_rule["end_date"]);
					static::insertRecord($event["id"], $event["route"], $start_date, $end_date, $recurrence_rule["end_date"], $recurrence_rule["end_time"], $recurrence_rule["all_day"], $recurrence_rule["id"]);
				} else {
					// If there's a start date to the recurrence, use it
					if ($recurrence_rule["start_date"]) {
						$start = strtotime($recurrence_rule["start_date"]);
					} else {
						$start = strtotime($event["start_date"]);
					}

					if ($recurrence_rule["type"] == "daily" && date("Y-m-d", $start) == $event["start_date"]) {
						$start += 24 * 60 * 60;
					}

					// If there's an end date, stop there
					if ($recurrence_rule["recurring_end_date"]) {
						$end = strtotime($recurrence_rule["recurring_end_date"]);
					} else {
						$end = strtotime("+2 years");
					}

					// If we've already passed the end date, we don't need to cache things anymore.
					if ($end < $start) {
						return;
					}

					// Put together a list of skip weeks for bi-weekly recurrences
					if ($recurrence_rule["type"] == "biweekly") {
						$skip_weeks = [];
						$skip_start = $start;

						while ($skip_start <= $end) {
							$skip_start = strtotime(date("Y-m-d", $skip_start)." +1 week");
							$week = date("W", $skip_start);

							if (date("w", $skip_start) == 0) {
								$week++;

								if (date("W", strtotime(date("Y-m-d", $skip_start)." +1 day")) == 1) {
									$week = 1;
								}
							}

							$skip_weeks[] = $week;
							$skip_start = strtotime(date("Y-m-d", $skip_start)." +1 week");
						}
					}

					// Get a list of the canceled recurrences
					$canceled = array_filter((array) json_decode($recurrence_rule["cancellations"], true));
					$x = 0;
					$current_week = null;

					while ($start <= $end) {
						$x++;
						$next = static::getNextRecurrence($recurrence_rule["type"], $rule, $start);

						// The next time the event occurs could fall outside our caching period
						if ($next <= $end) {
							[$start_date, $end_date] = static::getTimes($recurrence_rule, date("Y-m-d", $next), date("Y-m-d", $next));
							$date_route = date("Y-m-d", strtotime($start_date));
							$skip = false;

							if ($recurrence_rule["type"] == "biweekly") {
								$time = strtotime($start_date);
								$week = date("W", $time);

								if (date("w", $time) == 0) {
									$week++;

									if (date("W", strtotime(date("Y-m-d", $time)." +1 day")) == 1) {
										$week = 1;
									}
								}

								if (in_array($week, $skip_weeks)) {
									$skip = true;
								}
							}

							if (!$skip) {
								if (in_array($date_route, $canceled)) {
									static::insertRecord($event["id"], $event["route"], $start_date, $end_date, null, null, $recurrence_rule["all_day"], $recurrence_rule["id"], true);
								} else {
									static::insertRecord($event["id"], $event["route"], $start_date, $end_date, null, $recurrence_rule["end_time"], $recurrence_rule["all_day"], $recurrence_rule["id"]);
								}
							}
						}

						$start = strtotime(date("Y-m-d", $next)." +1 day");
					}
				}
			}
		}

	}
