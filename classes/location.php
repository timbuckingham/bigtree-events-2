<?php
	namespace BTXEvents;
	use SQL;

	/*
		Class: BTXEvents\Location
			Provides an interface for handling event locations.
	*/

	class Location extends BaseObject {

		public $City = "";
		public $Country = "";
		public $ID = -1;
		public $Position = 0;
		public $Route = "";
		public $State = "";
		public $Street = "";
		public $Title = "";
		public $ZipCode = "";

		/*
			Constructor:
				Builds a Location object referencing an existing btx_events_locations entry.

			Parameters:
				location - The ID of the location or a location array
				on_fail - An optional callable to call on non-existant or bad data (rather than triggering an error).
		*/

		function __construct($location = null, ?callable $on_fail = null)
		{
			if (is_null($location)) {
				return;
			}

			if (!is_array($location)) {
				$location = SQL::fetch("SELECT * FROM btx_events_locations WHERE id = ?", $location);
			}

			if (!$location) {
				if (is_null($on_fail)) {
					trigger_error("Location does not exist.", E_USER_ERROR);
				} else {
					call_user_func($on_fail);

					return;
				}
			}

			$this->City = $location["city"];
			$this->Country = $location["country"];
			$this->ID = $location["id"];
			$this->Position = $location["position"];
			$this->Route = $location["route"];
			$this->State = $location["state"];
			$this->Street = $location["street"];
			$this->Title = $location["title"];
			$this->ZipCode = $location["zip"];
		}

		/*
			Function: allInUse
				Returns an array of BTXEvents\Location objects that are used by events.

			Parameters:
				sort - The sort order of the locations (defaults to positioned).

			Returns:
				An array of locations.
		*/

		public static function allInUse(string $sort = "position DESC, id ASC"): array
		{
			$locations = SQL::fetchAll("SELECT btx_events_locations.* FROM btx_events_locations JOIN btx_events_events
										ON btx_events_locations.id = btx_events_events.location
										GROUP BY btx_events_locations.id ORDER BY $sort");

			foreach ($locations as $index => $location) {
				$locations[$index] = new Location($location);
			}

			return $locations;
		}

		/*
			Function: getByRoute
				Returns a location for the given route.

			Parameters:
				route - The location route.

			Returns:
				A location entry.
		*/

		public static function getByRoute(string $route): ?Location
		{
			$location = SQL::fetch("SELECT * FROM btx_events_locations WHERE route = ?", $route);

			return $location ? new Location($location) : null;
		}

	}
