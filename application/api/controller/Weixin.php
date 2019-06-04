<?php

namespace app\api\controller;

use app\common\controller\Api;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\Text;
use EasyWeChat\Payment\Order as EasyOrder;
use think\Db;
use Exception;

class Weixin extends Api
{
	private $option       = []; //微信配置参数
	private $wxpay_config = []; //微信支付配置

	public function _initialize()
	{
		if (!defined('__ROOT__')) {
			define('__ROOT__', $this->request->root());
		}
		$options = [
			'debug'  => true,
			'app_id' => config("site")['wechat_appid'],
			'secret' => config("site")['wechat_appsecret'],
			'token'  => config("site")['wechat_apptoken'],
			'log'    => [
				'level' => 'debug',
				'file'  => ROOT_PATH . 'easywechat.log', // XXX: 绝对路径！！！！
			],
		];
		$this->wxpay_config
			= array_merge($options,
			[
				'payment' => [
					'merchant_id' => config("site")['wechat_merchant_id'],
					'key'         => config("site")['wechat_key'],
				],
			]
		);
		$this->options = $options;
		try {
			$this->app = new Application($options);
		} catch (Exception $e) {
			dump('初始化失败：' . $e->getMessage());
		}
	}

	// 服务器配置回调方法
	// 注意，这里的 $message 不仅仅是用户发来的消息，也可能是事件
	// 当 $message->MsgType 为 event 时为事件
	public function run()
	{
		$server = $this->app->server;
		$server->setMessageHandler(function ($message) {
			if ($message->MsgType == 'event') {
				$this->openid = $message->FromUserName;
				switch (strtolower($message->Event)) {
					case 'subscribe':
						return config('site.wechat_first_subscribe');
						break;
					case 'unsubscribe':
						db('user')->where(['openid' => $this->openid])->update(['subscribe' => 0]);
						return "";
						break;
					case 'scan':
						$num = $message->EventKey;
						return $this->after_scan($num, $this->openid);
						break;
					default:
						return "";
						break;
				}
			} else {
				switch ($message->MsgType) {
					case 'text':
						return '';
						break;
					case 'image':
						return '';
						break;
					default:
						return "未处理的回复类型:{$message->MsgType}";
				}
			}
		});
		$response = $server->serve();
		$response->send();
		exit;
	}

	// 下载微信上传的图片
	public function download_img($media_id = 0)
	{
		$temporary = $this->app->material_temporary;
		$content = $temporary->getStream($media_id);
		$ext = 'jpg';
		//将远程图片保存到本地
		$tmp_name = tempnam(sys_get_temp_dir(), 'weixin_avatar') . '.' . $ext;
		file_put_contents($tmp_name, $content);
		$result = action('api/Common/upload', [$tmp_name]);
		if ($result === false) {
			$ret = ['status' => 0, 'info' => '上传失败'];
		} else {
			$res = json_decode($result, true);
			if ($res['status']) {
				$ret = ['status' => 1, 'info' => '上传成功', 'id' => $res['id'], 'src' => $res['path']];
			} else {
				$ret = ['status' => 0, 'info' => '上传失败，返回格式不对'];
			}
		}
		exit(json_encode($ret));
	}

	// 下载微信上传的音频
	public function download_audio($media_id = 0)
	{
		$temporary = $this->app->material_temporary;
		$content = $temporary->getStream($media_id);
		$ext = 'jpg';
		//将远程图片保存到本地
		$tmp_name = tempnam(sys_get_temp_dir(), 'weixin_audio') . '.' . $ext;
		file_put_contents($tmp_name, $content);
		$name = date('YmdHis');
		$file = [
			'savepath' => 'audioture/',
			'savename' => $name . '.' . $ext,
			'tmp_name' => $tmp_name,
		];
		$url = url('index/Upload/upload', ['dir' => 'audios'], true, true);
		$attachment_model = model('admin/Attachment');
		$result = $attachment_model->curlUploadFile($url, $tmp_name, 'audios');
		if ($result === false) {
			$ret = ['status' => 0, 'info' => '上传失败'];
		} else {
			$res = json_decode($result, true);
			if ($res['status']) {
				$ret = ['status' => 1, 'info' => '上传成功', 'id' => $res['id'], 'src' => $res['path']];
			} else {
				$ret = ['status' => 0, 'info' => '上传失败，返回格式不对'];
			}
		}
		exit(json_encode($ret));
	}

