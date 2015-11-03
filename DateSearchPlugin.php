<?php

/**
* DateSearch plugin.
*
* @package Omeka\Plugins\DateSearch
*/
class DateSearchPlugin extends Omeka_Plugin_AbstractPlugin {

	/**
	* @var array This plugin's hooks.
	*/
	protected $_hooks = array(
		'initialize', # tap into i18n
		'install', # create additional table and batch-preprocess existing items for dates / timespans
		'uninstall', # delete table
		'config_form', # display choice whether or not to use [G] / [J] prefixes
		'config', # store config settings in the database
		'after_save_item', # preprocess saved item for dates / timespans
		'after_delete_item', # delete deleted item's preprocessed dates / timespans
		'admin_items_search', # add a time search field to the advanced search panel in admin
		'public_items_search', # add a time search field to the advanced search panel in public
		'admin_items_show_sidebar', # Debug output of stored dates/timespans in item's sidebar (if activated)
		'items_browse_sql', # filter for a date after search page submission.
	);

	protected $_options = array(
		'date_search_use_gregjul_prefixes' => 0,
		'date_search_search_all_fields' => 1,
		'date_search_limit_fields' => "[]",
		'date_search_search_rel_comments' => 1,
		'date_search_debug_output' => 0,
	);

	/**
	 * Add the translations.
	 */
	public function hookInitialize() {
		add_translation_source(dirname(__FILE__) . '/languages');
	}

