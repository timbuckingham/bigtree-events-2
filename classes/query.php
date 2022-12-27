<?php
	namespace BTXEvents;
	use BigTree, BigTreeModule, BigTreeCMS, SQL;

	/*
		Class: BTXEvents\Query
			Provides an interface for querying events.
	*/

	class Query {

		private $Categories = [];
		private $DateRange = null;
		private $Location = null;
		private $OnlyFeatured = false;
		private $Page = 1;
		private $PerPage = 10;
		private $SearchFields = ["title", "blurb"];
		private $SearchSplit = false;
		private $SearchString = "";
		private $Where = [];

		public $Count = 0;
		public $PageCount = 0;
		public $Results = [];

		/*
			Constructor:
				Builds a Query object.

			Parameters:
				date_range - A DateRange object or null for all upcoming events.
				page - Which page to return (defaults to first page).
				per_page - How many results to return per page (defaults to 10, set to < 1 to return all)

		*/

		function __construct(?DateRange $date_range = null, int $page = 1, int $per_page = 10)
		{
			$this->DateRange = $date_range;
			$this->Page = $page;
			$this->PerPage = $per_page;
		}

		/*
			Function: run
				Runs the query and sets the Results, Count, and PageCount parameters.

			Parameters:
				results_as_array - If you want regular arrays instead of Event objects in ->Results set this to true (defaults false)
		*/

		public function run($results_as_array = false): Query
		{
			$tables = [
				"btx_events_events AS `events`",
				"btx_events_date_cache AS `cache`"
			];

			$select_fields = [
				"events.*",
				"cache.start",
				"cache.end",
				"cache.id AS instance",
				"cache.title_route",
				"cache.date_route"
			];

			// Date Range Filter
			$where = $this->Where;
			$where[] = "cache.event = events.id";

			if ($this->DateRange) {
				$where[] = "cache.start >= '".$this->DateRange->StartDate."'";
				$where[] = "cache.end <= '".$this->DateRange->EndDate."'";
			} else {
				$where[] = "cache.end >= NOW()";
			}

			// Featured Filter
			if ($this->OnlyFeatured) {
				$where[] = "events.featured = 'on'";
			}

			// Search filter
			if ($this->SearchString !== "" && count($this->SearchFields)) {
				if ($this->SearchSplit) {
					$search_terms = explode(" ", $this->SearchString);
				} else {
					$search_terms = [$this->SearchString];
				}

				// Bring in relationship tables for locations and categories if needed
				if (in_array("location", $this->SearchFields)) {
					$tables[] = "btx_events_locations AS `locations`";
					$where[] = "events.location = locations.id";
				}

				foreach ($search_terms as $term) {
					$term = SQL::escape($term);
					$search_where = [];

					foreach ($this->SearchFields as $field) {
						if ($field == "location") {
							$search_where[] = "(locations.title LIKE '%$term%' OR locations.street LIKE '%$term%' OR
												locations.city LIKE '%$term%' OR locations.zip LIKE '%$term%' OR
												locations.country LIKE '%$term%')";
						} else {
							$search_where[] = "events.`$field` LIKE '%$term%'";
						}
					}

					$where[] = "(".implode(" OR ", $search_where).")";
				}
			}

			// Location filter
			if ($this->Location) {
				$where[] = "events.location = '".SQL::escape($this->Location->ID)."'";
			}

			// Category filter
			if (count($this->Categories)) {
				$tables[] = "btx_events_event_categories AS `categories_rel`";
				$category_where = [];

				foreach ($this->Categories as $category) {
					$category_where[] = "categories_rel.category = '".$category->ID."'";
				}

				$where[] = "categories_rel.event = events.id";
				$where[] = "(".implode(" OR ", $category_where).")";
			}

			// Pagination
			$limit = "";

			if ($this->PerPage) {
				$limit = "LIMIT ".(($this->Page - 1) * $this->PerPage).", ".$this->PerPage;
			}

			$count = SQL::fetchSingle("SELECT COUNT(DISTINCT(cache.id)) FROM ".implode(" JOIN ", $tables)."
									   WHERE ".implode(" AND ", $where));
			$events = SQL::fetchAll("SELECT ".implode(", ", $select_fields)."
									 FROM ".implode(" JOIN ", $tables)."
									 WHERE ".implode(" AND ", $where)."
									 GROUP BY instance
									 ORDER BY cache.start $limit");

			// For returning array results
			$mod = new BigTreeModule;

			foreach ($events as $event) {
				if ($results_as_array) {
					$this->Results[] = $mod->get($event);
				} else {
					$this->Results[] = new EventInstance($event);
				}
			}

			$this->Count = $count;
			
			if ($this->PerPage) {
				$this->PageCount = ceil($count / $this->PerPage) ?: 1;
			} else {
				$this->PageCount = 1;
			}

			return $this;
		}

		/*
			Function: setCategory
				Adds a filter to this query to only return events in this category.
				Can be called multiple times to allow for OR relationships.

			Parameters:
				category - A Category object.
		*/

		public function setCategory(Category $category): Query
		{
			$this->Categories[$category->ID] = $category;

			return $this;
		}

		/*
			Function: setCategoryWithSubcategories
				Adds a filter to this query to only return events in this category and the categories beneath it.
				Can be called multiple times to allow for OR relationships.

			Parameters:
				category - A Category object.
		*/

		public function setCategoryWithSubcategories(Category $category): Query
		{
			$this->Categories[$category->ID] = $category;
			$child_categories = Category::allByParent($category->ID);

			foreach ($child_categories as $child) {
				static::setCategoryWithSubcategories($child);
			}

			return $this;
		}

		/*
			Function: setDateRange
				Adds a date range to this query to only return events in the given date range.

			Parameters:
				date_range - A DateRange object.
		*/

		public function setDateRange(DateRange $date_range): Query
		{
			$this->DateRange = $date_range;

			return $this;
		}

		/*
			Function: setLocation
				Adds a location filter to the query to only return events at a given location.

			Parameters:
				location - A Location object.
		*/

		public function setLocation(Location $location): Query
		{
			$this->Location = $location;

			return $this;
		}

		/*
			Function: setOnlyFeatured
				Sets the query to only return featured events.
		*/

		public function setOnlyFeatured(): Query
		{
			$this->OnlyFeatured = true;

			return $this;
		}


		/*
			Function: setPage
				Sets the page of results to return.

			Parameters:
				page - An integer.
		*/

		public function setPage(int $page): Query
		{
			$this->Page = $page;

			return $this;
		}

		/*
			Function: setPerPage
				Sets the number of results to return for each page.

			Parameters:
				per_page - An integer.
		*/

		public function setPerPage(int $per_page): Query
		{
			$this->PerPage = $per_page;

			return $this;
		}

		/*
			Function: setSearchString
				Adds a filter to this query to only return results that match the search string.

				Optional search fields (in addition to columns in the btx_events_events table):
				- location - Searches against the btx_events_locations table fields for associated location

				Both of the above search fields cause joins that could slow down your query.

			Parameters:
				query - The search string
				split - Whether to split the string into words and search each word individually (defaults to false)
				fields - An array of fields to search against. Defaults to ["title", "blurb"]
		*/

		public function setSearchString(string $query, bool $split = false, array $fields = ["title", "blurb"]): Query
		{
			$this->SearchString = trim($query);
			$this->SearchSplit = $split;
			$this->SearchFields = array_unique($fields);

			return $this;
		}

		/*
			Function: setWhere
				Adds an additional SQL "WHERE" condition to the query.
				In the query, the events table is "events" and the cache table is "cache".
				The provided condition will be added via AND relationship with other queries.
			
			Parameters:
				where - A SQL WHERE parameter (e.g. "`image` != ''")
		*/

		public function setWhere(string $where): Query
		{
			$this->Where[] = "(".ltrim(rtrim(trim($where), ")"), "(").")";

			return $this;
		}

	}