	//扫码之后的处理
	public function after_scan($id, $openid)
	{
		// $code = db('wxqrcode')->where('id', $id)->find();
		// $user = db('user')->where(['openid' => $openid])->find();
		// if ($user['rec_time'] == '') {
		// 	db('user')->where(['openid' => $openid])->update(['recuid' => $code['relate_id'], 'rec_time' => date('Y-m-d H:i:s', time())]);
		// }
	}

	//授权登录
	public function auth($redirect_url, $scopes = 'snsapi_userinfo')
	{
		$this->option = $this->options;
		$this->option['oauth'] = [
			'scopes'   => [$scopes],
			'callback' => $redirect_url,
		];
		try {
			$app = new Application($this->option);
			$response = $app->oauth->redirect();
			$response->send();
		} catch (Exception $e) {
			dump("授权失败." . $e->getMessage());
		}
	}

	// 授权登录后的回调页面
	public function auth_back()
	{
		$code = input('code');
		if (empty($code)) {
			$this->error('code 参数缺少');
		}
		if (isset(cookie('code')['code']) && cookie('code')['code'] == $code) {
			$wechat_userinfo = cookie('code')['wechat_userinfo'];
		} else {
			$oauth = $this->app->oauth;
			// 获取 OAuth 授权结果用户信息
			$user = $oauth->user();
			$wechat_userinfo = $user->toArray();
			$wechat_userinfo = $wechat_userinfo['original'];
			$cookie = [
				'code'            => $code,
				'wechat_userinfo' => $wechat_userinfo,
			];
			cookie('code', $cookie, 60 * 24);
		}
		$openid = $wechat_userinfo['openid'];
		$nickname = $wechat_userinfo['nickname'];
		$sex = $wechat_userinfo['sex'];
		$headimgurl = $wechat_userinfo['headimgurl'];
		$time = date('Y-m-d H:i:s', time());
		if (!empty($openid)) {
			$userModel = model('user');
			$has = $userModel->where(['openid' => $openid])->find();
			$data['openid'] = $openid;
			$data['nickname'] = $nickname;
			$data['headimage'] = $headimgurl;
			$data['update_time'] = $time;
			$data['cnname'] = $nickname;
			$data['avatar'] = $headimgurl;
			$data['nickname'] = $nickname;
			$data['sex'] = $sex;
			$cookie_time = 86400 * 7;
			$recUid = cookie("recUid") ?: 0;
			if (empty($has)) {
				if (!empty($recUid)) {
					$data['recuid'] = $recUid;
					$data['rec_time'] = date('Y-m-d H:i:s', time());
				}
				$res = $userModel->insert($data);
				if (!$res) {
					dump($userModel->getError());
				}
				$uid = $userModel->getLastInsID();
			} else {
				if (empty($has['recuid']) && !empty($recUid) && $recUid != $has['id']) {
					$data['recuid'] = $recUid;
				}
				$res = $userModel->where(['openid' => $openid])->update($data);
				if ($res === false) {
					dump(model('user')->getError());
				}
				$uid = $has['id'];
			}
			cookie('attention', 1, 60);
			cookie('openid', $openid, $cookie_time);
			cookie('user_id', $uid, $cookie_time);
		}
		session('wechat_user', $wechat_userinfo);
		$targetUrl = !cookie('target_url') ? url('index/index/index') : cookie('target_url');
		$this->redirect($targetUrl);
	}

	//生成jsapi 配置
	public function jsapi($APIs, $url = '')
	{
		if (get_platform() != 'weixin') {
			return [];
		}
		$js = $this->app->js;
		if ($url) {
			$js->setUrl($url);
		}
		return $js->config($APIs, $debug = false, $beta = false, $json = true);
	}

