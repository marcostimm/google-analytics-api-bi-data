<?php
/**
 * API Analytics - Class Analytics 
 *
 * @author      Marcos Timm <timm@marcos.im>
 * @link        https://www.api
 * @version     0.1.4
 * @category    Analytics
 * @package     API
 * @subpackage  BI
 *
 */

require 'vendor/autoload.php';

class Analytics {

	const CACHEDIR					= "/home/user/conf/cache/";
	const SERVICE_ACCOUNT_EMAIL 	= "xxxxxxx@developer.gserviceaccount.com";
	const KEY_FILE_LOCATION 		= "/home/user/conf/6def0a1cecde.p12";
	const WEBSITE_BASE_URL			= "http://www.my-website.com.br"
	const APPLICATION_NAME 			= "MyAnalytics";
	const MAIN_GA_CODE				= "ga:XXXXXXXX";
	const EVENT 					= ["Category" => , "Action"]

	/**
	 * Get all Analytics Profile
	 *
	 * @return array with id, name and profile items
	 */
	function getProfiles() {

		$analytics 			= getService();

		$accounts 	= $analytics->management_accounts->listManagementAccounts();
		$items 		= $accounts->getItems();

  		$properties = $analytics->management_webproperties->listManagementWebproperties($items[0]['id']);
    	$itemsProp = $properties->getItems();
		$firstPropertyId 	= $properties[0]->getId();
		$profiles 			= $analytics->management_profiles->listManagementProfiles($items[0]['id'], $itemsProp[0]['id']);
		$profileItems 		= $profiles->getItems();

		$cleanProfileItems = array();
		for ($i=0; $i < count($profileItems); $i++) { 
			$cleanProfileItems[$i]['id']	= $profileItems[$i]['id'];
			$cleanProfileItems[$i]['name']	= $profileItems[$i]['name'];
		}

    	$account['name'] 		= $items[0]['name'];
    	$account['id'] 			= $items[0]['id'];
    	$account['profiles'] 	= $cleanProfileItems;

		return array(
			'account'	=> $account
		);
	}

	/**
	 * Get Profile 
	 *
	 * @param $profileName A name of profile
	 * @return array with profile
	 */
	function getProfile($profileName) {

		$app->etag('analytics-profiles-'.$profileName);

		$profileName = preg_replace( '/[`^~\'"]/', null, iconv( 'UTF-8', 'ASCII//TRANSLIT', strtolower($profileName)));

		$analytics 		= getService();
		$cleanProfile 	= findProfile($analytics,$profileName);

		if($cleanProfile) {
			return array(
				'profile'	=> $cleanProfile
			);
		} else {
			return array(
				'error' 	=> true,
				'msg'		=> 'Profile name not found.',
				'profile'	=> array()
			);
		}
	}

	/**
	 * Get Profile data
	 *
	 * @param $profileName A name of profile
	 * @return array with profile data
	 */
	function getDataProfile($profile, $start = "30daysAgo", $end = "today") {

		$analytics 			= getService();

		if(!is_numeric($profile)) {
			$profileData 	= findProfile($analytics, $profile);
			$profile 		= $profileData['id'];
		}
		$completo = $analytics->data_ga->get('ga:'.$profile,$start,$end,'ga:sessions,ga:pageviews');			

		$simpleData = array();
		$simpleData['sessions'] 	= $completo['totalsForAllResults']['ga:sessions'];
		$simpleData['pageviews'] 	= $completo['totalsForAllResults']['ga:pageviews'];

		return array(
			'profile'	=> $profile,
			'startData'	=> $start,
			'endData'	=> $end,
			'data'		=> $simpleData
		);
	}

