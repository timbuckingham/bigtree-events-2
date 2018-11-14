<?php
	$admin->verifyCSRFToken();
	
	$rule = SQL::fetch("SELECT * FROM btx_events_recurrence_rules WHERE id = ?", $_POST["id"]);
	$event = BTXEvents::get($rule["event"]);
	$permission_level = $admin->getAccessLevel($bigtree["module"], $event, "btx_events_events");

	if ($permission_level != "p") {
		$admin->stop("Access denied.");
	}

	$id = $_POST["id"];
	$type = $_POST["type"];
	$rule = $_POST["rule"];
	$start_time = !empty($_POST["start_time"]) ? date("H:i:s", strtotime($_POST["start_time"])) : null;
	$end_time = !empty($_POST["end_time"]) ? date("H:i:s", strtotime($_POST["end_time"])) : null;
	$recurring_end_date = !empty($_POST["recurring_end_date"]) ? date("Y-m-d", strtotime($_POST["recurring_end_date"])) : null;
	
	if ($_POST["all_day"]) {
		$all_day = "on";
		$start_time = null;
		$end_time = null;
	} else {
		$all_day = "";
	}
	
	if ($type == "specific") {
		$recurring_end_date = null;
		$rule = "";
	} elseif ($type == "monthly") {
		if (!is_numeric($rule)) {
			list(, $week, $day) = explode("#", $rule);
			$rule = ["week" => $week, "day" => $day];
		}
	}
	
	$rule = BigTree::json($rule);

	SQL::update("btx_events_recurrence_rules", $id, [
		"type" => $type,
		"rule" => $rule,
		"recurring_end_date" => $recurring_end_date,
		"start_date" => !empty($_POST["start_date"]) ? date("Y-m-d", strtotime($_POST["start_date"])) : null,
		"end_date" => !empty($_POST["end_date"]) ? date("Y-m-d", strtotime($_POST["end_date"])) : null,
		"start_time" => $start_time,
		"end_time" => $end_time,
		"all_day" => $all_day
	]);

	$admin->growl("Events", "Updated Recurrence Rule");

	BTXEvents::recacheEvent($_POST["event"]);
	
	if ($_POST["return"] == "recurrences") {
		BigTree::redirect(MODULE_ROOT."recurrences/".$_POST["event"]."/");
	} else {
		BigTree::redirect(MODULE_ROOT."rules/".$_POST["event"]."/");
	}
