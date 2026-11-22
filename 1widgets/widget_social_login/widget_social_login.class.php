<?php
    /**
     * @author XENARA (kolaskks@naver.com)
     * @version 0.1
     **/



    class widget_social_login extends WidgetHandler {

        function proc($args) {
            /* 공통 객체 변수 설정 */
            $oPointModel = getModel('point');
            $logged_info = Context::get('logged_info');


            /* 아작스 설정영역 */
            if(Context::get('ajax')=='y') {
              // 회원가입 이메일 세션 설정
              if(!$logged_info && Context::get('social_email')){
                unset($_SESSION["social_email"]);
                $_SESSION["social_email"] = Context::get('social_email');
                $ajax_result_text = '이메일 세션저장 성공';
                Context::set('ajax_result_text', $ajax_result_text);
              }
              // 회원가입 전 소셜공급자 세션 설정
              if(!$logged_info && Context::get('social_provider') && Context::get('insert_session')=='y'){
                //세션 삭제
                unset($_SESSION["social_provider"]);
                unset($_SESSION["social_oauth_move"]);
                unset($_SESSION["twitter_oauth_token"]);
                unset($_SESSION["twitter_oauth_verifier"]);
                unset($_SESSION["twitter_oauth_token_secret"]);
                unset($_SESSION["kakao_access_token"]);
                unset($_SESSION["kakao_id"]);
                unset($_SESSION["kakao_nickname"]);
                unset($_SESSION["kakao_profile_image"]);
                //소셜공급자 세션 저장
                $_SESSION["social_provider"] = Context::get('social_provider');

                //IE에서 '이 페이지에서 나가겠습니까?' 창이 뜨는 문제로 'oauth_move'유무 세션 처리
                $_SESSION["social_oauth_move"] = 'y';

                $ajax_result_text = '소셜공급자 세션저장 성공';
                Context::set('ajax_result_text', $ajax_result_text);
              }

              //템플릿 객체생성 및 스킨파일 컴파일
              $oTemplate = TemplateHandler::getInstance();
              $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
              return $oTemplate->compile($tpl_path, "social_login_ajax");
            }



            if(Context::get('ajax')!='y') {
              //소셜설정에 등록한 redirect_uri 설정
              $redirect_uri = getFullUrl('').str_replace('./','',$this->widget_path).'redirect_uri.php';

              //최종 redirect_uri 세션 설정
              $_SESSION["final_redirect_uri"] = str_replace('amp;','',getFullUrl('mid',Context::get('mid'), 'facebook','', 'facebook_access_token','', 'twitter_request','', 'google','', 'google_access_token','', 'naver','', 'naver_access_token',''));

              $social_obj = new stdClass();
              //로그인 포인트 지급유무 변수 설정
              if($args->use_login_point!='N'){
                $social_obj->use_login_point = true;
              } else{
                $social_obj->use_login_point = false;
              }

              //페이스북 설정
              if($args->facebook_app_id){
                $social_obj->facebook_app_id = $args->facebook_app_id;
                if($args->facebook_app_version){
                  $social_obj->facebook_app_version = $args->facebook_app_version;
                } else{
                  $social_obj->facebook_app_version = "v2.5";
                }

                //IE에서 '이 페이지에서 나가겠습니까?' 창이 뜨는 문제로 인해 php 'header'로 변경함
                if($_SESSION["social_provider"]=='facebook' && $_SESSION["social_oauth_move"]=='y'){
                  unset($_SESSION["social_oauth_move"]);
                  $oauth_url = 'https://www.facebook.com/dialog/oauth?client_id='.$social_obj->facebook_app_id.'&redirect_uri='.urlencode($redirect_uri).'&response_type=token&scope=email';
                  header("Location: $oauth_url");
                }

                //페이스북 Access Token으로 및 페이스북 정보 설정
                if($_SESSION["social_provider"]=='facebook' && Context::get('facebook_access_token')){
                  $facebook_json_url = "https://graph.facebook.com/".$social_obj->facebook_app_version."/me?fields=id,name,email&access_token=".Context::get('facebook_access_token');

                  //페이스북 정보 설정 및 최종 로그인
                  $ch = curl_init($facebook_json_url);
                  curl_setopt($ch, CURLOPT_HEADER, 0);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                  curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
                  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

                  $facebook_json = curl_exec($ch);
                  curl_close($ch);

                  $facebook_user = json_decode($facebook_json);
                  $facebook_id = $facebook_user->id;
                  $facebook_name = $facebook_user->name;
                  $facebook_email = $facebook_user->email;
                  $facebook_profile_image = 'https://graph.facebook.com/'.$facebook_id.'/picture?type=large';
                  if($facebook_id && $facebook_name && $facebook_email){
                    $this->memberSocialLogin($_SESSION["social_provider"],$facebook_id,$facebook_name,$facebook_email,$facebook_profile_image,$social_obj->use_login_point, $args);
                  }
                  header("Location: ".$_SESSION["final_redirect_uri"]);
                }
              }

              //트위터 설정
              if($args->twitter_consumer_key && $args->twitter_consumer_secret){
                $social_obj->twitter_consumer_key = $args->twitter_consumer_key;
                $social_obj->twitter_consumer_secret = $args->twitter_consumer_secret;

                //IE에서 '이 페이지에서 나가겠습니까?' 창이 뜨는 문제로 인해 php 'header'로 변경함
                if($_SESSION["social_provider"]=='twitter' && $_SESSION["social_oauth_move"]=='y'){
                  unset($_SESSION["social_oauth_move"]);
                  $oauth_url = str_replace('amp;','',getFullUrl('mid',Context::get('mid'),'twitter_request','y'));
                  header("Location: $oauth_url");
                }

                //트위터 request_token 설정
                if($_SESSION["social_provider"]=='twitter' && Context::get('twitter_request')=='y'){
                  /* twitteroauth 이외 방식일 때
                  $baseURI = 'https://api.twitter.com/oauth/request_token';
                  $twitter_oauth_callback = getFullUrl('').'widgets/widget_social_login/redirect_uri.php';
                  require_once('libs/twitteroauth_xenara.php');
                  //redirect for authorization
                  header("Location: https://api.twitter.com/oauth/authorize?oauth_token=$oauth_token");
                  */
                  require "libs/twitteroauth-0.6.2_edit/src/Util/JsonDecoder.php";
                  require "libs/twitteroauth-0.6.2_edit/src/TwitterOAuthException.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Util.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Request.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Token.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Consumer.php";
                  require "libs/twitteroauth-0.6.2_edit/src/SignatureMethod.php";
                  require "libs/twitteroauth-0.6.2_edit/src/HmacSha1.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Response.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Config.php";
                  require "libs/twitteroauth-0.6.2_edit/src/TwitterOAuth.php";

                  $twitter_oauth_callback = getFullUrl('').'widgets/widget_social_login/redirect_uri.php';
                  $connection = new TwitterOAuth($social_obj->twitter_consumer_key, $social_obj->twitter_consumer_secret, null, null);
                  $content = $connection->oauth("oauth/request_token", array("oauth_callback" => $twitter_oauth_callback));

                  $_SESSION["twitter_oauth_token_secret"] = $content["oauth_token_secret"];
                  //redirect for authorization
                  $authorize_url = $connection->url("oauth/authorize", array("oauth_token" => $content["oauth_token"]));
                  header("Location: $authorize_url");
                }
                //트위터 access_token 설정
                if($_SESSION["social_provider"]=='twitter' && $_SESSION["twitter_oauth_token"] && $_SESSION["twitter_oauth_verifier"]){
                  /* twitteroauth 이외 방식일 때
                  $baseURI = 'https://api.twitter.com/oauth/access_token';
                  $twitter_oauth_callback = str_replace('amp;','',getFullUrl('mid',Context::get('mid')));
                  require_once('libs/twitteroauth_xenara.php');
                  unset($_SESSION["twitter_oauth_token"]);
                  unset($_SESSION["twitter_oauth_verifier"]);
                  $responseArray = explode('&',$response);
                  $accessTokenArray = explode('=',$responseArray[0]);
                  $accessTokenSecretArray = explode('=',$responseArray[1]);
                  $twitter_access_token = $accessTokenArray[1];
                  $twitter_access_secret = $accessTokenSecretArray[1];
                  */
                  require "libs/twitteroauth-0.6.2_edit/src/Util/JsonDecoder.php";
                  require "libs/twitteroauth-0.6.2_edit/src/TwitterOAuthException.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Util.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Request.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Token.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Consumer.php";
                  require "libs/twitteroauth-0.6.2_edit/src/SignatureMethod.php";
                  require "libs/twitteroauth-0.6.2_edit/src/HmacSha1.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Response.php";
                  require "libs/twitteroauth-0.6.2_edit/src/Config.php";
                  require "libs/twitteroauth-0.6.2_edit/src/TwitterOAuth.php";

                  $twitter_oauth_token = $_SESSION["twitter_oauth_token"];
                  $twitter_oauth_token_secret = $_SESSION["twitter_oauth_token_secret"];
                  $twitter_oauth_verifier = $_SESSION["twitter_oauth_verifier"];
                  unset($_SESSION["twitter_oauth_token"]);
                  unset($_SESSION["twitter_oauth_token_secret"]);
                  unset($_SESSION["twitter_oauth_verifier"]);

                  $connection = new TwitterOAuth($social_obj->twitter_consumer_key, $social_obj->twitter_consumer_secret, $twitter_oauth_token, $twitter_oauth_token_secret);
                  $content = $connection->oauth("oauth/access_token", array("oauth_verifier" => $twitter_oauth_verifier));

                  $twitter_access_token = $content["oauth_token"];
                  $twitter_access_secret = $content["oauth_token_secret"];
                  //트위터 access_token 및 access_secret을 이용하여, 트위터 정보 설정 및 최종 로그인
                  if($twitter_access_token && $twitter_access_secret){
                    $connection = new TwitterOAuth($social_obj->twitter_consumer_key, $social_obj->twitter_consumer_secret, $twitter_access_token, $twitter_access_secret);
                    $content = $connection->get("account/verify_credentials", array("include_email" => true));

                    if($_SESSION["social_provider"]=='twitter' && $content->id && $content->name && $_SESSION["social_email"]){
                      $this->memberSocialLogin($_SESSION["social_provider"],$content->id,$content->name,$_SESSION["social_email"],$content->profile_image_url_https,$social_obj->use_login_point, $args);
                      header("Location: ".$_SESSION["final_redirect_uri"]);
                    }
                  }
                }
              }

              //구글 설정
              if($args->google_client_id){
                $social_obj->google_client_id = $args->google_client_id;
                //Context::addHtmlHeader('<meta name="google-signin-client_id" content="'.$args->google_client_id.'" />');

                //IE에서 '이 페이지에서 나가겠습니까?' 창이 뜨는 문제로 인해 php 'header'로 변경함
                if($_SESSION["social_provider"]=='google' && $_SESSION["social_oauth_move"]=='y'){
                  unset($_SESSION["social_oauth_move"]);
                  $oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=token&client_id='.$social_obj->google_client_id.'&scope=email&redirect_uri='.urlencode($redirect_uri);
                  header("Location: $oauth_url");
                }

                //구글 Access Token으로 및 구글 정보 설정
                if($_SESSION["social_provider"]=='google' && Context::get('google_access_token')){
                  $google_json_url = "https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=".Context::get('google_access_token');

                  $ch = curl_init($google_json_url);
                  curl_setopt($ch, CURLOPT_HEADER, 0);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                  curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
                  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		                'Content-type: application/x-www-form-urlencoded;charset=utf-8'
                  ));
                  $google_json = curl_exec($ch);
                  curl_close($ch);

                  //구글 정보 설정 및 최종 로그인
                  $google_userinfo = json_decode($google_json);
                  $google_id = $google_userinfo->id;
            			$google_name = $google_userinfo->name;
            			$google_email = $google_userinfo->email;
            			$google_profile_image = $google_userinfo->picture;
                  if($google_id && $google_name && $google_email){
                    $this->memberSocialLogin($_SESSION["social_provider"],$google_id,$google_name,$google_email,$google_profile_image,$social_obj->use_login_point, $args);
                  }
                  header("Location: ".$_SESSION["final_redirect_uri"]);
                }
              }

              //네이버 설정
              if($args->naver_client_id){
                $social_obj->naver_client_id = $args->naver_client_id;

                //IE에서 '이 페이지에서 나가겠습니까?' 창이 뜨는 문제로 인해 php 'header'로 변경함
                if($_SESSION["social_provider"]=='naver' && $_SESSION["social_oauth_move"]=='y'){
                  unset($_SESSION["social_oauth_move"]);
                  $mt = microtime();
                  $rand = mt_rand();
                  $state = md5($mt . $rand);
                  $oauth_url = 'https://nid.naver.com/oauth2.0/authorize?client_id='.$social_obj->naver_client_id.'&response_type=token&redirect_uri='.urlencode($redirect_uri).'&state='.$state;
                  header("Location: $oauth_url");
                }

                //네이버 Access Token으로 및 네이버 정보 설정
                if($_SESSION["social_provider"]=='naver' && Context::get('naver_access_token')){
                  $data = array();
            			$data['Authorization'] = 'Bearer '.Context::get('naver_access_token');
            			$curlsession = curl_init();
			            curl_setopt($curlsession, CURLOPT_URL, 'https://apis.naver.com/nidlogin/nid/getUserProfile.xml');
          	  		curl_setopt($curlsession, CURLOPT_POST, 1);
			            curl_setopt($curlsession, CURLOPT_POSTFIELDS, $data);
			            curl_setopt($curlsession, CURLOPT_HTTPHEADER, array(
        				    'Authorization: '.$data['Authorization']
  			          ));
            			curl_setopt($curlsession, CURLOPT_RETURNTRANSFER,true);
	            		$retVar = curl_exec($curlsession);
			            curl_close($curlsession);
            			$xml = new SimpleXMLElement($retVar);

                  //네이버 정보 설정 및 최종 로그인
                  $naver_id = (string)$xml->response[0]->id[0];
            			$naver_name = (string)$xml->response[0]->name[0];
            			$naver_email = (string)$xml->response[0]->email[0];
            			$naver_profile_image = (string)$xml->response[0]->profile_image[0];
             			$naver_nickname = (string)$xml->response[0]->nickname[0];
                  if($naver_id && $naver_name && $naver_email){
                    $this->memberSocialLogin($_SESSION["social_provider"],$naver_id,$naver_name,$naver_email,$naver_profile_image,$social_obj->use_login_point, $args);
                  }
                  header("Location: ".$_SESSION["final_redirect_uri"]);
                }
              }

              //카카오 설정
              if($args->kakao_rest_key){
                $social_obj->kakao_rest_key = $args->kakao_rest_key;
                $_SESSION["kakao_rest_key"] = $args->kakao_rest_key;
                if($args->kakao_js_key) $social_obj->kakao_js_key = $args->kakao_js_key;

                //IE에서 '이 페이지에서 나가겠습니까?' 창이 뜨는 문제로 인해 php 'header'로 변경함
                if($_SESSION["social_provider"]=='kakao' && $_SESSION["social_oauth_move"]=='y'){
                  unset($_SESSION["social_oauth_move"]);
                  $mt = microtime();
                  $rand = mt_rand();
                  $state = md5($mt . $rand);
                  $oauth_url = 'https://kauth.kakao.com/oauth/authorize?client_id='.$social_obj->kakao_rest_key.'&redirect_uri='.urlencode($redirect_uri).'&response_type=code&state='.$state;
                  header("Location: $oauth_url");
                }

                //카카오 정보 설정 및 최종 로그인
                if($_SESSION["social_provider"]=='kakao' && $_SESSION["kakao_id"] && $_SESSION["kakao_nickname"] && $_SESSION["social_email"]){
                  $this->memberSocialLogin($_SESSION["social_provider"],$_SESSION["kakao_id"],$_SESSION["kakao_nickname"],$_SESSION["social_email"],$_SESSION["kakao_profile_image"],$social_obj->use_login_point, $args);
                  header("Location: ".$_SESSION["final_redirect_uri"]);
                }
              }


              //각종문구 설정
              if(!$args->text_input_email_description){
                $social_obj->text_input_email_description = '임시비번을 전송받을 이메일을 입력해주세요.';
              } else{
                $social_obj->text_input_email_description = $args->text_input_email_description;
              }
              if(!$args->text_alert_input_email){
                $social_obj->text_alert_input_email = '이메일주소를 입력하세요.';
              } else{
                $social_obj->text_alert_input_email = $args->text_alert_input_email;
              }
              if(!$args->text_alert_wrong_email){
                $social_obj->text_alert_wrong_email = '이메일주소를 입력하세요.';
              } else{
                $social_obj->text_alert_wrong_email = $args->text_alert_wrong_email;
              }

              //스킨명 변수설정
              $social_obj->skin = $args->skin;

              //기타 변수설정
              $member_point = 0;
              $social_provider = '';
              $social_code = '';
              if($logged_info){
                $member_point = $oPointModel->getPoint($logged_info->member_srl, true);
                //세션 삭제
                unset($_SESSION["social_email"]);
                unset($_SESSION["social_provider"]);
                unset($_SESSION["social_oauth_move"]);
                unset($_SESSION["twitter_oauth_token"]);
                unset($_SESSION["twitter_oauth_verifier"]);
                unset($_SESSION["twitter_oauth_token_secret"]);
                unset($_SESSION["kakao_access_token"]);
                unset($_SESSION["kakao_id"]);
                unset($_SESSION["kakao_nickname"]);
                unset($_SESSION["kakao_profile_image"]);
              } else{
                $social_provider = $_SESSION["social_provider"];
              }


              //템플릿 변수 설정
              Context::set('social_obj', $social_obj);
              Context::set('colorset', $args->colorset);
              Context::set('member_point', $member_point);
              Context::set('social_provider', $social_provider);
              Context::set('redirect_uri', $redirect_uri);
              Context::set('social_code', $social_code); //카카오 변수

              //템플릿 객체생성 및 스킨파일 컴파일
              $oTemplate = TemplateHandler::getInstance();
              $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
              return $oTemplate->compile($tpl_path, "social_login");
            }
        }


        /* 소셜로그인 함수 */
	      function memberSocialLogin($social_provider,$social_id,$user_name,$email_address,$profile_image,$point_system, $args) {

        		$oModuleModel = getModel('module');
            $oMemberModel = getModel('member');
            $oPointModel = getModel('point');
            $oMemberController = getController('member');
            $oPointController = getController('point');

            //이메일 분할 및 회원정보 설정
            list($email_id, $email_host) = explode('@', $email_address);

            $member_info = $oMemberModel->getMemberInfoByEmailAddress($email_address);
            //회원이 등록되어 있을 경우, 로그인 실행
            if($member_info->user_id){
              $member_config = $oMemberModel->getMemberConfig();
              if($member_config->identifier == 'email_address'){
                $output = $oMemberController->doLogin($email_address);
              } else{
                $output = $oMemberController->doLogin($member_info->user_id);
              }
              //로그인 포인트 부여
              if($point_system==true){
             		$point_config = $oModuleModel->getModuleConfig('point');
            		$cur_point = $oPointModel->getPoint($member_info->member_srl, true);
            		$point = $point_config->login_point;
             		$cur_point += $point;
             		$oPointController->setPoint($member_info->member_srl, $cur_point);
              }
            }

            //회원이 등록되어 있지 않을 경우, 회원가입 등 실행
            if(!$member_info->user_id){
              //회원가입 부분
              $new_member_srl = getNextSequence();
              $social_nickname = $email_id.'_'.$social_provider;
              $social_password = substr($social_id,0,6);
              $member_obj = new stdClass();
           		$member_obj->member_srl = $new_member_srl;
              $member_obj->list_order = -1 * $new_member_srl;
              $member_obj->email_id = $email_id;
              $member_obj->email_host = $email_host;
              $member_obj->user_id = $email_id;
              $member_obj->nick_name = $social_nickname;
              $member_obj->user_name = $user_name;
              $member_obj->email_address = $email_address;
              if(substr(__XE_VERSION__,0,3)>1.8){
                $member_obj->password = $oMemberModel->hashPassword($social_password);
              } else{
                $member_obj->password = md5($social_password);
              }
              $member_obj->find_account_question = 1;
              $member_obj->find_account_answer = $social_password;
   	          $member_obj->allow_mailing = 'N';
              $member_obj->denied = 'N';
 		          $member_obj->allow_message= 'N';
       		    $member_obj->is_admin = 'N';
              $member_output = executeQuery('member.insertMember', $member_obj);

              //회원가입 완료 후
              if($member_output->toBool()){
                //프로필 이미지 설정
                if($profile_image){
                  $ch = curl_init($profile_image);
                  curl_setopt($ch, CURLOPT_HEADER, 0);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                  curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
                  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                  $rawdata = curl_exec($ch);
                  curl_close($ch);
                  mkdir(_XE_PATH_ ."files/temp_social", 0755);
                  $fp = fopen(_XE_PATH_ ."files/temp_social/profile.png","w");
                  fwrite($fp, $rawdata);
                  fclose($fp);
                  //FileHandler::getRemoteFile($profile_image, _XE_PATH_ .'files/temp_social/profile.png');
                  $oMemberController->insertProfileImage($new_member_srl, _XE_PATH_ .'files/temp_social/profile.png');
                  FileHandler::removeDir(_XE_PATH_ .'files/temp_social/');
                }
                //기본그룹지정 및 로그인 부분
                $default_group = $oMemberModel->getDefaultGroup(0);
                $oMemberController->addMemberToGroup($member_obj->member_srl,$default_group->group_srl);
                $member_config = $oMemberModel->getMemberConfig();
                if($member_config->identifier == 'email_address'){
                  $output = $oMemberController->doLogin($email_address);
                } else{
                  $output = $oMemberController->doLogin($member_obj->user_id);
                }
              }

              //회원가입 포인트 부여
              if($point_system==true){
             		$point_config = $oModuleModel->getModuleConfig('point');
             		$cur_point = $oPointModel->getPoint($member_obj->member_srl, true);
             		$point = $point_config->signup_point;
             		$cur_point += $point;
             		$oPointController->setPoint($member_obj->member_srl, $cur_point, 'signup');
              }
              //회원가입 완료 후 회원정보 임시 비밀번호 메일전송
              $email_domain = str_replace('http://','',getFullUrl(''));
              $email_domain = str_replace('https://','',$email_domain);
              $email_domain = str_replace('www','',$email_domain);
              if(!$args->sender_name) $sender_name = '웹마스터';
              if(!$args->sender_email) $sender_email = 'webmaster@'.$email_domain;
              if(!$args->email_title) $email_title = getFullUrl('').'에서 처음으로 소셜로그인('.$social_provider.')하여 회원가입을 하였습니다.';
              if(!$args->text_message_id) $text_message_id = '아이디';
              if(!$args->text_message_temp_pw) $text_message_temp_pw = getFullUrl('','act','dispMemberModifyPassword');
              if(!$args->text_message_change_pw) $text_message_change_pw = '비번변경';
              $oMail = new Mail();
              $oMail->setSender($sender_name, $sender_email);
              $oMail->setReceiptor($user_name, $email_address);
              $oMail->setTitle($email_title);
              $message = $email_title.'<br /><br />';
              $message .= $text_message_id.': '.$member_obj->user_id.'<br />';
              $message .= $text_message_temp_pw.': '.$social_password.'<br /><br />';
              $message .= $text_message_change_pw.': <a href="'.getFullUrl('','act','dispMemberModifyPassword').'" target="_blank">'.$text_message_temp_pw.'</a>';
              $oMail->setContent($message);
              $oMail->send();
            }

            //세션 삭제
            unset($_SESSION["social_email"]);
            unset($_SESSION["social_provider"]);
            unset($_SESSION["social_oauth_move"]);
            unset($_SESSION["twitter_oauth_token"]);
            unset($_SESSION["twitter_oauth_verifier"]);
            unset($_SESSION["twitter_oauth_token_secret"]);
            unset($_SESSION["kakao_access_token"]);
            unset($_SESSION["kakao_id"]);
            unset($_SESSION["kakao_nickname"]);
            unset($_SESSION["kakao_profile_image"]);

            return false;
	      }
    }
?>