	/**
	 * Get Path
	 *
	 * @param $start can be a textual value or a date YYYY-mm-dd
	 * @param $end can be a textual value or a date YYYY-mm-dd
	 * @return array with profile data
	 */
	function getPathBy($start = "30daysAgo", $end = "today") {

		$analytics = getService();

		$cleanPath = str_replace(WEBSITE_BASE_URL,'', $_GET['path']);

		$arrPath = explode(',',$cleanPath);
		$newPath = implode(',ga:pagePath',$arrPath);

		if($newPath) {

			$result = $analytics->data_ga->get(
			    MAIN_GA_CODE,
			    $start,
			    $end,
			    'ga:sessions,ga:pageviews,ga:uniquePageviews,ga:entrances,ga:bounces,ga:timeOnPage,ga:exits',
			    array('filters' => 'ga:pagePath'.$newPath)
			);

			return array(
				'profile'	=> $profile,
				'startData'	=> $start,
				'endData'	=> $end,
				'data'		=> $result
			);

		} else {

			return array(
				'error'		=> true,
				'msg'		=> 'You must specify the path',
				'startData'	=> $start,
				'endData'	=> $end,
				'data'		=> array()
			);
		}
	}

	/**
	 * Get Blogs information based on database URLs
	 *
	 * @param $start can be a textual value or a date YYYY-mm-dd
	 * @param $end can be a textual value or a date YYYY-mm-dd
	 * @return array with profile data
	 */
	function getBlogs($start = "yesterday", $end = "yesterday") {

		$app->etag('analytics-blog-'.$start."-".$end);

		$DB = Utils::DB();

		$analytics = $this->getService();

		// Get Blogs URL
		$sql = "SELECT * FROM blogs WHERE deleted IS NULL AND ativo = 1";
		$stmt = $DB->prepare($sql);
		$stmt->execute();
		$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$blogsData = array();
		for ($i=0; $i < COUNT($blogs); $i++) { 
			$optParams = array('filters' => 'ga:pagePath=~/'.$blogs[$i]['editoria'].'/blogs/'.$blogs[$i]['slug'].'*');
			$data = $analytics->data_ga->get(
				MAIN_GA_CODE,
				$start,
				$end,
				'ga:sessions,ga:pageviews,ga:uniquePageviews,ga:entrances,ga:bounces,ga:timeOnPage,ga:exits',
				$optParams
			);
			$blogData['sessions'] 			= $data['totalsForAllResults']['ga:sessions'];
			$blogData['pageviews'] 			= $data['totalsForAllResults']['ga:pageviews'];
			$blogData['uniquePageviews'] 	= $data['totalsForAllResults']['ga:uniquePageviews'];
			$blogData['entrances'] 			= $data['totalsForAllResults']['ga:entrances'];
			$blogData['bounces'] 			= $data['totalsForAllResults']['ga:bounces'];
			$blogData['timeOnPage'] 		= $data['totalsForAllResults']['ga:timeOnPage'];
			$blogData['exits'] 				= $data['totalsForAllResults']['ga:exits'];

			$blogsData[$i]['name'] 		= $blogs[$i]['titulo'];
			$blogsData[$i]['slug'] 		= '/'.$blogs[$i]['editoria'].'/blogs/'.$blogs[$i]['slug'];
			$blogsData[$i]['editoria'] 	= $blogs[$i]['editoria'];
			$blogsData[$i]['analytics']	= $blogData;
		}

		return array(
			'startData'	=> $start,
			'endData'	=> $end,
			'blogs'		=> $blogsData
		);
	}

	/**
	 * Get most viewed pages
	 *
	 * @param $start can be a textual value or a date YYYY-mm-dd
	 * @param $end can be a textual value or a date YYYY-mm-dd
	 * @return array with top pages
	 */
	function top($start = "2015-01-01", $end = "today") {

		$app->etag('analytics-top-'.$start."-".$end);

		$analytics 			= $this->getService();

		$optParams = array(
			'dimensions' 	=> 'ga:pagePath',
			'sort'			=> '-ga:pageviews',
			'max-results' 	=> 100
		);

		$complete = $analytics->data_ga->get(
			MAIN_GA_CODE,
			$start,
			$end,
			'ga:pageviews,ga:sessions,ga:uniquePageviews,ga:entrances,ga:bounces,ga:timeOnPage,ga:exits',
			$optParams
		);

		return array(
			'startData'	=> $start,
			'endData'	=> $end,
			'data'		=> $complete
		);
	}

