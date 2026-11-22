<?php
  session_start();

  if(isset($_SESSION["final_redirect_uri"])){
    $final_redirect_uri = $_SESSION["final_redirect_uri"];
  }
  if(isset($_SESSION["social_provider"])) $social_provider = $_SESSION["social_provider"];


  //카카오일 경우
  if(isset($_REQUEST["code"]) && isset($_SESSION["social_provider"])){
    if($social_provider=='kakao'){
      $protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true ? 'https://' : 'http://';

      $url = 'https://kauth.kakao.com/oauth/token'; //호출 URL 지정
      $redirect_uri = $protocol.$_SERVER["HTTP_HOST"].'/widgets/widget_social_login/redirect_uri.php';
      $post_data = "grant_type=authorization_code&client_id=".$_SESSION["kakao_rest_key"]."&redirect_uri=".$redirect_uri."&code=".$_REQUEST["code"];

      $curlsession = curl_init (); 
      curl_setopt($curlsession, CURLOPT_URL, $url);
      curl_setopt($curlsession, CURLOPT_USERAGENT, 'CURL request'); 
      curl_setopt($curlsession, CURLOPT_TIMEOUT, 5); 
      curl_setopt($curlsession, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curlsession, CURLOPT_POST, 1); 
      curl_setopt($curlsession, CURLOPT_POSTFIELDS, $post_data); 
      curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, 1);
      $res_json = curl_exec($curlsession);
      $res_error = curl_error($curlsession); //에러정보
      curl_close($curlsession);

      $res_json_obj = json_decode($res_json);
      $_SESSION["kakao_access_token"] = $res_json_obj->access_token;

      header('Location: '.$redirect_uri.'?kakao_info=y');
      //echo $res_json;
      //echo '<br />'.$res_error;
    }
  }
  if(isset($_REQUEST["kakao_info"]) && isset($_SESSION["kakao_access_token"]) && isset($_SESSION["social_provider"])){
    $kakao_access_token = $_SESSION["kakao_access_token"];
    if($social_provider=='kakao' && $_REQUEST["kakao_info"]=='y' && $kakao_access_token){
      $data = array();
      $data['Authorization'] = 'Bearer '.$kakao_access_token;
		  $curlsession = curl_init();
      curl_setopt($curlsession, CURLOPT_URL, 'https://kapi.kakao.com/v1/user/me');
      curl_setopt($curlsession, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curlsession, CURLOPT_HTTPHEADER, array(
		    'Authorization: '.$data['Authorization'],
		    'Content-type: application/x-www-form-urlencoded;charset=utf-8'
      ));
    	curl_setopt($curlsession, CURLOPT_RETURNTRANSFER,true);
      $res_json = curl_exec($curlsession);
      $res_error = curl_error($curlsession); //에러정보
      curl_close($curlsession);

      $res_json_obj = json_decode($res_json);
      $_SESSION["kakao_id"] = $res_json_obj->id;
      $_SESSION["kakao_nickname"] = $res_json_obj->properties->nickname;
      $_SESSION["kakao_profile_image"] = $res_json_obj->properties->profile_image;

      header('Location: '.$final_redirect_uri);
      //echo $res_json_obj->properties->nickname;
      //echo $res_json;
      //echo '<br />'.$res_error;
    }
  }

  //트위터일 경우
  unset($_SESSION["twitter_oauth_token"]);
  unset($_SESSION["twitter_oauth_verifier"]);
  if(isset($_REQUEST["oauth_token"]) && isset($_REQUEST["oauth_verifier"]) && isset($_SESSION["social_provider"])){
    if($social_provider=='twitter'){
      $_SESSION["twitter_oauth_token"] = $_REQUEST["oauth_token"];
      $_SESSION["twitter_oauth_verifier"] = $_REQUEST["oauth_verifier"]; 
      header('Location: '.$final_redirect_uri);
    }
  }
  if(isset($_REQUEST["denied"]) && isset($_SESSION["social_provider"])){
    if($social_provider=='twitter'){
      unset($_SESSION["social_email"]);
      unset($_SESSION["social_provider"]);
      unset($_SESSION["social_oauth_move"]);
      unset($_SESSION["twitter_oauth_token"]);
      unset($_SESSION["twitter_oauth_verifier"]);
      unset($_SESSION["twitter_oauth_token_secret"]);
      header('Location: '.$final_redirect_uri);
    }
  }


  //카카오 및 트위터가 아닐경우
  if(isset($final_redirect_uri) && isset($_SESSION["social_provider"])){
    if($social_provider!='kakao' && $social_provider!='twitter'){
      header('Location: '.$final_redirect_uri);
    }
  }
?>