	//初始化需要支付的微信配置
	public function cert_pay_config()
	{
		$this->wxpay_config['payment']['cert_path'] = realpath('./cert/apiclient_cert.pem');
		$this->wxpay_config['payment']['key_path'] = realpath('./cert/apiclient_key.pem');
	}

	//生成二维码
	public function qrcode1($code = null, $expire = 0)
	{
		$app = $this->app;
		$qrcode = $app->qrcode;
		try {
			if ($expire) {
				$result = $qrcode->temporary($code, $expire);
				$ticket = $result->ticket; // 或者 $result['ticket']
				$expireSeconds = $result->expire_seconds; // 有效秒数
				$url = $result->url; // 二维码图片解析后的地址，开发者可根据该地址自行生成需要的二维码图片
			} else {
				//创建永久二维码
				$result = $qrcode->forever($code); // 或者 $qrcode->forever("foo");
				$ticket = $result->ticket; // 或者 $result['ticket']
			}
			$url = $qrcode->url($ticket);
			return [
				'status' => 1,
				'info'   => '生成成功',
				'data'   => ['ticket' => $ticket, 'url' => $url],
			];
		} catch (Exception $e) {
			return ['status' => 0, 'info' => $e->getMessage()];
		}
	}

	//创建微信统一订单
	public function union_order($orderInfo)
	{
		if ($orderInfo['money'] * 100 < 1) {
			return false;
		}
		$app = new Application($this->wxpay_config);
		$order_no = $orderInfo['ordernum'];
		$payment = $app->payment;
		$attributes = [
			'openid'       => $orderInfo['openid'],
			'body'         => $orderInfo['body'],
			'detail'       => $orderInfo['detail'],
			'out_trade_no' => $order_no,
			'total_fee'    => $orderInfo['money'] * 100, //APP_DEBUG ? 1 :
			'trade_type'   => 'JSAPI',
			'time_start'   => date('YmdHis'),
			'time_expire'  => date('YmdHis', strtotime('+1 year')),
			// 支付回调 支付结果通知网址，如果不设置则会使用配置里的默认地址
			// 'notify_url'   => XILUDomain().'/public/index/index/pay_success/ordernum'.$order_no,
			'notify_url'   => 'http://f.xilukeji.com/shangchang/public/index.php/index/weixin/wxOrderNotify/slog_force_client_id/slog_2c9a99',
		];
		$order = new EasyOrder($attributes);
		try {
			$result = $payment->prepare($order);
			$prepayId = $result->prepay_id;
			if (!$prepayId) {
				return false;
			}
			$json = $payment->configForPayment($prepayId);
			return $json;
		} catch (Exception $e) {
			return false;
		}
	}

	// 查询订单
	public function wxOrderQuery($out_trade_no)
	{
		$app = new Application($this->wxpay_config);
		$payment = $app->payment;
		$result = $payment->query($out_trade_no);
		return $result;
	}

	//订单支付回调
	public function wxOrderNotify()
	{
		$app = new Application($this->wxpay_config);
		$response = $app->payment->handleNotify(function ($notify, $successful) {
			// 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
			$order_info = db('vip_order')->where('ordernum', $notify->out_trade_no)->find();
			if (empty($order_info)) {
				return 'Order not exist.';// 告诉微信，我已经处理完了，订单没找到，别再通知我了
			}
			// 检查订单是否为未支付状态
			if ($order_info['checkinfo'] != 1) {
				return true; // 已经支付成功了就不再更新了
			}
			// 用户是否支付成功
			if (!$successful) {
				return false;
			}
			Db::startTrans();
			$transaction_id = $notify->transaction_id;
			$afterOrderPay = $this->afterOrderPay($order_info, $transaction_id);
			if (empty($afterOrderPay['status'])) {
				Db::rollback();
				return false;
			}
			Db::commit();
			return true;
		});
		$response->send();
	}

