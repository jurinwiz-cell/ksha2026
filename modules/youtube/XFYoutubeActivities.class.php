<?php
//Copyright (c) 2014 Studio2b
//xFacility2014
//xFYoutubeActivities
//Studio2b(www.studio2b.kr)
//Michael Son(mson0129@gmail.com)
//01DEC2014(1.0.0.)
//07JUN2015(1.0.0.) - Ported for XpressEngine
class XFYoutubeActivities {
	var $api_key, $token, $token_type;
	
	function __construct($token=NULL, $api_key=NULL, $token_type=NULL) {
		$xFGoogle = array('token_type' => 'Bearer', 'api_key' => '');
		if(!is_null($token))
			$this->token = $token;
		if(!is_null($api_key)) {
			$this->api_key = $api_key;
		} else {
			$this->api_key = isset($xFGoogle['api_key']) ? $xFGoogle['api_key'] : '';
		}
		if(!is_null($token_type)) {
			$this->token_type = $token_type;
		} else {
			$this->token_type = $xFGoogle['token_type'];
		}
	}
	
	function browse($part, $channelId=NULL, $home=NULL, $mine=NULL, $maxResults=5, $pageToken=NULL, $publishedAfter=NULL, $publishedBefore=NULL, $regionCode=NULL) {
		$return = false;
		if(!is_null($this->token) || !is_null($channelId)) {
			$data = array();
			$header = array();
			//Essential
			$data['key'] = $this->api_key;
			$data['part'] = $part;
			//Selectional
			if(!is_null($this->token)) {
				if(!is_null($home) || !is_null($mine)) {
					$header[] = "Authorization: ".$this->token_type." ".$this->token;
					if(!is_null($home)) {
						$data['home'] = $home;
					} else {
						$data['mine'] = $mine;
					}
				} else {
					$data['channelId'] = $channelId;
				}
			}
			//Additional(Optional)
			if(!is_null($maxResults))
				$data['maxResults'] = $maxResults;
			if(!is_null($pageToken))
				$data['pageToken'] = $pageToken;
			if(!is_null($publishedAfter))
				$data['publishedAfter'] = $publishedAfter;
			if(!is_null($publishedBefore))
				$data['publishedBefore'] = $publishedBefore;
			if(!is_null($regionCode)) {
				$data['regionCode'] = $regionCode;
			} else if(class_exists('XFLanguage')) {
				$languageClass = new XFLanguage();
				$languages = $languageClass->selectLanguages;
				if(is_array($languages) && isset($languages[0])) {
					$parts = explode("-", $languages[0]);
					$data['regionCode'] = end($parts);
				}
			}
			
			$curlClass = new XFCurl("GET", "https://www.googleapis.com/youtube/v3/activities", $header, $data);
			$return = json_decode($curlClass->body, true);
			if($curlClass->httpCode!=200 || !is_array($return)) {
				$return = false;
			}
		}
		return $return;
	}
	
	function insert($part, $fields=NULL, $bodyArray) {
		$header = array();
		$data = array();
		$data['key'] = $this->api_key;
		$data['part'] = $part;
		if(!is_null($fields))
			$data['fields'] = $fields;
		$getParam = "?".XFCurl::getParameter($data);

		if(strpos($part, "snippet")!==false && isset($bodyArray['snippet']['description']) && !is_null($bodyArray['snippet']['description'])) {
			$curlClass = new XFCurl("POST", "https://www.googleapis.com/youtube/v3/activities".$getParam, $header, $bodyArray);
			
			if($curlClass->httpCode==200) {
				$return = json_decode($curlClass->body, true);
			} else {
				$return = false;
			}
		} else {
			$return = false;
		}
		return $return;
	}
}
?>