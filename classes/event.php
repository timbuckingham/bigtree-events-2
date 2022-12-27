<?php
	namespace BTXEvents;
	use BigTree, SQL;

	/*
		Class: BTXEvents\Event
			Provides an interface for handling events.
	*/

	class Event extends BaseObject {

		public $AdditionalFields = [];
		public $AllDay;
		public $Blurb;
		public $Content;
		public $EndDate;
		public $EndTime;
		public $Featured;
		public $Image;
		public $ImageAltText;
		public $Link;
		public $Location;
		public $StartDate;
		public $StartTime;
		public $Title;

		protected $ID;
		protected $LastUpdated;
		protected $Route;

		/*
			Constructor:
				Builds an Event object referencing an existing btx_events_events entry.

			Parameters:
				event - The ID of the event or an event array
				on_fail - An optional callable to call on non-existant or bad data (rather than triggering an error).
		*/

		function __construct($event = null, ?callable $on_fail = null)
		{
			if (is_null($event)) {
				return;
			}

			if (!is_array($event)) {
				$event = SQL::fetch("SELECT * FROM btx_events_events WHERE id = ?", $event);
			}

			if (!$event) {
				if (is_null($on_fail)) {
					trigger_error("Event does not exist.", E_USER_ERROR);
				} else {
					call_user_func($on_fail);

					return;
				}
			}

			$core_fields = [
				"id",
				"all_day",
				"blurb",
				"content",
				"date_route",
				"end",
				"end_date",
				"end_time",
				"featured",
				"image",
				"image_alt_text",
				"instance",
				"last_updated",
				"link",
				"location",
				"route",
				"start",
				"start_date",
				"start_time",
				"title",
				"title_route"
			];

			// Decode fields
			foreach ($event as $key => $value) {
				$array_value = @json_decode($value, true);

				if (is_array($array_value)) {
					$event[$key] = $array_value;
				}
			}

			$event = BigTree::untranslateArray($event);

			// Process non-native fields into a catch all property
			foreach ($event as $key => $val) {
				if (!in_array($key, $core_fields)) {
					$this->AdditionalFields[$key] = $val;
				}
			}

			$this->AllDay = !empty($event["all_day"]);
			$this->Blurb = $event["blurb"];
			$this->Content = $event["content"];
			$this->EndDate = $event["end_date"];
			$this->EndTime = $event["end_time"];
			$this->Featured = !empty($event["featured"]);
			$this->ID = $event["id"];
			$this->Image = $event["image"];
			$this->ImageAltText = $event["image_alt"];
			$this->LastUpdated = $event["last_updated"];
			$this->Link = $event["link"];
			$this->Location = $event["location"] ? new Location($event["location"]) : null;
			$this->Route = $event["route"];
			$this->StartDate = $event["start_date"];
			$this->StartTime = $event["start_time"];
			$this->Title = $event["title"];
		}

		/*
			Function: getArray
				Returns an array representation of the event.
				Can also be called as the property "Array"
		*/

		public function getArray() {
			$raw_properties = get_object_vars($this);
			$changed_properties = [];

			foreach ($raw_properties as $key => $value) {
				$changed_properties[$this->_camelCaseToUnderscore($key)] = $value;
			}

			unset($changed_properties["additional_fields"]);

			foreach ($this->AdditionalFields as $key => $value) {
				$changed_properties[$key] = $value;
			}

			$changed_properties["location"] = $this->Location ? $this->Location->ID : null;

			return $changed_properties;
		}

		/*
			Function: getByRoute
				Returns an event for the given route.

			Parameters:
				route - The event route.

			Returns:
				An event entry.
		*/

		public static function getByRoute(string $route): ?Event
		{
			$event = SQL::fetch("SELECT * FROM btx_events_events WHERE route = ?", $route);

			return $event ? new Event($event) : null;
		}

		/*
			Function: getCategories
				Returns an array of categories that this event is attached to.

			Parameters:
				as_arrays - Whether to return arrays rather than  Category objects (defaults to false)

			Returns:
				An array of Category objects.
		*/

		public function getCategories(bool $as_arrays = false): array
		{
			$categories = [];
			$data = SQL::fetchAll("SELECT btx_events_categories.*
								   FROM btx_events_categories JOIN btx_events_event_categories
								   ON btx_events_categories.id = btx_events_event_categories.category
								   WHERE btx_events_event_categories.event = ?", $this->ID);

			if ($as_arrays) {
				return $data;
			}

			foreach ($data as $item) {
				$categories[] = new Category($item);
			}

			return $categories;
		}

		/*
			Function: getInstances
				Returns instances of this event.

			Parameters:
				upcoming - Whether to return only upcoming instances (defaults to true)

			Returns:
				An array of EventInstance objects.
		*/

		public function getInstances($upcoming = true): array
		{
			$instances = [];
			$event = $this->Array;
			$upcoming = $upcoming ? " AND end >= NOW()" : "";
			$instance_data = SQL::fetchAll("SELECT id AS instance, start, end, title_route, date_route
											FROM btx_events_date_cache
											WHERE event = ? $upcoming
											ORDER BY start ASC", $this->ID);

			foreach ($instance_data as $instance) {
				$instances[] = new EventInstance(array_merge($event, $instance));
			}

			return $instances;
		}

	}
