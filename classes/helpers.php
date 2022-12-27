<?php
	namespace BTXEvents;
	use SQL;

	/*
		Class: BTXEvents\Helpers
			Provides an interface of static helper methods for events.
	*/

	class Helpers {

		/*
			Function: getFormattedDate
				Returns a string of formatted date/time based on an event having a start/end date and times.

			Parameters:
				item - The event instance array.
				date_format - The date format (compatible with PHP's date function)
				time_format - The time format (compatible with PHP's time format)

			Returns:
				A date/time string.
		*/

		public static function getFormattedDate(array $item, string $date_format = "F j, Y",
												string $time_format = "g:ia"): string
		{
			$s = strtotime($item["start"]);
			$e = strtotime($item["end"]);

			// If it's a single all day event
			if ($item["all_day"]) {
				if (date("Y-m-d", $s) == date("Y-m-d", $e)) {
					return date($date_format, $s);
				} else {
					return date($date_format, $s)." - ".date($date_format, $e);
				}
			} else {
				// Single day event
				if (date("Y-m-d", $s) == date("Y-m-d", $e)) {
					if ($s != $e && $item["end_time"] != "") {
						return date($date_format, $s)." &mdash; ".date($time_format, $s)." - ".date($time_format, $e);
					} else {
						return date($date_format, $s)." &mdash; ".date($time_format, $s);
					}
					// Multi day event
				} else {
					// Starts one night, ends next morning?
					if (date("H", $s) > date("H", $e)) {
						return date($date_format, $s)." &mdash; ".date($time_format, $s)." - ".date($time_format, $e);
						// Probably meant an event to be on multiple days for a few hours each day.
					} else {
						return date($date_format, $s)." - ".date($date_format, $e)." &mdash; ".date($time_format, $s)." - ".date($time_format, $e);
					}
				}
			}
		}

		/*
			Function: getFormattedTime
				Returns a string of formatted time based on an event having start/end times.

			Parameters:
				item - The event instance array.
				time_format - The time format (compatible with PHP's time format)

			Returns:
				A date/time string.
		*/

		public static function getFormattedTime(array $item, string $time_format = "gi:a"): string
		{
			$s = strtotime($item["start"]);
			$e = strtotime($item["end"]);

			if ($item["all_day"]) {
				return "All Day";
			}

			if ($s != $e && $item["end_time"] != "") {
				return date($time_format, $s)." - ".date($time_format, $e);
			} else {
				return date($time_format, $s);
			}
		}

		/*
			Function: getKeyedEventsDateRangeForEvents
				Returns an array of days as keys with the events that fall in each day as an array.

			Parameters:
				events - An array of event instances.

			Returns:
				A keyed array (dates are keys, array of events are vals) for the events passed in.
		*/

		public static function getKeyedDateRangeForEvents(array $events): array
		{
			$days = [];

			foreach ($events as $event) {
				$days[date("Y-m-d", strtotime($event->Start))][] = $event;
			}

			return $days;
		}

		/*
			Function: getNestedCategoryList
				Returns a nested category list.
		*/

		public static function getNestedCategoryList(int $parent = 0, string $level = ""): array
		{
			$list = [];
			$categories = SQL::fetchAll("SELECT * FROM btx_events_categories WHERE parent = ? ORDER BY title", $parent);

			foreach ($categories as $category) {
				$list[$category["id"]] = $level.$category["name"];
				$list = $list + static::getNestedCategoryList($category["id"], trim($level)."--- ");
			}

			return $list;
		}

	}
