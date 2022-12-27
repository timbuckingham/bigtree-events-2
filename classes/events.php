<?php
	
	use BTXEvents\Cache;
	use BTXEvents\Category;
	use BTXEvents\Event;
	use BTXEvents\EventInstance;
	use BTXEvents\DateRange;
	use BTXEvents\Helpers;
	use BTXEvents\Location;
	use BTXEvents\Query;
	
	/*
		Class: BTXEvents
			A class to handle events in BigTree.
	*/
	
	include "base-object.php";
	include "cache.php";
	include "category.php";
	include "date-range.php";
	include "event.php";
	include "event-instance.php";
	include "helpers.php";
	include "location.php";
	include "query.php";
	
	class BTXEvents {
		
		public static $Days = [
			"Sunday",
			"Monday",
			"Tuesday",
			"Wednesday",
			"Thursday",
			"Friday",
			"Saturday"
		];
		
		public static $Months = [
			"01" => "January",
			"02" => "February",
			"03" => "March",
			"04" => "April",
			"05" => "May",
			"06" => "June",
			"07" => "July",
			"08" => "August",
			"09" => "September",
			"10" => "October",
			"11" => "November",
			"12" => "December"
		];
		
		/*
			Constructor:
				Re-caches stale events.
		*/
		
		public function __construct() {
			$stale_event_ids = SQL::fetchAllSingle("SELECT id FROM btx_events_events WHERE last_updated < ?", date("Y-m-d", strtotime("-1 year")));
			
			foreach ($stale_event_ids as $id) {
				Cache::processEvent($id);
				SQL::update("btx_events_events", $id, ["last_updated" => "NOW()"]);
			}
		}
		
		/*
			Function: getBreadcrumb
				Used by templates to bring in breadcrumbs.
		*/
		
		public function getBreadcrumb($page): array {
			$link = WWW_ROOT.$page["path"]."/";
			
			global $event;
			
			if (defined("EVENT_DETAIL")) {
				return [[
					"title" => $event["title"],
					"link" => $link."event/".$event["title_route"]."/".$event["date_route"]."/"
				]];
			}
			
			return [];
		}
		
		/*
			Function: parseManyToMany
				Helper function for the admin to create sensible category tagging.
		*/
		
		public static function parseManyToMany($list, $everything = false) {
			if (!$everything) {
				return $list;
			}
			
			$parsed = [];
			
			foreach ($list as $id => $title) {
				$category = new BTXEvents\Category($id);
				$ancestors = $category->getLineage();
				$path = [];
				
				foreach ($ancestors as $ancestor) {
					$path[] = $ancestor->Title;
				}
				
				$path[] = $title;
				$parsed[$id] = implode(" » ", $path);
			}
			
			asort($parsed);
			
			return $parsed;
		}
		
		/*
			Function: publishHook
				Used by the BigTree form to cache the event on publish.
		*/
		
		public static function publishHook($table, $id, $changes, $many_to_many, $tags) {
			BTXEvents\Cache::processEvent($id);
		}
		
		////////////
		///
		/// Backwards Compatibility Methods
		///
		////////////
		
		public static function formattedDate($item, $date_format = "F j, Y", $time_format = "g:ia") {
			return Helpers::getFormattedDate($item, $date_format, $time_format);
		}
		
		public static function formattedTime($item, $time_format = "gi:a") {
			return Helpers::getFormattedTime($item, $time_format);
		}
		
		public static function get($item) {
			$event = new Event(is_array($item) ? $item["id"] : $item);
			
			return $event->Array;
		}
		
		public static function getCategories($sort = "position DESC, id ASC") {
			return static::getCategoriesByParent(0, $sort);
		}
		
		public static function getCategoriesByParent($parent = false, $sort = "position DESC, id ASC") {
			$categories = Category::allByParent($parent, $sort);
			$array_categories = [];
			
			foreach ($categories as $item) {
				$array_categories[] = $item->Array;
			}
			
			return $array_categories;
		}
		
		public static function getCategory($id) {
			$category = new Category($id);
			
			return $category->Array;
		}
		
		public static function getCategoryByRoute($route) {
			$category = Category::getByRoute($route);
			
			return $category->Array;
		}
		
		public static function getCategoryLineage($category, $ancestors = []) {
			$category = new Category($category);
			$lineage = $category->getLineage();
			$array_lineage = [];
			
			foreach ($lineage as $item) {
				$array_lineage[] = $item->Array;
			}
			
			return $array_lineage;
		}
		
		public static function getEventCategories($event) {
			$event = new Event(is_array($event) ? $event["id"] : $event);
			
			return $event->getCategories(true);
		}
		
		public static function getEventCategoryIDs($event) {
			$event = is_array($event) ? $event["id"] : $event;
			
			return SQL::fetchAllSingle("SELECT category FROM btx_events_event_categories WHERE event = ?", $event);
		}
		
		public static function getEventCategoryList($original_list = [], $parent = 0, $level = "") {
			return Helpers::getNestedCategoryList();
		}
		
		public static function getEventsByDate($date, $featured = false) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getEventsByDateInCategories($date, $categories, $featured = false) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getEventsByDateInCategoriesWithSubcategories($date, $categories, $featured = false) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategoryWithSubcategories($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getEventsByDateInCategory($date, $category, $featured = false) {
			$date_range = new DateRange($date, $date);
			$category = new Category($category);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->setCategory($category)
				->run()
				->Results;
		}
		
		public static function getEventsByDateInCategoryWithSubcategories($date, $category, $featured = false) {
			$date_range = new DateRange($date, $date);
			$category = new Category($category);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->setCategoryWithSubcategories($category)
				->run()
				->Results;
		}
		
		public static function getEventsByDateRange($start_date, $end_date, $featured = false) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getEventsByDateRangeInCategories($start_date, $end_date, $categories, $featured = false) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getEventsByDateRangeInCategoriesWithSubcategories($start_date, $end_date, $categories, $featured = false) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategoryWithSubcategories($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getEventsByDateRangeInCategory($start_date, $end_date, $category, $featured = false) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			$category = new Category($category);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->setCategory($category)
				->run()
				->Results;
		}
		
		public static function getEventsByDateRangeInCategoryWithSubcategories($start_date, $end_date, $category, $featured = false) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			$category = new Category($category);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->setCategoryWithSubcategories($category)
				->run()
				->Results;
		}
		
		public static function getFeaturedEventsByDate($date) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			
			return $event_query->setOnlyFeatured()
				->run()
				->Results;
		}
		
		public static function getFeaturedEventsByDateRange($start_date, $end_date) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			return $event_query->setOnlyFeatured()
				->run()
				->Results;
		}
		
		public static function getFeaturedEventsByDateRangeInCategories($start_date, $end_date, $categories) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->setOnlyFeatured()
				->run()
				->Results;
		}
		
		public static function getFeaturedEventsByDateRangeInCategoriesWithSubcategories($start_date, $end_date, $categories) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategoryWithSubcategories($category);
			}
			
			return $event_query->setOnlyFeatured()
				->run()
				->Results;
		}
		
		public static function getFeaturedSearchResultsInDateRange($query, $start_date, $end_date) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			return $event_query->setOnlyFeatured()
				->setSearchString($query, true)
				->run()
				->Results;
		}
		
		public static function getInstance($id) {
			$instance = EventInstance::get($id);
			
			return $instance->Array;
		}
		
		public static function getInstanceByRoute($title_route, $date_route) {
			$instance = EventInstance::getInstanceByRoute($title_route, $date_route);
			
			return $instance->Array;
		}
		
		public static function getKeyedDateRangeForEvents($events) {
			return Helpers::getKeyedDateRangeForEvents($events);
		}
		
		public static function getEventLocation($item) {
			$mod = new BigTreeModule("btx_events_locations");
			
			return $mod->get($item);
		}
		
		public static function getEventLocationByRoute($route) {
			return static::getEventLocation(SQL::fetch("SELECT * FROM btx_events_locations WHERE route = ?", $route));
		}
		
		public static function getEventLocations($sort = "position DESC, id ASC", $in_use = false) {
			if (!$in_use) {
				$locations = SQL::fetchAll("SELECT * FROM btx_events_locations ORDER BY $sort");
			} else {
				$locations = SQL::fetchAll("SELECT * FROM btx_events_locations
											WHERE id IN (SELECT DISTINCT(location) FROM btx_events_events)
											ORDER BY $sort");
			}
			
			foreach ($locations as $index => $location) {
				$locations[$index] = static::getEventLocation($location);
			}
			
			return $locations;
		}
		
		public static function getNumberOfEventsOnDate($date) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			
			return $event_query->run()->Count;
		}
		
		public static function getPageCountOfUpcomingEvents($per_page) {
			$event_query = new Query(null, 1, $per_page);
			
			return $event_query->run()->PageCount;
		}
		
		public static function getPageCountOfUpcomingEventsInCategories($categories, $per_page = 10) {
			$event_query = new Query(null, 1, $per_page);
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->PageCount;
		}
		
		public static function getPageCountOfUpcomingEventsInCategory($category, $per_page = 10) {
			$event_query = new Query(null, 1, $per_page);
			$category = new Category($category);
			
			return $event_query->setCategory($category)->run()->PageCount;
		}
		
		public static function getPageOfUpcomingEvents($page = 1, $per_page = 10) {
			$event_query = new Query(null, $page, $per_page);
			
			return $event_query->run()->Results;
		}
		
		public static function getPageOfUpcomingEventsInCategory($category, $page = 1, $per_page = 10) {
			$event_query = new Query(null, $page, $per_page);
			$category = new Category($category);
			
			return $event_query->setCategory($category)->run()->Results;
		}
		
		public static function getPageOfUpcomingEventsInCategories($categories, $page = 1, $per_page = 10) {
			$event_query = new Query(null, $page, $per_page);
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getPageOfUpcomingSearchResults($query, $page = 1, $per_page = 10) {
			$event_query = new Query(null, $page, $per_page);
			
			return $event_query->setSearchString($query, true)->run()->Results;
		}
		
		public static function getRandomEvent() {
			$event_query = new Query(null, 1, 0);
			$events = $event_query->run()->Results;
			
			shuffle($events);
			
			return $events[0];
		}
		
		public static function getRandomEventByDate($date) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			$events = $event_query->run()->Results;
			
			shuffle($events);
			
			return $events[0];
		}
		
		public static function getRandomFeaturedEvent() {
			$event_query = new Query(null, 1, 0);
			$event_query->setOnlyFeatured();
			$events = $event_query->run()->Results;
			
			shuffle($events);
			
			return $events[0];
		}
		
		public static function getRandomFeaturedEventByDate($date) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 0);
			$event_query->setOnlyFeatured();
			$events = $event_query->run()->Results;
			
			shuffle($events);
			
			return $events[0];
		}
		
		public static function getSearchResultsInDateRange($query, $start_date, $end_date, $featured = false) {
			$date_range = new DateRange($start_date, $end_date);
			$event_query = new Query($date_range, 1, 0);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->setSearchString($query, true)->run()->Results;
		}
		
		public static function getSingleEventByDate($date, $featured = false) {
			$date_range = new DateRange($date, $date);
			$event_query = new Query($date_range, 1, 1);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			[$event] = $event_query->run()->Results;
			
			return $event;
		}
		
		public static function getSingleFeaturedEventByDate($date) {
			return static::getSingleEventByDate($date, true);
		}
		
		public static function getSubcategoriesOfCategory($category) {
			$category_id = is_array($category) ? $category["id"] : $category;
			$categories = [];
			$query = SQL::query("SELECT * FROM btx_events_categories WHERE parent = ?", $category_id);
			
			while ($category = $query->fetch()) {
				$categories[] = $category;
				$categories = array_merge($categories, static::getSubcategoriesOfCategory($category));
			}
			
			return $categories;
		}
		
		public static function getTotalUpcomingEvents() {
			$event_query = new Query;
			
			return $event_query->run()->Count;
		}
		
		public static function getTotalUpcomingEventsInCategory($category) {
			$event_query = new Query;
			$category = new Category($category);
			
			return $event_query->setCategory($category)->run()->Count;
		}
		
		public static function getTotalUpcomingEventsInCategories($categories) {
			$event_query = new Query;
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->Count;
		}
		
		public static function getTotalUpcomingSearchResults($query) {
			$event_query = new Query;
			
			return $event_query->setSearchString($query, true)
				->run()
				->Count;
		}
		
		public static function getUpcomingEventInstances($event) {
			$event = new Event(is_array($event) ? $event["id"] : $event);
			
			return $event->getInstances(true);
		}
		
		public static function getUpcomingEvents($limit = 5, $featured = false, $page = 1) {
			$event_query = new Query(null, $page, $limit);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getUpcomingEventsPageCount($per_page = 5) {
			$event_query = new Query(null, 1, $per_page);
			
			return $event_query->run()->PageCount;
		}
		
		public static function getUpcomingFeaturedEvents($limit = 5, $page = 1) {
			$event_query = new Query(null, $page, $limit);
			$event_query->setOnlyFeatured();
			
			return $event_query->run()->Results;
		}
		
		public static function getUpcomingEventsInCategories($limit = 5, $categories = [], $featured = false, $page = 1) {
			$event_query = new Query(null, $page, $limit);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getUpcomingEventsInCategoriesWithSubcategories($limit, $categories = [], $featured = false, $page = 1) {
			$event_query = new Query(null, $page, $limit);
			
			if ($featured) {
				$event_query->setOnlyFeatured();
			}
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategoryWithSubcategories($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getUpcomingFeaturedEventsInCategories($limit = 5, $categories = [], $page = 1) {
			$event_query = new Query(null, $page, $limit);
			$event_query->setOnlyFeatured();
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategory($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getUpcomingFeaturedEventsInCategoriesWithSubcategories($limit = 5, $categories = [], $page = 1) {
			$event_query = new Query(null, $page, $limit);
			$event_query->setOnlyFeatured();
			
			foreach ($categories as $category) {
				$category = new Category($category);
				$event_query->setCategoryWithSubcategories($category);
			}
			
			return $event_query->run()->Results;
		}
		
		public static function getUpcomingSearchResults($query, $limit = 5) {
			$event_query = new Query(null, 1, $limit);
			
			return $event_query->setSearchString($query, true)
				->run()
				->Results;
		}
		
		public static function getUpcomingFeaturedSearchResults($query, $limit = 5) {
			$event_query = new Query(null, 1, $limit);
			
			return $event_query->setOnlyFeatured()
				->setSearchString($query, true)
				->run()
				->Results;
		}
		
		public static function searchResults($query) {
			$date_range = new DateRange;
			$date_range->StartDate = null;
			
			$event_query = new Query($date_range, 1, 0);
			$event_query->setSearchString($query, true);
			$event_query->run();
			
			return $event_query->Results;
		}
		
		public static function searchResultsInCategory($query, $category) {
			$category = new Category($category);
			$date_range = new DateRange;
			$date_range->StartDate = null;
			
			$event_query = new Query($date_range, 1, 0);
			$event_query->setSearchString($query, true);
			$event_query->setCategory($category);
			$event_query->run();
			
			return $event_query->Results;
		}
	}