	/**
	 * Get Realtime events data
	 *
	 * @return array with events data
	 */
	function events() {

		$analytics 	= $this->getService();

		$optParams = array(
			'dimensions' => 'rt:eventAction,rt:eventCategory,rt:eventLabel',
			'filters'	=> 'rt:eventAction=='.EVENT["Action"].';rt:eventCategory==' . EVENT["Category"]
		);
		$events = $analytics->data_realtime->get(
		MAIN_GA_CODE,
		'rt:activeUsers',
		$optParams);

		return array(
			'events' => $events
		);
	}

	/**
	 * Get Realtime Data
	 *
	 * @return array with profile data
	 */
	function realtime() {

		$cacheTime = "5 seconds";

		if(file_exists(CACHEDIR . 'analytics-realtime.json')) {
			$realTime 						= json_decode(file_get_contents(CACHEDIR . 'analytics-realtime.json'), true);
			$realTime['cache']['status'] 	= true;
			$realTime['cache']['log'] 		= "Cache will be expired in few seconds";

			if($realTime['cache']['cacheControll'] < strtotime("-" . $cacheTime)) {
				$realTime 						= realTimeData();
				$realTime['cache']['status'] 	= false;
				$realTime['cache']['log'] 		= "Last cache has more than " . $cacheTime;
			}
		} else {
			$realTime = realTimeData();
			$realTime['cache']['status'] 	= false;
			$realTime['cache']['log'] 		= 'fail do read cache file';
		}

		return array(
			'activeUsers'	=> $realTime
		);

	}

	/**
	 * Get Analytics Object Service
	 *
	 * @return object Google Service Analytics
	 */
  	function getService() {

	    $service_account_email 	= SERVICE_ACCOUNT_EMAIL;
	    $key_file_location 		= KEY_FILE_LOCATION;

		// Create and configure a new client object.
		$client = new Google_Client();
		$client->setApplicationName(APPLICATION_NAME);
		$analytics = new Google_Service_Analytics($client);

		// Read the generated client_secrets.p12 key.
		$key = file_get_contents($key_file_location);
		$cred = new Google_Auth_AssertionCredentials(
		    $service_account_email,
		    array(Google_Service_Analytics::ANALYTICS_READONLY),
		    $key
		);
		$client->setAssertionCredentials($cred);
		if($client->getAuth()->isAccessTokenExpired()) {
		  $client->getAuth()->refreshTokenWithAssertion($cred);
		}

		return $analytics;
  	}

	/**
	 * Find profile by name
	 *
	 * @return object analytics profile
	 */
  	function findProfile($analytics, $profileName) {

		$accounts 			= $analytics->management_accounts->listManagementAccounts();
		$items 				= $accounts->getItems();
  		$properties 		= $analytics->management_webproperties->listManagementWebproperties($items[0]['id']);
    	$itemsProp 			= $properties->getItems();
		$firstPropertyId 	= $properties[0]->getId();
		$profiles 			= $analytics->management_profiles->listManagementProfiles($items[0]['id'], $itemsProp[0]['id']);
		$profileItems 		= $profiles->getItems();

		$cleanProfile = array();
		for ($i=0; $i < count($profileItems); $i++) { 
			if(strtolower($profileItems[$i]['name']) == strtolower($profileName)){
				$cleanProfile['id']		= $profileItems[$i]['id'];
				$cleanProfile['name']	= $profileItems[$i]['name'];
			}
		}
		return $cleanProfile;
  	}

	function realTimeData() {

		$online 			= array();
		$analytics 			= getService();
		$optParams 			= array('dimensions' => 'rt:medium');
		$analytisResults 	= $analytics->data_realtime->get(MAIN_GA_CODE,'rt:activeUsers', $optParams);
		$online['rows'] 	= $analytisResults->rows;
		$online['total'] 	= $analytisResults->totalsForAllResults["rt:activeUsers"];
		$online['cache']['cacheControll'] = time();
		file_put_contents(CACHEDIR . 'analytics-realtime.json', json_encode($online));

		return $online;

	}

}