	//支付回调
	public function afterOrderPay($order, $transaction_id = '')
	{
		$info = [
			'id'        => $order['id'],
			'pay_time'  => date('Y-m-d H:i:s', time()),
			'checkinfo' => 2,
		];
		$user = db('user')->where('id', $order['uid'])->find();
		if ($user['vip_expire_date'] < date('Y-m-d', time())) {
			$info['start_date'] = date('Y-m-d', time());
			$info['end_date'] = date('Y-m-d', strtotime("+" . $order['number'] . " month", strtotime($info['start_date'])));
		} else {
			$info['start_date'] = $user['vip_expire_date'];
			$info['end_date'] = date('Y-m-d', strtotime("+" . $order['number'] . " month", strtotime($info['start_date'])));
		}
		if ($user['recuid'] > 0) {
			$info['recuid'] = $user['recuid'];
			$recuser = db('user')->where('id', $user['recuid'])->find();
			$vip_lecel = db('vip_level')->column('id,rate');
			$rate = $vip_lecel[$recuser['vip_level']];
			$info['brokerage'] = $order['pay'] * $rate / 100;
			$brokerage_data = [
				'uid'         => $recuser['id'],
				'subordinate' => $user['id'],
				'create_time' => date('Y-m-d H:i:s', time()),
				'update_time' => date('Y-m-d H:i:s', time()),
				'type'        => 1,
				'sum'         => $info['brokerage'],
			];
			$res3 = db('brokerage')->insert($brokerage_data);
		}
		$res1 = db('vip_order')->update($info);
		$user_data = [
			'id'              => $user['id'],
			'vip_expire_date' => $info['end_date'],
			'update_time'     => date('Y-m-d H:i:s', time()),
		];
		if ($user['vip_level'] == 0) {
			$user_data['vip_level'] = 1;
		}
		$res2 = db('user')->update($user_data);
		if ($res1 && $res2) {
			$result = ['status' => true, 'msg' => '支付成功'];
		} else {
			$result = ['status' => false, 'msg' => '支付失败'];
		}
		return $result;
	}

	/**
	 * 申请退款 只 支持1次性退款  退款表 id|apply_time|order_no|status|to_back_fee|reason|back_time
	 * @param $order_no    订单号
	 * @param $to_back_fee int 退款费 单位分
	 * @param $reason      string 退款理由
	 */
	public function applyRefund($order_no, $to_back_fee, $reason = '')
	{
		$refundModel = model('wxrefund'); //可能换表名
		$this->cert_pay_config();
		$app = new Application($this->wxpay_config);
		$payment = $app->payment;
		$exist_refund = $refundModel->where(['order_no' => $order_no, 'status' => 2])->find();
		if ($exist_refund) {
			return true;
		}
		$orderModel = model('Order'); //TODO 可能替换
		$orderInfo = $orderModel->where(['order_no' => $order_no])->find();
		if (!$orderInfo) {
			return false;
		}
		//测试情况 检测退款金额
		if (config('app_debug')) {
			$orderInfo['order_amount'] = 0.01;
			$to_back_fee = 1;
		}
		if ($to_back_fee > 100 * $orderInfo['order_amount']) {
			return false;
		}
		$insertData = [
			'apply_time'  => time(),
			'order_no'    => $order_no,
			'status'      => 1,
			'to_back_fee' => $to_back_fee,
			'reason'      => $reason,
		];
		$refundModel->insert($insertData);
		$refundId = $refundModel->getLastInsID();
		try {
			$result = $payment->refund($order_no, $refundId, $orderInfo['order_amount'] * 100, $to_back_fee); // 总金额 100， 退款 80，操作员：商户号
			if ('SUCCESS' != $result['return_code']) {
				return false;
			} else {
				$refundModel->where(['id' => $refundId])->update(['back_time' => time(), 'status' => 2]);
				return true;
			}
		} catch (Exception $e) {
			$refundModel->delete($refundId);
			return false;
		}
	}

