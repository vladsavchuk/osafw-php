<?php

class LoginController extends FwController {
    const route_default_action = '';
    public $model_name = 'Users';

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction() {
        $item = req('item');
        if ( !isset($item) ){
            #defaults
            $item=array(
            );
        }
        $ps = array(
            'i'  => $item,
        );

        return $ps;
    }

    public function SaveAction() {
        global $CONFIG;

        #special case login
        if ( req('save_type')=='facebook' ){
            $this->SaveFacebook();
            return;
        }

        try{
            $login  = trim($_REQUEST['item']['login']);
            $pwd    = $_REQUEST['item']['pwdh'];
            if ($_REQUEST["item"]["chpwd"] == "1") $pwd = $_REQUEST['item']['pwd'];
            $pwd = substr(trim($pwd), 0, 32);

            if (!strlen($login) || !strlen($pwd) ) {
                $this->ferr("REGISTER", True);
                throw new ApplicationException("");
            }

            $hU = db_row("select * from users where email=".dbq($login)." and pwd=".dbq($pwd));
            if ( !isset($hU['access_level']) || $hU['status']!=0 ) throw new ApplicationException(lng("User Authentication Error"));

            $this->model->do_login( $hU['id'] );

            $gourl = reqs('gourl');
            if ($gourl && !preg_match("/^http/i", $gourl)){ #if url set and not external url (hack!) given
                fw::redirect($gourl);
            }else{
                fw::redirect($CONFIG['LOGGED_DEFAULT_URL']);
            }

        }catch( ApplicationException $ex){
            $this->fw->G['err_ctr']=reqi('err_ctr')+1;
            $this->set_form_error($ex->getMessage());
            $this->route_redirect("Index");
        }
    }

    public function SaveFacebook() {

        $item=FormUtils::form2dbhash($_REQUEST, 'access_token id email first_name last_name name username gender link locale timezone verified');
        #TODO better validate
        if (!$item['access_token'] || !$item['id']) throw new ApplicationException("Wrong facebook data", 1);

        /*
        $fb = new Facebook(array(
            'appId'  => $GLOBALS['FACEBOOK_APP_ID'],
            'secret' => $GLOBALS['FACEBOOK_APP_SECRET'],
        ));
        $fb_user_id = $facebook->getUser();
        $user_profile = $facebook->api('/me');
        */

        #check if such user exists
        $users_id=0;

        #first - check by email
        $hU=$this->model->one_by_email($item['email']);
        if ($hU['id']){
            $users_id=$hU['id'];
        }

        if (!$users_id){
            #now check by facebook email
            $hU=db_row("select * from users where fb_email=".dbq($item['email']) );
            if ($hU['id']) $users_id=$hU['id'];
        }

        if (!$users_id){
            #now check by facebook id
            $hU=db_row("select * from users where fb_id=".dbq($item['id']) );
            if ($hU['id']) $users_id=$hU['id'];
        }

        if ($users_id){
            #update user's missing data from facebook
            $vars=array(
                'fb_access_token'   => $item['access_token'],
            );

            if ($hU['sex']!= ($item['gender']=='male' ? 1 : 0) ) $vars['sex']=$item['gender']=='male' ? 1 : 0;
            if (!$hU['fname']) $vars['fname']=$item['first_name'];
            if (!$hU['lname']) $vars['lname']=$item['last_name'];
            if ($hU['fb_email']!=$item['email'] && $item['email']) $vars['fb_email']=$item['email'];

            if (!$hU['fb_id'])          $vars['fb_id']          =$item['id'];
            if (!$hU['fb_link'])        $vars['fb_link']        =$item['link'];
            if (!$hU['fb_locale'])      $vars['fb_locale']      =$item['locale'];
            if (!$hU['fb_name'])        $vars['fb_name']        =$item['name'];
            if (!$hU['fb_timezone'])    $vars['fb_timezone']    =$item['timezone'];
            if (!$hU['fb_username'])    $vars['fb_username']    =$item['username'];
            if (!$hU['fb_verified'])    $vars['fb_verified']    =$item['verified']=='true' ? 1 : 0;
            if (!$hU['fb_picture_url']) $vars['fb_picture_url'] ='http://graph.facebook.com/'.$item['username'].'/picture';

            db_update('users', $vars, $users_id);

        }else{
            #register user first if new
            $users_id=$this->model->add(array(
                'email'     => $item['email'],
                #'phone'  => $item['phone'],
                'nick'      => $item['name'],
                'sex'       => $item['gender']=='male' ? 1 : 0,
                'fname'     => $item['first_name'],
                'lname'     => $item['last_name'],

                'fb_id'     => $item['id'],
                'fb_link'   => $item['link'],
                'fb_locale'   => $item['locale'],
                'fb_name'     => $item['name'],
                'fb_timezone' => $item['timezone'],
                'fb_username' => $item['username'],
                'fb_verified' => $item['verified']=='true' ? 1 : 0,
                'fb_picture_url' => 'http://graph.facebook.com/'.$item['username'].'/picture',
                'fb_access_token'   => $item['access_token'],
            ));
        }

        #automatically login the user
        $_SESSION['is_just_registered']=1;
        $this->model->do_login($users_id);

        $ps=array(
            'status'    => 0,
            'err_msg'   => '',
        );
        parse_json($ps);
    }

    public function LogoffAction() {
        global $CONFIG;

        //delete session
        $_SESSION = array();
        session_destroy();

        $this->model->remove_perm_cookie();

        fw::redirect($CONFIG['UNLOGGED_DEFAULT_URL']);
    }

}//end of class

?>