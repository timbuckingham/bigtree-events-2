<?php
	namespace BTXEvents;

	/*
		Class: BTXEvents\DateRange
			Provides an interface for handling date ranges in queries.
	*/

	class DateRange extends BaseObject {

		public $EndDate;
		public $StartDate;

		/*
			Constructor:
				Builds a DateRange object.

			Parameters:
				start_date - A date in a format strtotime understands (or null for NOW)
				end_date - A date in a format strtotime understands (or null for no end date)
		*/

		function __construct(?string $start_date, ?string $end_date)
		{
			$this->StartDate = date("Y-m-d H:i:s", $start_date ? strtotime($start_date) : time());
			$this->EndDate = $end_date ? date("Y-m-d 23:59:59", strtotime($end_date)) : null;
		}

	}
