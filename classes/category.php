<?php
	namespace BTXEvents;
	use SQL;

	/*
		Class: BTXEvents\Category
			Provides an interface for handling event categories.
	*/

	class Category extends BaseObject {

		public $ID = -1;
		public $Parent = 0;
		public $Position = 0;
		public $Route = "";
		public $Title = "";

		/*
			Constructor:
				Builds a Category object referencing an existing btx_events_categories entry.

			Parameters:
				category - The ID of the category or a category array
				on_fail - An optional callable to call on non-existant or bad data (rather than triggering an error).
		*/

		function __construct($category = null, ?callable $on_fail = null)
		{
			if (is_null($category)) {
				return;
			}

			if (!is_array($category)) {
				$category = SQL::fetch("SELECT * FROM btx_events_categories WHERE id = ?", $category);
			}

			if (!$category) {
				if (is_null($on_fail)) {
					trigger_error("Category does not exist.", E_USER_ERROR);
				} else {
					call_user_func($on_fail);

					return;
				}
			}

			$this->ID = $category["id"];
			$this->Parent = $category["parent"];
			$this->Position = $category["position"];
			$this->Route = $category["route"];
			$this->Title = $category["title"];
		}

		/*
			Function: allByParent
				Returns an array of BTXEvents\Category objects for a given parent.

			Parameters:
				parent - The parent ID to check.
				sort - The sort order of the categories (defaults to positioned).

			Returns:
				An array of categories.
		*/

		public static function allByParent(?int $parent = null, string $sort = "position DESC, id ASC"): array
		{
			if (!$parent) {
				$categories = SQL::fetchAll("SELECT * FROM btx_events_categories WHERE (parent IS NULL OR parent = 0) ORDER BY $sort");
			} else {
				$categories = SQL::fetchAll("SELECT * FROM btx_events_categories WHERE parent = ? ORDER BY $sort", $parent);
			}

			foreach ($categories as $index => $category) {
				$categories[$index] = new Category($category);
			}

			return $categories;
		}

		/*
			Function: allInUse
				Returns an array of top level BTXEvents\Category objects that are used by events.

			Parameters:
				sort - The sort order of the categories (defaults to positioned).

			Returns:
				An array of categories.
		*/

		public static function allInUse(string $sort = "position DESC, id ASC"): array
		{
			$categories = SQL::fetchAll("SELECT btx_events_categories.* FROM btx_events_categories JOIN btx_events_event_categories
										 ON btx_events_event_categories.category = btx_events_categories.id
										 WHERE (parent = 0 OR parent IS NULL)
										 GROUP BY btx_events_categories.id ORDER BY $sort");

			foreach ($categories as $index => $category) {
				$categories[$index] = new Category($category);
			}

			return $categories;
		}

		/*
			Function: getByRoute
				Returns a category for the given route.

			Parameters:
				route - The category route.

			Returns:
				A category entry.
		*/

		public static function getByRoute(string $route): ?Category
		{
			$category = SQL::fetch("SELECT * FROM btx_events_categories WHERE route = ?", $route);

			return $category ? new Category($category) : null;
		}

		/*
			Function: getLineage
				Returns an array of the ancestors of this category.

			Returns:
				An array of BTXEvents\Category objects starting with the "oldest".
		*/

		public function getLineage(): array
		{
			$ancestors = [];
			$parent = $this->Parent;

			while ($parent) {
				$category = new Category($parent, function() {
					trigger_error("An ancestor of this category no longer exists.", E_USER_ERROR);
				});
				$ancestors[] = $category;
				$parent = $category->Parent;
			}

			return array_reverse($ancestors);
		}

	}
