<?php
namespace addons\rlsms;

use think\Addons;

/**
 * 容融云短信插件
 */
class Rlsms extends Addons
{
	/**
	 * 插件安装方法
	 * @return bool
	 */
	public function install()
	{
		return true;
	}

	/**
	 * 插件卸载方法
	 * @return bool
	 */
	public function uninstall()
	{
		return true;
	}

	/**
	 * 插件启用方法
	 * @return bool
	 */
	public function enable()
	{
		return true;
	}

	/**
	 * 插件禁用方法
	 * @return bool
	 */
	public function disable()
	{
		return true;
	}

	/**
	 * 短信发送行为
	 * @param   Sms $params
	 * @return  boolean
	 */
	public function smsSend(&$params)
	{
		$config = get_addon_config('rlsms');
		//请求地址，格式如下，不需要写https://
		$serverIP = 'app.cloopen.com';
		//请求端口
		$serverPort = '8883';
		//REST版本号
		$softVersion = '2013-12-26';
		$rest        = new library\Rest($serverIP, $serverPort, $softVersion);
		$rest->setAccount($config['accountSid'], $config['accountToken']);
		$rest->setAppId($config['appId']);
		$content = [$params['code'], '10分钟'];
		$result  = $rest->sendTemplateSMS($params['mobile'], $content, $config['template'][$params['event']]);
		$status  = $result->statusCode;
		if ($result->statusCode == 0) {
			$smsMessage = $result->TemplateSMS;
			return true;
		} else {
			$errorMsg = $result->statusMsg;
			return false;
		}
	}

	/**
	 * 短信发送通知
	 * @param   array $params
	 * @return  boolean
	 */
	public function smsNotice(&$params)
	{
		$config = get_addon_config('rlsms');
		//请求地址，格式如下，不需要写https://
		$serverIP = 'app.cloopen.com';
		//请求端口
		$serverPort = '8883';
		//REST版本号
		$softVersion = '2013-12-26';
		$rest        = new library\Rest($serverIP, $serverPort, $softVersion);
		$rest->setAccount($config['accountSid'], $config['accountToken']);
		$rest->setAppId($config['appId']);
		$content = [$params['code'], 10];
		$result  = $rest->sendTemplateSMS($params->mobile, $content, $params['template']);
		return $result->statusCode == 0 ? true : false;
	}

	/**
	 * 检测验证是否正确
	 * @param   Sms $params
	 * @return  boolean
	 */
	public function smsCheck(&$params)
	{
		return true;
	}
}
