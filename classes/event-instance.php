<?php
	namespace BTXEvents;
	use SQL;

	/*
		Class: BTXEvents\EventInstance
			Provides an interface for handling event instances.
	*/

	class EventInstance extends Event {

		protected $DateRoute;
		protected $Instance;
		protected $End;
		protected $Start;
		protected $TitleRoute;

		/*
			Constructor:
				Builds an EventInstance object.

			Parameters:
				instance - An event instance array or ID
		*/

		function __construct($instance)
		{
			if (!is_array($instance)) {
				$instance_data = SQL::fetch("SELECT * FROM btx_events_date_cache WHERE id = ?", $instance);

				if ($instance_data) {
					$instance = SQL::fetch("SELECT * FROM btx_events_events WHERE id = ?", $instance_data["event"]);
					$instance["instance"] = $instance_data["id"];
					$instance["start"] = $instance_data["start"];
					$instance["end"] = $instance_data["end"];
					$instance["title_route"] = $instance_data["title_route"];
					$instance["date_route"] = $instance_data["date_route"];
				}
			}

			if (!is_array($instance)) {
				trigger_error("Event does not exist.", E_USER_ERROR);
			}

			parent::__construct($instance);

			$this->DateRoute = $instance["date_route"];
			$this->Instance = $instance["instance"];
			$this->End = $instance["end"];
			$this->Start = $instance["start"];
			$this->TitleRoute = $instance["title_route"];
		}

		/*
			Function: get
				Returns an instance of an event (combined date cache and event entry).

			Parameters:
				id - The id of the event instance.

			Returns:
				An event array with its fields decoded.
		*/

		public static function get(int $id): ?EventInstance
		{
			$instance = SQL::fetch("SELECT events.*, cache.start, cache.end, cache.id as instance,
										   cache.title_route AS title_route, cache.date_route AS date_route
									FROM btx_events_events AS `events` JOIN btx_events_date_cache AS `cache`
									WHERE cache.event = events.id AND cache.id = ?", $id);

			if (!$instance) {
				return null;
			}

			return new EventInstance($instance);
		}

		/*
			Function: getByRoute
				Returns an event for the given route.

			Parameters:
				title_route - The event title route.
				date_route - The instance date route.

			Returns:
				An event instance.
		*/

		public static function getInstanceByRoute(string $title_route, string $date_route): ?EventInstance
		{
			$instance = SQL::fetch("SELECT events.*, cache.start, cache.end, cache.id as instance,
										   cache.title_route AS title_route, cache.date_route AS date_route
									FROM btx_events_events AS `events` JOIN btx_events_date_cache AS `cache`
									WHERE cache.event = events.id AND title_route = ? AND date_route = ?",
								   $title_route, $date_route);

			if (!$instance) {
				return null;
			}

			return new EventInstance($instance);
		}

	}