	/**
	 * 申请提现订单  申请提现的记录表结果 id|uid|money|status|create_time|check_time|reason|order_no|reason
	 * @param $withdraw array 提现记录数据
	 */
	public function applyWithDraw($withdraw, $openid)
	{
		if ($withdraw['check_time']) {
			return [
				'code'   => 0,
				'status' => true,
			];
		}
		$this->cert_pay_config();
		$app = new Application($this->wxpay_config);
		$merchantPay = $app->merchant_pay;
		$order_no = getOrderNo();
		$merchantPayData = [
			'partner_trade_no' => $order_no . $withdraw['id'], //随机字符串作为订单号，跟红包和支付一个概念。
			'openid'           => $openid, //收款人的openid
			'check_name'       => 'NO_CHECK', //文档中三分钟校验实名的方法NO_CHECK OPTION_CHECK FORCE_CHECK
			'amount'           => config('app_debug') ? 100 : $withdraw['money'] * 100, //单位为分 ，最少不能小于1元
			'desc'             => '【' . config('web_site_title') . '】用户提现',
			'spbill_create_ip' => get_client_ip(), //发起交易的IP地址
		];
		try {
			$result = $merchantPay->send($merchantPayData);
			if ('SUCCESS' == $result->return_code && 'SUCCESS' == $result->result_code) {
				M('withdraw')->where(['id' => $withdraw['id']])->save(['order_no' => $order_no]);
				return [
					'code'   => 0,
					'status' => true,
				];
			} else {
				return [
					'code'   => 2,
					'info'   => "{$result->err_code}：{$result->err_code_des}",
					'status' => false,
				];
			}
		} catch (Exception $e) {
			return [
				'code'   => 1,
				'info'   => $e->getMessage(),
				'status' => false,
			];
		}
	}

	//发送微信
	public function send_custom($openid, $message)
	{
		$staff = $this->app->staff;
		try {
			$ret = $staff->message($message)->to($openid)->send();
			return $ret->errcode == 0;
		} catch (Exception $e) {
			return false;
		}
	}

	public function get_user_info($openid)
	{
		$userService = $this->app->user;
		try {
			return $userService->get($openid);
		} catch (Exception $e) {
			dump("获取信息失败");
			return false;
		}
	}

	/**
	 * 发送模板消息
	 * @param openid 用户openid
	 * @param $templateId 消息模板ID
	 * @param $data       = [
	 *                    "first"    => "恭喜你购买成功！",
	 *                    "keynote1" => "巧克力",
	 *                    "keynote2" => "39.8元",
	 *                    "keynote3" => "2014年9月16日",
	 *                    "remark"   => "欢迎再次购买！",
	 *                    ];
	 * @param $link
	 * @param $color
	 */
	public function send_template($openid, $templateId, $data, $link = '', $color = '')
	{
		$notice = $this->app->notice;
		if ($color) {
			$notice->color($color);
		}
		if ($link) {
			$notice->url($link);
		}
		try {
			$notice->to($openid)->template($templateId)->data($data)->send();
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	// 创建用户
	public function insert_user()
	{
		$userModel = model('user');
		$has = $userModel->where(['openid' => $this->openid])->find();
		if (!$has) {
			$userService = $this->app->user;
			try {
				$wechat_userinfo = $userService->get($this->openid);
				$nickname = isset($wechat_userinfo['nickname']) ? $wechat_userinfo['nickname'] : '';
				$sex = isset($wechat_userinfo['sex']) ? $wechat_userinfo['sex'] : '';
				$headimgurl = isset($wechat_userinfo['headimgurl']) ? $wechat_userinfo['headimgurl'] : '';
				$data = [
					'openid'      => $this->openid,
					'sex'         => $sex,
					'nickname'    => $nickname,
					'avatar'      => $headimgurl,
					'update_time' => date('Y-m-d H:i:s', time()),
					'subscribe'   => 1,
				];
				$user_model = model('user');
				$user_model->insert($data);
				return true;
			} catch (Exception $e) {
				return true;
			}
		} else {
			db('user')->where(['openid' => $this->openid])->update(['subscribe' => 1]);
			return true;
		}
	}
}
