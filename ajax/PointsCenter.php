<?php
require_once __DIR__ . "/datastoreVars.php";
include_once __DIR__ . "/swift/swift_required.php";

class PointsCenter
{
	private static $qtr = 0;

	private static $dbConn = null;

	public function __construct ($qtr)
	{
		error_reporting(E_ALL & ~E_NOTICE);
		#ini_set('display_errors', '1');
		self::initializeConnection();

		if ($qtr) {
			self::$qtr = $qtr;
		} else {
			self::$qtr = $GLOBALS['QTR'];
		}
	}

	private static function initializeConnection ()
	{
		if (is_null(self::$dbConn)) {
            $dsn = $GLOBALS['DB_TYPE'] . ":host=" . $GLOBALS['DB_HOST'] . ";dbname=" . $GLOBALS['DB_NAME'];
            try {
                self::$dbConn = new PDO($dsn, $GLOBALS['DB_USER'], $GLOBALS['DB_PASS']);
            } catch (PDOException $e) {
                echo "Error: " . $e->getMessage();
                die();
            }

            self::$dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
	}

	public function getQuarter ()
	{
		return self::$qtr;
	}

	public function getQuarters ()
	{
		$quarters = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT qtr,quarter
				FROM quarters
				WHERE 1301<qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$quarters = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $quarters;
	}

	public function getQuarterInfo ()
	{
		$quarter_info;
		try {
			$statement = self::$dbConn->prepare(
				"SELECT *
				FROM quarters
				WHERE qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$quarter_info = $statement->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		$quarter_info['im_teams'] = json_decode($quarter_info['im_teams']);
		return $quarter_info;
	}

	public function getDirectory ()
	{
		$directory = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT first_name,last_name,year,major,suites.suite,photo
				FROM slivkans
				LEFT JOIN suites ON slivkans.nu_email=suites.nu_email AND suites.qtr=:qtr
				WHERE qtr_joined <= :qtr AND (qtr_final IS NULL OR qtr_final >= :qtr)
				ORDER BY first_name,last_name");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$directory = $statement->fetchAll(PDO::FETCH_NUM);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $directory;
	}

	public function getSlivkans ()
	{
		$slivkans = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT CONCAT(first_name, ' ', last_name) AS full_name,
					slivkans.nu_email,gender,wildcard,committees.committee,photo,suites.suite,year
				FROM slivkans
				LEFT JOIN committees ON slivkans.nu_email=committees.nu_email AND committees.qtr=:qtr
				LEFT JOIN suites ON slivkans.nu_email=suites.nu_email AND suites.qtr=:qtr
				WHERE qtr_joined <= :qtr AND (qtr_final IS NULL OR qtr_final >= :qtr)
				ORDER BY first_name,last_name");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$slivkans = $statement->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		# Add tokens for typeahead.js
		$n = count($slivkans);
		for($i=0; $i<$n; $i++){
			$slivkans[$i]["tokens"] = explode(" ",$slivkans[$i]["full_name"]);
		}

		return $slivkans;
	}

	public function getNicknames ()
	{
		$nicknames = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email,nickname
				FROM nicknames");
			$statement->execute();
			$nicknames = $statement->fetchAll(PDO::FETCH_NAMED);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $nicknames;
	}

	public function getFellows ()
	{
		$fellows = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT full_name,photo
				FROM fellows
				WHERE qtr_final IS NULL");
			$statement->execute();
			$fellows = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $fellows;
	}

	public function getEvents ($start,$end)
	{
		$events = array();

		if(!$start){
			$start = date('Y-m-d',mktime(0,0,0,date("m"),date("d")-14,date("Y")));
		}
		if(!$end){
			$end = '2050-01-01';
		}

		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name,date,type,attendees,committee,description
				FROM events
				WHERE qtr=:qtr AND date BETWEEN :start AND :end
				ORDER BY date, id");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":start", $start);
			$statement->bindValue(":end", $end);
			$statement->execute();
			$events = $statement->fetchAll(PDO::FETCH_NAMED);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $events;
	}

	public function getRecentEvents ($count = 20, $offset = 0)
	{
		$events = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name,date,type,attendees,committee,description
				FROM events
				WHERE qtr=:qtr
				ORDER BY date DESC, id DESC
				LIMIT :offset,:count");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":offset", $offset, PDO::PARAM_INT);
			$statement->bindValue(":count", $count, PDO::PARAM_INT);
			$statement->execute();
			$events = $statement->fetchAll(PDO::FETCH_NAMED);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return array_reverse($events);
	}

	public function getIMs ($team)
	{
		$IMs = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name
				FROM events
				WHERE qtr=:qtr AND type='im' AND event_name LIKE :team");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":team", "%".$team."%");
			$statement->execute();
			$IMs = $statement->fetchAll(PDO::FETCH_COLUMN,0);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $IMs;
	}

	public function getPoints ()
	{
		$points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name,nu_email
				FROM points
				WHERE qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$points = $statement->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $points;
	}

	public function getHelperPoints ()
	{
		$helper_points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name,nu_email
				FROM helperpoints
				WHERE qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$helper_points = $statement->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $helper_points;
	}

	public function getCommitteeAttendance ()
	{
		$committee_attendance = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name,nu_email
				FROM committeeattendance
				WHERE qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$committee_attendance = $statement->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $committee_attendance;
	}

	public function getEventTotals ()
	{
		$points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email, count(points.event_name) AS total
				FROM points INNER JOIN events
				ON points.event_name=events.event_name
				WHERE events.type<>'im' AND events.qtr=:qtr
				GROUP BY nu_email");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$points = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $points;
	}

	public function getIMPoints ()
	{
		$im_points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email, LEAST(SUM(count),15) AS total
				FROM imcounts
				WHERE count >= 3 AND qtr=:qtr
				GROUP BY nu_email");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$im_points = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $im_points;
	}

	public function getSlivkanPoints ($nu_email)
	{
		$events = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT event_name
				FROM points
				WHERE qtr=:qtr AND nu_email=:nu_email");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":nu_email", $nu_email);
			$statement->execute();
			$events = $statement->fetchAll(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		return $events;
	}

	public function getSlivkanPointsByCommittee ($nu_email)
	{
		$points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT committee, count(nu_email) AS count
				FROM points LEFT JOIN events
					ON points.event_name=events.event_name
					WHERE nu_email=:nu_email AND type<>'im' AND points.qtr=:qtr
				GROUP BY events.committee");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":nu_email", $nu_email);
			$statement->execute();
			$points = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $points;
	}

	public function getSlivkanIMPoints ($nu_email)
	{
		$im_points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT sport, count
				FROM imcounts
				WHERE nu_email=:nu_email AND qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":nu_email", $nu_email);
			$statement->execute();
			$im_points = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $im_points;
	}

	public function getSlivkanBonusPoints ($nu_email)
	{
		$helper_points = 0;
		try {
			$statement = self::$dbConn->prepare(
				"SELECT count
				FROM helperpointcounts
				WHERE nu_email=:nu_email AND qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":nu_email", $nu_email);
			$statement->execute();
			$helper_points = $statement->fetch(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		if(!$helper_points){ $helper_points = 0; }

		$bonus = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT *
				FROM bonuspoints
				WHERE nu_email=:nu_email AND qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":nu_email", $nu_email);
			$statement->execute();
			$bonus = $statement->fetch(PDO::FETCH_NAMED);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		if($bonus){
			$helper_points += $bonus['helper'];
			$committee_points = $bonus['committee'];
			$other_points = $bonus['other1']+$bonus['other2']+$bonus['other3'];
		}else{
			$committee_points = 0;
			$other_points = 0;
		}

		$other_breakdown = array(
			array($bonus['other1_name'], $bonus['other1']),
			array($bonus['other2_name'], $bonus['other2']),
			array($bonus['other3_name'], $bonus['other3']));


		return array("helper" => $helper_points, "committee" => $committee_points, "other" => $other_points, "other_breakdown" => $other_breakdown);
	}

	public function getBonusPoints ()
	{
		$bonus_points = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT bonuspoints.nu_email, IFNULL(helperpointcounts.count,0)+bonuspoints.helper AS helper,
					committee, other1+other2+other3 AS other
				FROM bonuspoints
				LEFT OUTER JOIN helperpointcounts
					USING (nu_email,qtr)
					WHERE qtr=:qtr");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$bonus_points = $statement->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		foreach(array_keys($bonus_points) AS $s){
			$bonus_points[$s] = $bonus_points[$s][0];
		}

		return $bonus_points;
	}

	public function getPointsTable ($showall = false)
	{
		$slivkans = self::getSlivkans();
		if($showall){
			$quarter_info = self::getQuarterInfo();
			$events = self::getEvents($quarter_info['start_date'],$quarter_info['end_date']);
		}else{
			$events = self::getRecentEvents();
		}
		$points = self::getPoints();
		$event_totals = self::getEventTotals();
		$im_points = self::getIMPoints();
		$bonus_points = self::getBonusPoints();

		$helperpoints = self::getHelperPoints();
		$committeeattendance = self::getCommitteeAttendance();


		$points_table = array(); #table that is slivkan count by event count + 6

		# statistics holders:
		$totals_by_year = array();
		$totals_by_suite = array();

		#form points_table
		$events_count = count($events);
		$total_ind				= $events_count + 7;

		for($s=0; $s < count($slivkans); $s++){
			$nu_email = $slivkans[$s]['nu_email'];

			$subtotal = $event_totals[$nu_email] + $bonus_points[$nu_email]['helper'] + $im_points[$nu_email];
			$total = $subtotal + $bonus_points[$nu_email]['committee'] + $bonus_points[$nu_email]['other'];

			$totals_by_year[$slivkans[$s]['year']][] = $subtotal;
			$totals_by_suite[$slivkans[$s]['suite']][] = $subtotal;

			$points_table[$nu_email] = array_merge(
				array($slivkans[$s]['full_name'], $slivkans[$s]['gender']),
				array_fill(0, $events_count, 0),
				array((int) $event_totals[$nu_email], (int) $bonus_points[$nu_email]['helper'],
					(int) $im_points[$nu_email], (int) $bonus_points[$nu_email]['committee'],
					(int) $bonus_points[$nu_email]['other'], $total));
		}

		for($e=0; $e < $events_count; $e++){
			$event_name = $events[$e]['event_name'];
			$is_im = $events[$e]['type'] == "im";

			foreach($points[$event_name] as $s){
				$points_table[$s][2+$e] = 1;
			}

			if(!$is_im){
				if(array_key_exists($event_name, $helperpoints)){
					foreach($helperpoints[$event_name] as $s){
						$points_table[$s][2+$e] += 0.1;
					}
				}

				if(array_key_exists($event_name, $committeeattendance)){
					foreach($committeeattendance[$event_name] as $s){
						$points_table[$s][2+$e] += 0.2;
					}
				}
			}
		}

		foreach($totals_by_year as $y => $totals){
			$by_year[] = array($y, round(array_sum($totals)/count($totals), 2));
		}

		foreach($totals_by_suite as $s => $totals){
			$by_suite[] = array($s, round(array_sum($totals)/count($totals), 2));
		}

		return array('points_table' => array_values($points_table), 'events' => $events, 'by_year' => $by_year, 'by_suite' => $by_suite);
	}

	public function getMultipliers ()
	{
		$slivkans = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT CONCAT(first_name,' ',last_name) AS full_name,
					nu_email,gender,qtr_joined,qtrs_away,qtr_final
				FROM slivkans
				WHERE qtr_final IS NULL OR qtr_final >= :qtr
				ORDER BY first_name, last_name");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$slivkans = $statement->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}
		$count = count($slivkans);

		for($s=0; $s<$count; $s++){
			$y_join = round($slivkans[$s]['qtr_joined'],-2);
			$q_join = $slivkans[$s]['qtr_joined'] - $y_join;

			$y_this = round(self::$qtr,-2);
			$q_this = self::$qtr - $y_this;

			$y_acc = ($y_this - $y_join) / 100;
			$q_acc = $q_this - $q_join;

			$q_total = $q_acc + 3 * $y_acc - $slivkans[$s]['qtrs_away'];

			$mult = 1 + 0.1 * $q_total;

			$slivkans[$s]['mult'] = $mult;
		}

		return $slivkans;
	}

	public function getRankings ()
	{
		$is_housing = $GLOBALS['IS_HOUSING'] == true;
		# figure out how many qtrs to consider
		# if its spring, you're trying to get final housing rankings.
		$y_this = round(self::$qtr,-2);
		$q_this = self::$qtr - $y_this;

		if($q_this == 2 && !$is_housing){
			$qtrs = array(self::$qtr);
		}else if($q_this == 3){
			$qtrs = array(self::$qtr-1, self::$qtr);
		}else if($q_this == 1 || $is_housing){
			$qtrs = array($y_this-100+2, $y_this-100+3, $y_this+1);
		}else{
			echo "Error: qtr messed up. " . $q_this;
			die();
		}

		# spring 13, fall 13, winter 14 HOUSING spring 14
		$abstentions = self::getAbstentions();
		$rankings = self::getMultipliers();
		$totals = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email,total
				FROM totals
				WHERE qtr IN (".implode(",",$qtrs).")
				ORDER BY qtr");
			$statement->execute();
			$totals = $statement->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		$house_meetings;

		try {
			$statement = self::$dbConn->prepare(
				"SELECT count(type)
				FROM events
				WHERE qtr IN (".implode(",",$qtrs).") AND type='house_meeting'");
			$statement->execute();
			$house_meetings = $statement->fetch(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		$mult_count = count($rankings);
		$qtrs_count = count($qtrs);
		for($i=0; $i<$mult_count; $i++){
			$sum = 0;
			for($j=0; $j<$qtrs_count; $j++){
				$t = (int) $totals[$rankings[$i]['nu_email']][$j] OR 0;
				$rankings[$i][$qtrs[$j]] = $t;
				$sum += $t;
			}

			# give multiplier for current qtr if it isnt housing
			if (!$is_housing) {
				$rankings[$i]['mult'] += 0.1;
			}

			$rankings[$i]['total'] = $sum;
			$rankings[$i]['total_w_mult'] = $sum * $rankings[$i]['mult'];
			$rankings[$i]['abstains'] = in_array($rankings[$i]['nu_email'], $abstentions) || $rankings[$i]['total_w_mult'] < $house_meetings;
		}

		return array('rankings' => $rankings, 'qtrs' => $qtrs, 'males' => $GLOBALS['HOUSING_MALES'], 'females' => $GLOBALS['HOUSING_FEMALES']);
	}

	public function updateTotals ()
	{
		$slivkans = self::getSlivkans();
		$event_totals = self::getEventTotals();
		$im_points = self::getIMPoints();
		$bonus_points = self::getBonusPoints();

		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO totals (nu_email, total, qtr)
				VALUES (?,?,?)
				ON DUPLICATE KEY UPDATE total=VALUES(total)");

			for($s=0; $s < count($slivkans); $s++){
				$nu_email = $slivkans[$s]['nu_email'];
				$total = $event_totals[$nu_email] + $im_points[$nu_email] + array_sum($bonus_points[$nu_email]);

				$statement->execute(array($nu_email, $total, self::$qtr));
			}
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return true;
	}

	public function getAbstentions ()
	{
		$q = self::$qtr - round(self::$qtr,-2);

		#round up to closest "YY02"
		if ($q == 3) {
			$qtr_final = round(self::$qtr,-2) + 100 + 2;
		} else {
			$qtr_final = round(self::$qtr,-2) + 2;
		}

		$abstentions = array();

		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email
				FROM slivkans
				WHERE qtr_final>=:qtr AND qtr_final<=:qtr_final");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":qtr_final", $qtr_final);
			$statement->execute();
			$abstentions = $statement->fetchAll(PDO::FETCH_COLUMN);

		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $abstentions;
	}

	public function getCommittee ($committee)
	{
		$slivkans = array();

		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email
				FROM committees
				WHERE committee=:committee AND qtr=:qtr");
			$statement->bindValue(":committee", $committee);
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();

			$slivkans = $statement->fetchAll(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $slivkans;
	}

	public function updateCommittee ($slivkans, $committee)
	{
		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO committees (nu_email, committee, qtr) VALUES (?,?,?)
				ON DUPLICATE KEY UPDATE committee=VALUES(committee)");

			for($s=0; $s < count($slivkans); $s++){
				$statement->execute(array($slivkans[$s], $committee, self::$qtr));
			}
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return true;
	}

	public function getSuite ($suite)
	{
		$slivkans = array();

		try {
			$statement = self::$dbConn->prepare(
				"SELECT nu_email
				FROM suites
				WHERE suite=:suite AND qtr=:qtr");
			$statement->bindValue(":suite", $suite);
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();

			$slivkans = $statement->fetchAll(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $slivkans;
	}

	public function updateSuite ($slivkans, $suite)
	{
		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO suites (nu_email, suite, qtr) VALUES (?,?,?)
				ON DUPLICATE KEY UPDATE suite=VALUES(suite)");

			for($s=0; $s < count($slivkans); $s++){
				$statement->execute(array($slivkans[$s], $suite, self::$qtr));
			}
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return true;
	}

	public function submitPointsForm ($get)
	{
		$real_event_name = $get['event_name'] . " " . $get['date'];

		if($get['helper_points'] === NULL){ $get['helper_points'] = array(""); }
		if($get['committee_members'] === NULL){ $get['committee_members'] = array(""); }
		if($get['fellows'] === NULL){ $get['fellows'] = array(""); }

		# Begin PDO Transaction
		self::$dbConn->beginTransaction();

		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO `pointsform` SET
				date=:date, type=:type, committee=:committee, event_name=:event_name, description=:description,
				filled_by=:filled_by, comments=:comments, attendees=:attendees, helper_points=:helper_points,
				committee_members=:committee_members, fellows=:fellows");
			$statement->bindValue(":date", $get['date']);
			$statement->bindValue(":type", $get['type']);
			$statement->bindValue(":committee", $get['committee']);
			$statement->bindValue(":event_name", $get['event_name']);
			$statement->bindValue(":description", $get['description']);
			$statement->bindValue(":filled_by", $get['filled_by']);
			$statement->bindValue(":comments", $get['comments']);
			$statement->bindValue(":attendees", implode(", ",$get['attendees']));
			$statement->bindValue(":helper_points", implode(", ",$get['helper_points']));
			$statement->bindValue(":committee_members", implode(", ",$get['committee_members']));
			$statement->bindValue(":fellows", implode(", ",$get['fellows']));

			$statement->execute();
		} catch (PDOException $e) {
			echo json_encode(array("error" => $e->getMessage(), "step" => "1"));
			self::$dbConn->rollBack();
			die();
		}

		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO events (event_name,date,qtr,filled_by,committee,description,type,attendees)
				VALUES (:event_name, :date, :qtr, :filled_by, :committee, :description, :type, :attendees)");
			$statement->bindValue(":event_name", $real_event_name);
			$statement->bindValue(":date", $get['date']);
			$statement->bindValue(":qtr", self::$qtr);
			$statement->bindValue(":filled_by", $get['filled_by']);
			$statement->bindValue(":committee", $get['committee']);
			$statement->bindValue(":description", $get['description']);
			$statement->bindValue(":type", $get['type']);
			$statement->bindValue(":attendees", count($get['attendees']));

			$statement->execute();
		} catch (PDOException $e) {
			echo json_encode(array("error" => $e->getMessage(), "step" => "2"));
			self::$dbConn->rollBack();
			die();
		}

		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO points (nu_email, event_name, qtr)
				VALUES (?,?,?)");

			foreach($get['attendees'] as $a){
				$statement->execute(array($a,$real_event_name,self::$qtr));
			}
		} catch (PDOException $e) {
			echo json_encode(array("error" => $e->getMessage(), "step" => "3"));
			self::$dbConn->rollBack();
			die();
		}

		if ($get['helper_points'][0] != ""){
			try {
				$statement = self::$dbConn->prepare(
					"INSERT INTO helperpoints (nu_email, event_name, qtr)
					VALUES (?,?,?)");

				foreach($get['helper_points'] as $h){
					$statement->execute(array($h,$real_event_name,self::$qtr));
				}
			} catch (PDOException $e) {
				echo json_encode(array("error" => $e->getMessage(), "step" => "4"));
				self::$dbConn->rollBack();
				die();
			}
		}

		if ($get['committee_members'][0] != ""){
			try {
				$statement = self::$dbConn->prepare(
					"INSERT INTO committeeattendance (nu_email, event_name, qtr)
					VALUES (?,?,?)");

				foreach($get['committee_members'] as $c){
					$statement->execute(array($c,$real_event_name,self::$qtr));
				}
			} catch (PDOException $e) {
				echo json_encode(array("error" => $e->getMessage(), "step" => "5"));
				self::$dbConn->rollBack();
				die();
			}
		}

		if ($get['fellows'][0] != ""){
			try {
				$statement = self::$dbConn->prepare(
					"INSERT INTO fellowattendance (full_name, event_name, qtr)
					VALUES (?,?,?)");

				foreach($get['fellows'] as $f){
					$statement->execute(array($f,$real_event_name,self::$qtr));
				}
			} catch (PDOException $e) {
				echo json_encode(array("error" => $e->getMessage(), "step" => "6"));
				self::$dbConn->rollBack();
				die();
			}
		}

		#email VP a notification?
		if($GLOBALS['VP_EMAIL_POINT_SUBMISSION_NOTIFICATIONS']){
			$html = "<table border=\"1\">";

			foreach($get as $key => $value){
				$html .= "<tr><td style=\"text-align:right;\">";

				if(is_array($value)){
					$html .=  $key . "</td><td>" . implode(", ", $value);
				}else{
					$html .= $key . "</td><td>" . $value;
				}

				$html .= "</td></tr>\n";
			}

			$html .= "</table>";

			self::sendEmail(null, "Points Submitted for " . $real_event_name, $html);
		}

		return self::$dbConn->commit();
	}

	public function submitPointsCorrectionForm($get,$key){

		try {
			$statement = self::$dbConn->prepare(
				"SELECT *
				FROM points
				WHERE event_name=:event_name AND nu_email=:nu_email");
			$statement->bindValue(":event_name", $get['event_name']);
			$statement->bindValue(":nu_email", $get['sender_email']);

			$statement->execute();
			if($statement->rowCount() > 0){
				echo json_encode(array("message" => "You already have points for that event!"));
				die();
			}

		} catch (PDOException $e) {
			echo json_encode(array("message" => "Error: " . $e->getMessage()));
			die();
		}

		try {
			$statement = self::$dbConn->prepare(
				"SELECT filled_by
				FROM events
				WHERE event_name=:event_name");
			$statement->bindValue(":event_name", $get['event_name']);
			$statement->execute();

			$filled_by = $statement->fetch(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			echo json_encode(array("message" => "Error: " . $e->getMessage()));
			die();
		}

		if($get['comments'] === NULL){ $get['comments'] = ""; }

		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO pointscorrection (message_key,nu_email,event_name,comments)
				VALUES (:message_key, :nu_email, :event_name, :comments)");
			$statement->bindValue(":message_key", $key);
			$statement->bindValue(":nu_email", $get['sender_email']);
			$statement->bindValue(":event_name", $get['event_name']);
			$statement->bindValue(":comments", $get['comments']);

			$statement->execute();
		} catch (PDOException $e) {
			echo json_encode(array("message" => "Error: Maybe you are trying to submit the same correction twice."));
			die();
		}

		$enc1 = md5('1');
		$enc2 = md5('2');
		$enc3 = md5('3');

		$html = "<h2>Slivka Points Correction</h2>
		<h3>Automated Email</h3>
		<p style=\"padding: 10; width: 70%\">" . $get['name'] . " has submitted a points correction for the
		event, " . $get['event_name'] . ", for which you took points. Please click one of the following links
		to respond to this request. Please do so within 2 days of receiving this email.</p>
		<p style=\"padding: 10; width: 70%\">" . $get['name'] . "'s comment: " . $get['comments'] . "</p>
		<ul>
			<li><a href=\"http://slivka.northwestern.edu/points/ajax/pointsCorrectionReply.php?key=$key&reply=$enc1\">" . $get['name'] . " was at " . $get['event_name'] . "</a></li><br/>
			<li><a href=\"http://slivka.northwestern.edu/points/ajax/pointsCorrectionReply.php?key=$key&reply=$enc2\">" . $get['name'] . " was NOT at " . $get['event_name'] . "</a></li><br/>
			<li><a href=\"http://slivka.northwestern.edu/points/ajax/pointsCorrectionReply.php?key=$key&reply=$enc3\">Not sure</a></li>
		</ul>

		<p style=\"padding: 10; width: 70%\">If you received this email in error, please contact " . $GLOBALS['VP_EMAIL'] . "</p>";

		return self::sendEmail($filled_by,"Slivka Points Correction (Automated)",$html);
	}

	public function pointsCorrectionReply($get)
	{
		if($get['reply']==md5('1')){ $code = 1; }
		elseif($get['reply']==md5('2')){ $code = 2;}
		elseif($get['reply']==md5('3')){ $code = 3;}
		else{die("Error in decoding");}

		try {
			$statement = self::$dbConn->prepare(
				"SELECT *
				FROM pointscorrection
				WHERE message_key=:key");
			$statement->bindValue(":key", $get['key']);
			$statement->execute();
			$result = $statement->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		if($result['response'] == "0"){
			try {
				$statement = self::$dbConn->prepare(
					"UPDATE pointscorrection
					SET response=:code
					WHERE message_key=:key");
				$statement->bindValue(":code", $code);
				$statement->bindValue(":key", $get['key']);
				$statement->execute();
			} catch (PDOException $e) {
				echo "Error: " . $e->getMessage();
				die();
			}

		}else{
			echo "You already responded to this request.";
			die();
		}

		if($code == 1){
			try {
				$statement = self::$dbConn->prepare(
					"INSERT INTO points (nu_email,event_name,qtr)
					VALUES (:nu_email,:event_name,:qtr)");
				$statement->bindValue(":nu_email", $result['nu_email']);
				$statement->bindValue(":event_name", $result['event_name']);
				$statement->bindValue(":qtr", self::$qtr);
				$statement->execute();
			} catch (PDOException $e) {
				echo "Error: " . $e->getMessage();
				die();
			}

			echo "Success! She/He was given a point for the event.";
			$html_snippet = "You were given a point for " . $result['event_name'] . ".";
		}elseif($code == 2){
			echo "Success! A point has NOT been given.";
			$html_snippet = "You were NOT given a point for " . $result['event_name'] . ". You can request an explanation through the VP.";
		}elseif($code == 3){
			echo "Success! The VP will consult another attendee of the event.";
			$html_snippet = "The points taker couldn't remember if you were at " . $result['event_name'] . " and additional inquiry will be made by the VP.";
		}

		$html = "<h2>Slivka Points Correction Response Posted</h2>
			<h3>Automated Email</h3>
			<p style=\"padding: 10; width: 70%\">A points correction you submitted has received a response:</p>

			<p style=\"padding: 10; width: 70%\">" . $html_snippet . "</p>

			<p style=\"padding: 10; width: 70%\"><a href=\"http://slivka.northwestern.edu/points/table.php\" target=\"_blank\">View Points</a></p>

			<p style=\"padding: 10; width: 70%\">If you received this email in error, please contact " . $GLOBALS['VP_EMAIL'] . "</p>";

		return self::sendEmail($result['nu_email'],"Slivka Points Correction Response Posted (Automated)",$html);
	}

	private function sendEmail($to_email,$subject,$body)
	{
		$from = array($GLOBALS['VP_EMAIL'] => $GLOBALS['VP_NAME']);

		if($to_email){
			$to = array(
				$to_email . "@u.northwestern.edu" => $to_email,
				$GLOBALS['VP_EMAIL_BOT'] => $GLOBALS['VP_NAME'] . "'s Copy"
			);
		}else{
			$to = array($GLOBALS['VP_EMAIL'] => $GLOBALS['VP_NAME']);
		}

		$transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
			->setUsername($GLOBALS['VP_EMAIL'])
			->setPassword($GLOBALS['VP_EMAIL_PASS']);

		$mailer = Swift_Mailer::newInstance($transport);

		$message = new Swift_Message($subject);
		$message->setFrom($from);
		$message->setBody($body, 'text/html');
		$message->setTo($to);
		$message->addPart($subject, 'text/plain');

		if ($recipients = $mailer->send($message, $failures)){
			return "Message successfully sent!";
		} else {
			return "There was an error: " . print_r($failures);
		}
	}

	public function getCoursesInDept ($department)
	{
		$courses = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT courses
				FROM courses
				WHERE courses LIKE :department");
			$statement->bindValue(":department", "%".$department."%");
			$statement->execute();
			$courses = $statement->fetchAll(PDO::FETCH_COLUMN,0);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		$return = array();

		foreach($courses as $c){
			$arr = explode($department,$c);

			foreach($arr as $el){
				if($el[0] == ' '){
					$return[] = substr($el,1,($el[5] > 0 && $el[5] < 5 ? 5 : 3));
				}
			}
		}

		$return = array_unique($return);
		sort($return);

		return $return;
	}

	public function getCourseListing ($department,$number)
	{
		$courses = array();
		try {
			$statement = self::$dbConn->prepare(
				"SELECT CONCAT(first_name, ' ', last_name) AS full_name,
					courses.nu_email,courses.qtr
				FROM courses
				INNER JOIN slivkans ON courses.nu_email=slivkans.nu_email
				WHERE courses.courses LIKE :course AND qtr_joined <= :qtr AND (qtr_final IS NULL OR qtr_final >= :qtr)");
			$statement->bindValue(":course", "%".$department." ".$number."%");
			$statement->bindValue(":qtr", self::$qtr);
			$statement->execute();
			$courses = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage();
			die();
		}

		return $courses;
	}

	public function submitCourseDatabaseEntryForm ($nu_email,$courses,$qtr)
	{
		try {
			$statement = self::$dbConn->prepare(
				"INSERT INTO courses
				(nu_email, courses, qtr)
				VALUES (?,?,?)");
			$statement->execute(array($nu_email,$courses,$qtr));
		} catch (PDOException $e) {
			echo "Error: " . $e->getMessage() . "<br/>Tell the VP";
			die();
		}

		return true;
	}
}
