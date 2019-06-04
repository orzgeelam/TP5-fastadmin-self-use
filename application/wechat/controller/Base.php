<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Controller;
use addons\rlsms\Rlsms;

class Base extends Frontend
{
	public $cookie_expire = 604800; // cookie保存时间
	public $qiniu_image   = "http://image.xilukeji.com"; // 七牛云链接地址
	protected $Local_Debug   = false; // 本地调试开关
	// protected $Local_Debug = true; // 本地调试开关

	public function _initialize()
	{
		parent::_initialize();
		$this->platform    = get_platform(); // 获取当前平台  pc、app、weixin
		$this->current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; // 当前地址
		$this->assign('http_host','http://' . $_SERVER['HTTP_HOST']);
		$current_action    = strtolower($this->request->action()); //获取当前页面方法名
		if ($this->Local_Debug) {
			$this->local_debug(); // 设置本地调试用户信息
		}
		$recuid = input('recuid') ?: 0;
		if ($recuid) {
			cookie("recUid", $recuid, 900);
		}
		$this->get_user(); // 获取用户信息
		if (!$this->Local_Debug) {
			if (!in_array($current_action, ['auth_back', 'upload'])) {
				$this->is_auth(); // 微信授权登录
			}
		}
		$this->assign('meta_title', '');
		$this->assign('user_id', $this->user_id);
		$this->assign('userInfo', $this->userInfo);
		$this->assign('jsapi', $this->set_jsapi()); // 设置jsapi微信配置
		$this->assign('wechatShare', $this->set_share()); // 设置微信分享
		$this->assign('wechatShare1', json_encode($this->set_share())); // 微信分享内容打印
		$this->assign('platform', $this->platform);
		if (cookie('is_login') == "null" || !cookie('is_login')) {
			$this->assign('is_login', 0);
		} else {
			$this->assign('is_login', 1);
		}
		if ($this->userInfo['vip_expire_date'] >= date('Y-m-d', time())) {
			$this->assign('is_vip', 1);
		} else {
			$this->assign('is_vip', 0);
		}
	}

	/**
	 * 本地调试cookie
	 */
	protected function local_debug()
	{
		cookie('openid', 'obL6dw7iSGTORMhSZYioIVYrWTG0', $this->cookie_expire);
		cookie('user_id', 1, $this->cookie_expire);
	}

	/**
	 * 设置用户信息
	 */
	protected function get_user()
	{
		$uid            = cookie('register_uid');
		$uid            = $uid ? $uid : cookie('login_uid');
		$openid         = cookie('openid');
		$this->openid   = $openid;
		$user_id        = cookie('user_id') ? cookie('user_id') : $uid;
		$user_id        = empty($user_id) ? 0 : (int)$user_id;
		$this->user_id  = $user_id;
		$user_model     = model('user');
		$this->userInfo = $user_model->where('id', $this->user_id)->find();
		/*if ($openid) {
			// 每次获取微信用户信息 更新用户信息
			$wx_model = new \app\index\controller\Weixin();
			$wx_user  = $wx_model->get_user_info($openid);
			if (isset($wx_user['subscribe']) && $wx_user['subscribe'] == 1) {
				cookie('attention', null);
			}
		}*/
	}

	/**
	 * 设置jsapi微信配置
	 */
	protected function set_jsapi()
	{
		if ($this->platform == 'weixin') {
			// 分享配置
			$jsapi_config = ['onMenuShareAppMessage', 'onMenuShareTimeline', 'previewImage', 'chooseImage', 'uploadImage', 'chooseWXPay', 'getLocation'];
			$jsapi        = action('index/Weixin/jsapi', [$jsapi_config]);
		} else {
			$jsapi = "";
		}
		return $jsapi;
	}

	/**
	 * 设置分享内容
	 */
	protected function set_share()
	{
		$share_url = $this->current_url;
		if ($this->userInfo['vip_expire_date'] >= date('Y-m-d', time())) {
			if (strpos($share_url, '?') == false) {
				$share_url .= '?recuid=' . $this->user_id;
			} else {
				$share_url .= '&recuid=' . $this->user_id;
			}
		}
		//基本分享信息
		$wechatShare = [
			'title'  => config('site.share_title'),
			'desc'   => config('site.share_desc'),
			'imgUrl' => $this->qiniu_image . config('site.share_image'),
			'link'   => $share_url,
		];
		return $wechatShare;
	}

	/**
	 * 微信登录检测
	 */
	protected function is_auth()
	{
		if (empty($this->openid)) {
			cookie('openid', null);
			cookie('user_id', null);
			$this->wechat();
			exit;
		} else {
			if (empty($this->userInfo) || $this->userInfo['openid'] != $this->openid) {
				cookie('openid', null);
				cookie('user_id', null);
				$this->wechat();
				exit;
			}
			return $this->user_id;
		}
	}

	/**
	 * 调用Weixin/auth方法
	 */
	public function wechat()
	{
		$cookie_time = $this->cookie_expire;
		cookie('target_url', $this->current_url, $cookie_time);
		// Url::root('/cityenglish/index.php');
		$back_url = url('index/Weixin/auth_back', null, false, true);
		action('index/Weixin/auth', [$back_url, 'snsapi_userinfo']);
	}

	/**
	 * 发送短信验证码
	 * $mobile 短信接收号码
	 * $type 短息发送模板
	 */
	public function send($data = [])
	{
		$sms         = new \addons\rlsms\Rlsms();
		$mobile      = $data['mobile'];
		$type        = $data['type'];
		$rand        = rand(100000, 999999);
		$cookie_time = 900;
		cookie($mobile . '_codenum', $rand, $cookie_time);
		$params['mobile'] = $mobile;
		$params['code']   = $rand;
		$params['event']  = $type;
		$res              = $sms->smsSend($params);
		return $res;
		exit;
	}
}