	/**
	 * Install the plugin.
	 */
	public function hookInstall() {
		# Create table
		$db = get_db();

		# Dates are always "YYYY-MM-DD", i.e. 10 characters long

		$sql = "
		CREATE TABLE IF NOT EXISTS `$db->DateSearchDates` (
				`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`item_id` int(10) unsigned NOT NULL REFERENCES `$db->Item`,
				`fromdate` varchar(10) NOT NULL,
				`todate` varchar(10) NOT NULL,
				PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
		$db->query($sql);

		SELF::_installOptions();

		SELF::_batchProcessExistingItems();
	}

	/**
	 * Uninstall the plugin.
	 */
	public function hookUninstall() {
		$db = get_db();

		# Drop the table
		$sql = "DROP TABLE IF EXISTS `$db->DateSearchDates`";
		$db->query($sql);

		SELF::_uninstallOptions();
	}

	/**
	 * Display the plugin configuration form.
	 */
	public static function hookConfigForm() {
		$useGregJulPrefixes = intval(get_option('date_search_use_gregjul_prefixes'));
		$searchAllFields = (int)(boolean) get_option('date_search_search_all_fields');

		$db = get_db();
		$sql = "select id, name from `$db->Elements` order by name asc";
		$elements = $db->fetchAll($sql);

		$searchElements = array();
		foreach($elements as $element) { $searchElements[$element["id"]] = $element["name"]; }

		$LimitFields = get_option('date_search_limit_fields');
		$LimitFields = ( $LimitFields ? json_decode($LimitFields) : array() );

		$withRelComments=SELF::_withRelComments();
		$searchRelComments = (int)(boolean) get_option('date_search_search_rel_comments');

		$debugOutput = (int)(boolean) get_option('date_search_debug_output'); # comment line to remove debug output panel

		require dirname(__FILE__) . '/config_form.php';
	}

	/**
	 * Handle the plugin configuration form.
	 */
	public static function hookConfig() {
		// Gregorian / Julian Prefix switch
		$useGregJulPrefixes = intval($_POST['date_search_use_gregjul_prefixes']);
		set_option('date_search_use_gregjul_prefixes', $useGregJulPrefixes);

		// Search All Fields switch
		$searchAllFields = (int)(boolean) $_POST['date_search_search_all_fields'];
		set_option('date_search_search_all_fields', $searchAllFields);

		// Limit Fields list (in case "Search All Fields" is false
		$limitFields = array();
		$postIds=false;
		if (isset($_POST["date_search_limit_fields"])) { $postIds = $_POST["date_search_limit_fields"]; }
		if (is_array($postIds)) {
			foreach($postIds as $postId) {
				$postId = intval($postId);
				if ($postId) { $limitFields[] = $postId; }
			}
		}
		sort($limitFields);
		$limitFields = json_encode($limitFields);
		set_option('date_search_limit_fields', $limitFields);

		// Search Relationship Comments switch
		$searchRelComments = (int)(boolean) $_POST['date_search_search_rel_comments'];
		set_option('date_search_search_rel_comments', $searchRelComments);

		// Debug Output switch -- if present
		$debugOutput = 0; // Sanity
		if (isset($_POST['date_search_debug_output'])) {
			$debugOutput = (int)(boolean) $_POST['date_search_debug_output'];
		}
		set_option('date_search_debug_output', $debugOutput);

		$reprocess = (int)(boolean) $_POST['date_search_trigger_reindex'];
		if ($reprocess) { SELF::_batchProcessExistingItems(); }
		# echo "<pre>"; print_r($_POST); echo "</pre>"; die();
	}

	/**
	 * Preprocess ALL existing items which could be rather EVIL in huge installations
	 */
	private function _batchProcessExistingItems() {
		$db = get_db();
		$sql= "select id from `$db->Items`";
		$items = $db->fetchAll($sql);
		foreach($items as $item) { SELF::_preProcessItem($item["id"]); }
	}

	/**
	 * Preprocess yyyy / yyyy-mm / yyyy-mm-dd dates after saving an item add/edit form.
	 *
	 * @param array $args
	 */
	public function hookAfterSaveItem($args) {
			if ( (!$args['post']) and (!$args['insert']) ) {
					return;
			}

			$item_id = intval($args["record"]["id"]);
			if ($item_id) { SELF::_preProcessItem($item_id); }

			# die("After Save Item");

	} # hookAfterSaveItem()

	/**
	 * Delete pre-processed dates after an item has been deleted
	 *
	 * @param array $args
	 */
	public function hookAfterDeleteItem($args) {
			$db = get_db();

			$item_id = intval($args["record"]["id"]);

			if ($item_id) {
				$sql = "delete from `$db->DateSearchDates` where item_id=$item_id";
				$db->query($sql);
			}

			# echo "<pre>After Delete Item - ID: $item_id\nSQL: $sql\n"; print_r($args); die("</pre>");
	} # hookAfterDeleteItem()

	/**
	 * Determine if Item Relations is installed, and if it's patched to feature relationship comments
	 */
	private function _withRelComments() {
		$db = get_db();

		$withRelComments=false;
		$sql = "show columns from `$db->ItemRelationsRelations` where field='relation_comment'";
		try { $withRelComments = ($db->fetchOne($sql) !== false); }
		catch (Exception $e) { $withRelComments=false; }

		return $withRelComments;
	}

	/**
	 * Get an item's relationship comment text
	 */
	private function _relationshipCommentText($item_id) {
		$db = get_db();
		$text = "";

		# Check if we could add relation comments in case Item Relations is installed and has been patched
		# to feature relation comments.
		$withRelComments=SELF::_withRelComments();

		if ($withRelComments) {
			$sql = "select relation_comment from `$db->ItemRelationsRelations` where subject_item_id=$item_id";
			$comments = $db->fetchAll($sql);
			if ($comments) {
				foreach($comments as $comment) { $text .= " ".$comment["relation_comment"]; }
			}
		}

		return $text;
	}

	/**
	 * Pre-process one item's textual data and store timespans in DateSearchDates table
	 */
	private function _preProcessItem($item_id) {
		$db = get_db();

		if ($item_id) {
			$sql = "delete from `$db->DateSearchDates` where item_id=$item_id";
			$db->query($sql);

			$text = false;

			$searchAllFields = (int)(boolean) get_option('date_search_search_all_fields');

			if ($searchAllFields) {
				$text = $db->fetchOne("select text from `$db->SearchTexts` where record_type='Item' and record_id=$item_id");
				$text = ( $text ? $text : "" );

				$text .= SELF::_relationshipCommentText($item_id);
				$text = ( $text ? $text : false );
			} # if ($searchAllFields)

			else { # !$searchAllFields

				$limitFields = get_option('date_search_limit_fields');
				$limitFields = ( $limitFields ? json_decode($limitFields) : array() );

				$elementIds=array();
				if (is_array($limitFields)) {
					foreach($limitFields as $limitField) {
						$limitField = intval($limitField);
						if ($limitField) { $elementIds[] = $limitField; }
					}
					sort($elementIds);
				}

				if ($elementIds) {
					$elementIds = "(" . implode(",", $elementIds) . ")";

					$elementTexts = $db -> fetchAll("select text from `$db->ElementTexts`".
																					" where record_id=$item_id".
																					" and element_id in $elementIds");
					if ($elementTexts) {
						$text = "";
						foreach($elementTexts as $elementText) { $text .= " " . $elementText["text"]; }
					} # if ($elementTexts)
				} # if ($elementIds)

				$searchRelComments = (int)(boolean) get_option('date_search_search_rel_comments');

				if ($searchRelComments) {
					$text = ( $text ? $text : "" );
					$text .= SELF::_relationshipCommentText($item_id);
					$text = ( $text ? $text : false );
				}

			}  # !$searchAllFields

			if ($text !== false) {

				$cookedDates = SELF::_processDateText($text);
				# echo "<pre>"; print_r($cookedDates); die("</pre>");

				if ($cookedDates) {

					$values = array();
					foreach($cookedDates as $cookedDate) {
						SELF::_swapIfNecessary($cookedDate[0], $cookedDate[1]);
						$values[]='('.$item_id.',"'.$cookedDate[0].'","'.$cookedDate[1].'")';
					}
					$values = implode(", ", $values);

					$sql = "insert into `$db->DateSearchDates` (item_id, fromdate, todate) values $values";
					$db->query($sql);
					# die($sql);

				} # if ($cookedDates)
			} # if ($text)
		} # if ($item_id)
	} #  function _preProcessItem()

	/**
	 * Display the time search form on the admin advanced search page
	 */
	protected function _ItemsSearch() {
		echo common('date-search-advanced-search', null);
	}

	/**
	 * Display the time search form on the admin advanced search page in admin
	 */
	public function hookAdminItemsSearch() { SELF::_itemsSearch(); }

	/**
	 * Display the time search form on the admin advanced search page in public
	 */
	public function hookPublicItemsSearch() { SELF::_itemsSearch(); }

  /**
  * Debug output of stored dates/timespans in item's sidebar (if activated)
  *
  * @param Item $item
  */
  public function hookAdminItemsShowSidebar($args) {
		$debugOutput = (int)(boolean) get_option('date_search_debug_output');
		if ($debugOutput) {
			$itemID = $args['item']['id'];
			if ($itemID) {
				echo "<div class='panel'><h4>".__("Date Search Debug Output")."</h4>\n";
				$db = get_db();
				$sql = "select * from `$db->DateSearchDates` where item_id=$itemID";
				$timespans = $db->fetchAll($sql);
				if ($timespans) {
					echo "<ul>\n";
					foreach($timespans as $timespan) {
						$fromDate = $timespan["fromdate"];
						$toDate = $timespan["todate"];
						echo "<li>". $fromDate . " … ". $toDate . "</li>\n";
					}
					echo "</ul>\n";
				}
				echo "</div>\n";
			}
		}
	}

	/**
	 * Filter for an date after search page submission.
	 *
	 * @param array $args
	 */
	public function hookItemsBrowseSql($args) {
		$select = $args['select'];
		$params = $args['params'];

		$regEx = SELF::_constructRegEx();
		$date = $regEx["date"];
		$dateTimespan = $regEx["dateTimespan"];

		if (	(isset($params['date_search_term'])) and
					(preg_match( "($dateTimespan)", $params['date_search_term'])) ) {

			$singleCount = preg_match_all ( "($date)", $params['date_search_term'], $singleSplit );
			$timespan = array();
			$timespan[] = $singleSplit[0][0];
			$timespan[] = $singleSplit[0][ ($singleCount==2 ? 1 : 0 ) ];
			$timespan = SELF::_expandTimespan($timespan);

			$searchFromDate = $timespan[0];
			$searchToDate = $timespan[1];

			$db = get_db();
			$select
					->join(
							array('date_search_dates' => $db->DateSearchDates),
							"date_search_dates.item_id = items.id",
							array()
					)
					->where("'$searchFromDate'<=date_search_dates.todate and '$searchToDate'>=date_search_dates.fromdate");
					# die("<pre>$searchFromDate / $searchToDate --- $select</pre>");

		}

	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Cross swap  in case the first element is "bigger" (i.e. sorts behind) the second
	 */
	private function _swapIfNecessary(&$x,&$y) {
		# as in http://stackoverflow.com/a/26549027
		if ($x > $y) {
			$tmp=$x;
			$x=$y;
			$y=$tmp;
		}
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Main regex processing to extract dates and timespans, to be able to expand them later
	 */
	private function _processDateText($text) {
		$regEx = SELF::_constructRegEx();
		$julGregPrefix = $regEx["julGregPrefix"];
		$date = $regEx["date"];

		$useGregJulPrefixes = intval(get_option('date_search_use_gregjul_prefixes'));

		$mainRegEx = $regEx["dateTimespan"]; // Default: Ignore prefixes
		switch ($useGregJulPrefixes) {
			case 1 : $mainRegEx = $regEx["julGregDateTimeSpan"]; break; // 1 == old "true": Require [J]/[G]
			// 2 == optional prefix: honor if present, but also parse dates without prefix
			case 2 : $mainRegEx = $regEx["optionalJulGregTimeSpan"]; break;
		}

		$allCount = preg_match_all( "($mainRegEx)i", $text, $allMatches);
		# echo "<pre>Count: $allCount\n" . print_r($allMatches,true) . "</pre>";

		$cookedDates = array();
		foreach($allMatches[0] as $singleMatch) {
			$singleCount = preg_match_all ( "($date)", $singleMatch, $singleSplit );
			$timespan = array();
			$timespan[] = $singleSplit[0][0];
			$timespan[] = $singleSplit[0][ ($singleCount==2 ? 1 : 0 ) ];
			$timespan = SELF::_expandTimespan($timespan);

			$storeDate = true;

			if ($useGregJulPrefixes>0) { // Gregorian / Julian date prefixes
				$julGreg = preg_match( "($julGregPrefix)i", $singleMatch, $julGregMatch );
				$julGregJG = ($julGreg == 1 ? strtoupper($julGregMatch[1]) : null ); // "G" or "J" or null
				# echo "<pre>$julGreg / $julGregJG: " .  print_r($julGregMatch,true) . "</pre>";

				switch ($julGregJG) {
					case "J" : $storeDate = ($timespan[0] <= "1582-10-04"); break;
					case "G" : $storeDate = ($timespan[1] >= "1582-10-15"); break;
				}
				# echo "<pre>StoreDate: $storeDate\n" . print_r($timespan,true) . "</pre>";
			}

			if ($storeDate) { $cookedDates[] = $timespan; }
		}
		# echo "<pre>" . print_r($cookedDates,true) . "</pre>";
		# die();

		return $cookedDates;
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Create the necessary regEx expressions to deal with yyyy / yyyy-mm / yyyy-mm-dd / yyyy-mm-dd - yyyy-mm-dd
	 */
	private function _constructRegEx() {

		# Construct RegEx
		$year = "\d{4}";
		$month = $day = "\d{1,2}";
		$monthDay = "$month(?:-$day)?";
		$date = "$year(?:-$monthDay)?\b";
		$separator = "\s*-\s*";
		$dateTimespan = "$date(?:$separator$date)?";

		$julGregPrefix = "\[([J,G])\] ";
		$julGregDateTimeSpan = $julGregPrefix.$dateTimespan;
		$optionalJulGregTimeSpan = "(?:$julGregDateTimeSpan|$dateTimespan)";

		$result=array(
								"year" => $year,
								"month" => $month,
								"day" => $day,
								"monthDay" => $monthDay,
								"date" => $date,
								"separator" => $separator,
								"dateTimespan" => $dateTimespan,
								"julGregPrefix" => $julGregPrefix,
								"julGregDateTimeSpan" => $julGregDateTimeSpan,
								"optionalJulGregTimeSpan" => $optionalJulGregTimeSpan,
							);

		return $result;

	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Transform a (valid) date yyyy-mm-dd into a timespan -- down to yyyy-01-01 to yyyy-12-31
	 *
	 * @param string $timespan as in single date or timespan
	 * @result array [0] => left edge, [1] => right edge
	 */
	private function _expandTimespan($timespan) {
		$result = $timespan;

		if (!is_array($result)) { $result = array($result, $result); }

		$result[0] = SELF::_updateDate($result[0], -1); # -1 == left edge, xxxx-01-01
		$result[1] = SELF::_updateDate($result[1], +1); # +1 == right edge, xxxx-12-31

		return $result;
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Take a valid yyyy / yyyy-m / yyyy-mm / yyyy-m-d / yyyy-mm-d / yyyy-mm-dd
	 * and transform it towards a left edge of possibly yyyy-01-01 or yyyy-12-31
	 * or at least add leading zeros, as in yyyy-0m-0d
	 *
	 * @param string $date to be updated
	 * @param int edge -- -1 -> left edge (-01-01) / +1 -> right edge (-12-31)
	 * @result string $date -- transformed towards edge and with leading zeros
	 */
	protected function _updateDate($date, $edge) {
		$result=$date;

		$regEx = SELF::_constructRegEx();

		$year = $regEx["year"];
		$month =$regEx["month"];
		$day = $regEx["day"];

		$yearOnly = "^$year$";
		$yearMonth = "^$year-$month$";
		$yearMonthDay = "^$year-$month-$day$";

		if ( preg_match( "($yearOnly)", $result ) ) { $result = $result."-".( $edge<0 ? "1" : "12" ); }
		if ( preg_match( "($yearMonth)", $result ) ) { $result = $result."-".( $edge<0 ? "1" : "31" ); }

		if ( preg_match( "($yearMonthDay)", $result ) ) {
			$oneDigit = "\b(\d)\b";
			$result = preg_replace("($oneDigit)", '0${0}', $result);
		}

		return $result;
	}

	# ------------------------------------------------------------------------------------------------------

} # class
