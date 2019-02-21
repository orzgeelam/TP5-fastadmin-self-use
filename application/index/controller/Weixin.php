<?php
namespace app\index\controller;

use app\api\controller\Common;
use app\common\controller\Frontend;
use app\index\model\Order;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\Text;
use EasyWeChat\Payment\Order as EasyOrder;
use think\Controller;
use think\Db;

class Weixin extends Controller
{
	private $option       = []; //微信配置参数
	private $wxpay_config = [];
	private $wechat       = []; //微信配置参数

	public function _initialize()
	{
		if (!defined('__ROOT__')) {
			// $_root = rtrim(dirname($this->request->root()), '/');
			// define('__ROOT__', (('/' == $_root || '\\' == $_root) ? '' : $_root));
			define('__ROOT__', $this->request->root());
		}
		// $course_config = config('course_config');
		$options = [
			'debug'  => true,
			'app_id' => config("site")['wechat_appid'],
			'secret' => config("site")['wechat_appsecret'],
			'token'  => config("site")['wechat_apptoken'],
			'log'    => [
				'level' => 'debug',
				'file'  => ROOT_PATH.'easywechat.log', // XXX: 绝对路径！！！！
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
		//        dump($this->wxpay_config);die;
		$this->wechat_social_config
			           = array_merge([
			'wechat' => [
				'client_id'     => config('wechat_appid'),
				'client_secret' => config('wechat_appsecret'),
			],
		], $options
		);
		$this->options = $options;
		try {
			$this->app = new Application($options);
		} catch (\Exception $e) {
			dump('初始化失败：'.$e->getMessage());
		}
	}

	public function run()
	{
		// exit($_GET['echostr']);
		$server = $this->app->server;
		$server->setMessageHandler(function ($message)
		{
			// 注意，这里的 $message 不仅仅是用户发来的消息，也可能是事件
			// 当 $message->MsgType 为 event 时为事件
			// pubu('进来了拉拉米dsdsaasdsddsd');
			// ptrace($message);
			if ($message->MsgType == 'event') {
				$this->openid = $message->FromUserName;
				switch (strtolower($message->Event)) {
					case 'subscribe':
						$userModel = model('wxuser');
						$has       = $userModel->where(['openid' => $this->openid])->find();
						if (!$has) {
							// ptrace('未关注扫码后插入用户');
							$userService = $this->app->user;
							try {
								$wechat_userinfo = $userService->get($this->openid);
								// ptrace($wechat_userinfo);
								$nickname   = isset($wechat_userinfo['nickname']) ? $wechat_userinfo['nickname'] : '';
								$sex        = isset($wechat_userinfo['sex']) ? $wechat_userinfo['sex'] : '';
								$headimgurl = isset($wechat_userinfo['headimgurl']) ? $wechat_userinfo['headimgurl'] : '';
								$data       = [
									'openid'      => $this->openid,
									'sex'         => $sex,
									'nickname'    => $nickname,
									'headimage'   => $headimgurl,
									'avatar'      => 0,
									'recUid'      => 0,
									'create_time' => date('Y-m-d H:i:s', time()),
									'subscribe'   => 1,
								];
								$user_model = model('wxuser');
								$res        = $user_model->insert($data);
								if ($res) {
									$newid = $user_model->getLastInsID();
								}
							} catch (\exception $e) {
								// pubu("获取openid为{$this->openid}的关注用户信息失败，原因：{$e->getMessage()}");
								return true;
							}
						} else {
							db('wxuser')->where(['openid' => $this->openid])->update(['subscribe' => 1]);
						}
						//点击关注生成二维码和水印
						if (!empty($message->EventKey) && false === stripos($message->EventKey, 'last_trade_no_')) {
							$num = trim($message->EventKey, 'qrscene_');
							$this->after_scan($num, $this->openid);
						}
						return config('site.wechat_first_subscribe');
						break;
					case 'unsubscribe':
						db('wxuser')->where(['openid' => $this->openid])->update(['subscribe' => 0]);
						break;
					case 'scan':
						$num = $message->EventKey;
						return $this->after_scan($num, $this->openid);
						break;
					default:
						# code...
						break;
				}
			} else {
				switch ($message->MsgType) {
					case 'text':
						if ('wechat_debug' == $message->Content) {
							return url('/index/Weixin/debug', [], '', true);
						} else {
							$keyword = $message->Content;
							if ($keyword == config('post_key')) {
								$user_id   = db('wxuser')->where(['openid' => $message->FromUserName])->value('id');
								$test      = new Test();
								$image     = $test->user_qrcode($user_id);
								$temporary = $this->app->material_temporary;
								$result    = $temporary->uploadImage($image);
								$image     = new Image(['media_id' => $result['media_id']]);
								try {
									//$app->staff->message($image)->to($openid)->send();
									return $image;
								} catch (\Exception $e) {
									trace($e->getMessage());
								}
								// return "好了";
							} else {
								$content = db('wxkeyword')->where(['keyword' => $keyword, 'status' => 1])->find();
								if ($content) {
									return $content['reply'];
								} else {
									return "抱歉，没有找到相关消息";
								}
							}
						}
						break;
						// ....
						break;
					case 'image':
						// ...
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
		$content   = $temporary->getStream($media_id);
		$ext       = 'jpg';
		db('wxlog')->insert(['content' => $media_id, 'create_time' => date('Y-m-d H:i:s')]);
		//将远程图片保存到本地
		$tmp_name = tempnam(sys_get_temp_dir(), 'weixin_avatar').'.'.$ext;
		file_put_contents($tmp_name, $content);
		$result = action('api/Common/upload', [$tmp_name]);
		if ($result === false) {
			$ret = ['status' => 0, 'info' => '上传失败'];
		} else {
			$res = json_decode($result, true);
			if ($res['status']) {
				$ret = ['status' => 1, 'info' => '上传成功', 'id' => $res['id'], 'src' => $res['path']];
			} else {
				// ptrace($res);
				$ret = ['status' => 0, 'info' => '上传失败，返回格式不对'];
			}
		}
		// trace($ret);
		// ptrace($ret);
		exit(json_encode($ret));
	}

	// 下载微信上传的音频
	public function download_audio($media_id = 0)
	{
		$temporary = $this->app->material_temporary;
		$content   = $temporary->getStream($media_id);
		$ext       = 'jpg';
		//将远程图片保存到本地
		$tmp_name = tempnam(sys_get_temp_dir(), 'weixin_audio').'.'.$ext;
		file_put_contents($tmp_name, $content);
		$name = date('YmdHis');
		$file = [
			'savepath' => 'audioture/',
			'savename' => $name.'.'.$ext,
			'tmp_name' => $tmp_name,
		];
		// ptrace($media_id);
		// ptrace($tmp_name);
		// trace($media_id);
		// trace($tmp_name);
		$url              = url('index/Upload/upload', ['dir' => 'audios'], true, true);
		$attachment_model = model('admin/Attachment');
		$result           = $attachment_model->curlUploadFile($url, $tmp_name, 'audios');
		// ptrace('调试');
		// ptrace($result);
		if ($result === false) {
			$ret = ['status' => 0, 'info' => '上传失败'];
		} else {
			$res = json_decode($result, true);
			if ($res['status']) {
				$ret = ['status' => 1, 'info' => '上传成功', 'id' => $res['id'], 'src' => $res['path']];
			} else {
				// ptrace($res);
				$ret = ['status' => 0, 'info' => '上传失败，返回格式不对'];
			}
		}
		// trace($ret);
		// ptrace($ret);
		exit(json_encode($ret));
	}

	//扫码之后的处理
	public function after_scan($id, $openid)
	{
		//ptrace($id);
		if (mb_substr($id, 0, 4) == 'user') {
			//是关注扫码过来的
			$p_id = mb_substr($id, 4);
			if ($p_id && $openid) {
				$id = db('wxuser')->where(['openid' => $openid])->value('id');//被邀请人的id
				if ($p_id != $id) {
					$scan_user = db('user_sub')->field('id')->where(['uid' => $id])->find();
					if (!$scan_user) {
						$puser = db('wxuser')->field('id,is_first')->where(['id' => $p_id])->find();
						$data  = [
							'yuser_id' => $p_id,
							'uid'      => $id,
							'add_at'   => time(),
						];
						//邀请我的是顶级的人
						if ($puser['is_first'] == 1) {
							//是顶级,不需要id
							$data['first_id'] = $p_id;
						} else {
							//不是，查看上一级
							$pre = db('user_sub')->where(['uid' => $p_id])->value('first_id') ?: 0;
							//ptrace($pre);
							$data['first_id'] = $pre;
						}
						//ptrace($data);
						db('user_sub')->insert($data);
					}
				}
			}
		}
	}

	//授权登录
	public function auth($redirect_url, $scopes = 'snsapi_userinfo')
	{
		$this->option          = $this->options;
		$this->option['oauth'] = [
			'scopes'   => [$scopes],
			'callback' => $redirect_url,
		];
		try {
			$app      = new Application($this->option);
			$response = $app->oauth->redirect();
			$response->send();
		} catch (\Exception $e) {
			dump("授权失败.".$e->getMessage());
		}
	}

	// 授权登录后的回调页面
	public function auth_back()
	{
		$code = input('code');
		if (empty($code)) {
			$this->error('code 参数缺少');
		}
		$oauth = $this->app->oauth;
		// 获取 OAuth 授权结果用户信息
		$user            = $oauth->user();
		$wechat_userinfo = $user->toArray();
		$wechat_userinfo = $wechat_userinfo['original'];
		// ptrace('微信授权后用户信息');
		// ptrace($wechat_userinfo);
		$openid     = $wechat_userinfo['openid'];
		$nickname   = $wechat_userinfo['nickname'];
		$sex        = $wechat_userinfo['sex'];
		$headimgurl = $wechat_userinfo['headimgurl'];
		$time       = date('Y-m-d H:i:s', time());
		if (!empty($openid)) {
			$userModel = model('wxuser');
			$has       = $userModel->where(['openid' => $openid])->find();
			// ptrace($has);
			$data['openid']      = $openid;
			$data['nickname']    = $nickname;
			$data['headimage']   = $headimgurl;
			$data['update_time'] = $time;
			$cookie_time         = 86400 * 7;
			$recUid              = cookie("recUid") ?: 0;
			if (empty($has)) {
				if (!empty($recUid)) {
					$data['recUid'] = $recUid;
				}
				$data['nickname'] = $nickname;
				$data['sex']      = $sex;
				//$data['create_time'] = $time;
				$res = $userModel->insert($data);
				// ptrace($res);
				if (!$res) {
					dump($userModel->getError());
				}
				$uid = $userModel->getLastInsID();
				// ptrace($userModel->_sql());
			} else {
				if (empty($has['recUid']) && !empty($recUid) && $recUid != $has['id']) {
					$data['recUid'] = $recUid;
				}
				$res = $userModel->where(['openid' => $openid])->update($data);
				// ptrace($res);
				if ($res === false) {
					dump(model('wxuser')->getError());
				}
				$uid = $has['id'];
			}
			cookie('attention', 1, 60);
			// ptrace("{$uid}|{$openid}");
			cookie('openid', $openid, $cookie_time);
			cookie('user_id', $uid, $cookie_time);
			// ptrace(cookie('openid'));
			// ptrace(cookie('user_id'));
		}
		// ptrace(cookie('target_url'));
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
		$this->wxpay_config['payment']['key_path']  = realpath('./cert/apiclient_key.pem');
	}

	//生成二维码
	public function qrcode1($code = null, $expire = 0)
	{
		$app    = $this->app;
		$qrcode = $app->qrcode;
		try {
			if ($expire) {
				$result        = $qrcode->temporary($code, $expire);
				$ticket        = $result->ticket; // 或者 $result['ticket']
				$expireSeconds = $result->expire_seconds; // 有效秒数
				$url           = $result->url; // 二维码图片解析后的地址，开发者可根据该地址自行生成需要的二维码图片
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
		} catch (\Exception $e) {
			//pubu("生成{$code}, 有效期为{$expire}秒的二维码失败");
			return ['status' => 0, 'info' => $e->getMessage()];
		}
	}

	//创建微信统一订单
	public function union_order($orderInfo)
	{
		// ptrace($this->wxpay_config);
		if ($orderInfo['money'] * 100 < 1) {
			// ptrace($orderInfo);
			//ptrace("创建订单前提失败，金额不足1分");
			return false;
		}
		$app      = new Application($this->wxpay_config);
		$order_no = $orderInfo['ordernum'];
		// pubu('订单号：' . $order_no);
		$payment    = $app->payment;
		$attributes = [
			'openid'       => $orderInfo['openid'],
			'body'         => $orderInfo['body'],
			'detail'       => $orderInfo['detail'],
			'out_trade_no' => $order_no,
			//    'total_fee'    => config('app_debug') ? 1 : $orderInfo['money'] * 100, //APP_DEBUG ? 1 :
			'total_fee'    => $orderInfo['money'] * 100, //APP_DEBUG ? 1 :
			'trade_type'   => 'JSAPI',
			'time_start'   => date('YmdHis'),
			'time_expire'  => date('YmdHis', strtotime('+1 year')),
			'notify_url'   => XILUDomain().'/index.php/index/weixin/wxOrderNotify/slog_force_client_id/slog_2c9a99',
			// 'notify_url' => 'http://d.xilukeji.com/zxschool/index.php/index/weixin/wxOrderNotify/slog_force_client_id/slog_2c9a99', //支付回调 支付结果通知网址，如果不设置则会使用配置里的默认地址
			//  'notify_url'   => 'http://b.xilukeji.com/cityenglish/index.php/index/weixin/wxOrderNotify/slog_force_client_id/slog_2c9a99', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
			// ...
		];
		//ptrace($attributes);
		$order = new EasyOrder($attributes);
		try {
			$result   = $payment->prepare($order);
			$prepayId = $result->prepay_id;
			if (!$prepayId) {
				//                 dump($order);
				//                dump($attributes);
				//                dump('prepayId为空');
				return false;
			}
			$json = $payment->configForPayment($prepayId);
			//            dump($json);die;
			return $json;
		} catch (\Exception $e) {
			//            pubu('创建微信订单失败');
			//            trace('创建微信订单失败');
			//            pubu($e->getMessage());
			return false;
		}
	}

	// 查询订单
	public function wxOrderQuery($out_trade_no)
	{
		$app     = new Application($this->wxpay_config);
		$payment = $app->payment;
		$result  = $payment->query($out_trade_no);
		return $result;
	}

	// 订单成功支付回调
	public function wxOrderNotify()
	{
		db('log')->insert(['content' => 222, 'create_time' => date('Y-m-d H:i:s')]);
		$app      = new Application($this->wxpay_config);
		$response = $app->payment->handleNotify(function ($notify, $successful)
		{
			$order_model = (new Order());
			// 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
			$order = $order_model->where(['ordernum' => $notify->out_trade_no])->find();
			if (!$order) {
				// ptrace($notify->out_trade_no);
			}
			if (!$order) {
				// 如果订单不存在
				return 'Order not exist.';
				// 告诉微信，我已经处理完了，订单没找到，别再通知我了
			}
			// 如果订单存在
			// 检查订单是否已经更新过支付状态
			if ($order['checkinfo'] >= 2) { // 假设订单字段“支付时间”不为空代表已经支付
				return true; // 已经支付成功了就不再更新了
			}
			//            if (($notify->total_fee / 100) != $order['payment']) {
			//                return false;
			//            }
			// 用户是否支付成功
			if ($successful) {
				//更新订单的状态
				// $orderObj = new \common\util\Order('weixin', 0);
				// $result   = $orderObj->ChangeOrderStatus($order['id'], 'payed', ['transaction_id' => $notify->transaction_id]);
				$result = $order_model->payComplete($order);
				if ($result['status']) {
					return true;
				} else {
					// ptrace($result);
					return false;
				}
			} else {
				// ptrace($order);
				// ptrace($notify);
				// ptrace($successful);
				return false;
			}
		});
		$response->send();
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
		$app          = new Application($this->wxpay_config);
		$payment      = $app->payment;
		$exist_refund = $refundModel->where(['order_no' => $order_no, 'status' => 2])->find();
		if ($exist_refund) {
			pubu("订单号 {$order_no}的订单已申请过退款");
			return true;
		}
		$orderModel = model('Order'); //TODO 可能替换
		$orderInfo  = $orderModel->where(['order_no' => $order_no])->find();
		if (!$orderInfo) {
			pubu("订单号 {$order_no}的订单记录不存在");
			return false;
		}
		//测试情况 检测退款金额
		if (config('app_debug')) {
			$orderInfo['order_amount'] = 0.01;
			$to_back_fee               = 1;
		}
		if ($to_back_fee > 100 * $orderInfo['order_amount']) {
			pubu("订单号{$order_no}申请的退款金额 {$to_back_fee}分 大于支付金额");
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
			ptrace($result);
			if ('SUCCESS' != $result['return_code']) {
				pubu("订单号：{$order_no} 因{$reason} 申请的微信退款失败, 原因：{$result['return_msg']}");
				return false;
			} else {
				$refundModel->where(['id' => $refundId])->update(['back_time' => time(), 'status' => 2]);
				return true;
			}
		} catch (\Exception $e) {
			pubu("订单号：{$order_no} 因{$reason} 申请的微信退款失败, 原因：{$e->getMessage()}");
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
		$app             = new Application($this->wxpay_config);
		$merchantPay     = $app->merchant_pay;
		$order_no        = getOrderNo();
		$merchantPayData = [
			'partner_trade_no' => $order_no.$withdraw['id'], //随机字符串作为订单号，跟红包和支付一个概念。
			'openid'           => $openid, //收款人的openid
			'check_name'       => 'NO_CHECK', //文档中三分钟校验实名的方法NO_CHECK OPTION_CHECK FORCE_CHECK
			// 're_user_name'     => '张三', //OPTION_CHECK FORCE_CHECK 校验实名的时候必须提交
			'amount'           => config('app_debug') ? 100 : $withdraw['money'] * 100, //单位为分 ，最少不能小于1元
			'desc'             => '【'.config('web_site_title').'】用户提现',
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
		} catch (\Exception $e) {
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
		// ptrace($openid);
		ptrace('发送的消息:'.$message);
		$staff = $this->app->staff;
		try {
			$ret = $staff->message($message)->to($openid)->send();
			return $ret->errcode == 0;
		} catch (\Exception $e) {
			pubu("发送客服消息失败，失败原因{$e->getMessage()}");
			return false;
		}
	}

	public function get_user_info($openid)
	{
		$userService = $this->app->user;
		try {
			return $userService->get($openid);
		} catch (\Exception $e) {
			dump("获取信息失败");
			return false;
		}
	}
	// 1. 所有服务号都可以在功能->添加功能插件处看到申请模板消息功能的入口，但只有认证后的服务号才可以申请模板消息的使用权限并获得该权限；
	// 2. 需要选择公众账号服务所处的2个行业，每月可更改1次所选行业；
	// 3. 在所选择行业的模板库中选用已有的模板进行调用；
	// 4. 每个账号可以同时使用15个模板。
	// 5. 当前每个模板的日调用上限为 10 万次【2014年11月18日将接口调用频率从默认的日1万次提升为日10万次，可在MP登录后的开发者中心查看】
	/**
	 * 发送模板消息
	 * @author yangweijie
	 * @param openid openid
	 * @param $templateId
	 * @param $data = array(
	 *              "first"    => "恭喜你购买成功！",
	 *              "keynote1" => "巧克力",
	 *              "keynote2" => "39.8元",
	 *              "keynote3" => "2014年9月16日",
	 *              "remark"   => "欢迎再次购买！",
	 *              );
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
			$messageId = $notice->to($openid)->template($templateId)->data($data)->send();
			return true;
		} catch (\Exception $e) {
			ptrace("给{$openid} 发送templateId为{$templateId} 数据为".var_export($data, true).'的微信模板消息失败，失败原因:'.$e->getMessage());
			return false;
		}
	}

	//微信cookie调试
	public function debug()
	{
		$user_list = Db::name('user')->where(['admin_uid' => ['neq', 0]])->order('id ASC')->select();
		if ($user_list) {
			$admin_users = model('admin_user')->where(['id' => ['in', array_column($user_list, 'admin_uid')]])->column('id,username,mobile');
		}
		foreach ($user_list as $key => $value) {
			if (isset($admin_users[$value['admin_uid']])) {
				$user_list[$key]['username'] = $admin_users[$value['admin_uid']]['username'];
				$user_list[$key]['mobile']   = $admin_users[$value['admin_uid']]['mobile'];
			} else {
				$user_list[$key]['username'] = '未知';
				$user_list[$key]['mobile']   = '空';
			}
		}
		$this->assign('user_list', $user_list);
		return $this->fetch();
	}

	public function write_log($content)
	{
		db('log')->insert(['content' => json_encode($content)]);
	}
}
