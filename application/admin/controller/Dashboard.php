<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;

/**
 * 控制台
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{
	/**
	 * 查看
	 */
	public function index()
	{
		$seventtime = \fast\Date::unixtime('day', -7);
		$paylist    = $createlist = [];
		for ($i = 0; $i < 7; $i++) {
			$day              = date("Y-m-d", $seventtime + ($i * 86400));
			$createlist[$day] = mt_rand(20, 200);
			$paylist[$day]    = mt_rand(1, mt_rand(1, $createlist[$day]));
		}
		// $hooks = config('addons.hooks');
		// $uploadmode = isset($hooks['upload_config_init']) && $hooks['upload_config_init'] ? implode(',', $hooks['upload_config_init']) : 'local';
		// $addonComposerCfg = ROOT_PATH . '/vendor/karsonzhang/fastadmin-addons/composer.json';
		// Config::parse($addonComposerCfg, "json", "composer");
		// $config = Config::get("composer");
		// $addonVersion = isset($config['version']) ? $config['version'] : __('Unknown');
		$siteList = [
			['name' => 0, 'title' => "控制台", 'href' => url('admin/dashboard/index'), 'active' => 1],
			['name' => 1, 'title' => "订单交易金额统计", 'href' => url('admin/dashboard/goodsorder')],
			['name' => 2, 'title' => "充值交易金额统计", 'href' => url('admin/dashboard/rechargeorder')],
			['name' => 3, 'title' => "订单成交数量统计", 'href' => url('admin/dashboard/ordernum')],
		];
		// 用户统计
		$user_num = db('member')->count();
		// 商品统计
		$goods_num = db('goods')->count();
		// 农场统计
		$farm_num = db('farm')->count();
		// 农作物统计
		$crops_num = db('crops')->count();
		// 订单统计
		$goods_order_num   = db('goods_order')->count();
		$service_order_num = db('service_order')->count();
		$recharge_num      = db('recharge')->count();
		$withdraw_num      = db('withdraw')->count();
		$this->view->assign([
			'user_num'          => $user_num,
			'goods_num'         => $goods_num,
			'farm_num'          => $farm_num,
			'crops_num'         => $crops_num,
			'goods_order_num'   => $goods_order_num,
			'service_order_num' => $service_order_num,
			'recharge_num'      => $recharge_num,
			'withdraw_num'      => $withdraw_num,
			'siteList'          => $siteList,
		]);
		return $this->view->fetch();
	}

	public function goodsorder()
	{
		$siteList = [
			['name' => 0, 'title' => "控制台", 'href' => url('admin/dashboard/index')],
			['name' => 1, 'title' => "订单交易金额统计", 'href' => url('admin/dashboard/goodsorder'), 'active' => '1'],
			['name' => 2, 'title' => "充值交易金额统计", 'href' => url('admin/dashboard/rechargeorder')],
			['name' => 3, 'title' => "订单成交数量统计", 'href' => url('admin/dashboard/ordernum')],
		];
		//计算统计图日期
		$type         = input('get.type', '');
		$order_object = db('goods_order');
		$map['status'] = '3';
		if ($type == 'month') {
			//按月统计
			$start_date = input('get.start_date') ? substr(input('get.start_date'), 0, 7) : date('Y-m', strtotime('-12 month'));
			//过去12个月
			$end_date = input('get.end_date') ? substr(input('get.end_date'), 0, 7) : date('Y-m', strtotime('+0 month'));
			$i        = 0;
			$month1   = '';
			while ($month1 != $end_date) {
				$month1      = date('Y-m', strtotime('+'.$i.' month '.$start_date));
				$next_month1 = date('Y-m', strtotime('+'.($i + 1).' month'.$start_date));
				$month             = strtotime($month1.'-01');
				$next_month        = strtotime($next_month1.'-01');
				$map['createtime'] = [
					['egt', $month],
					['lt', $next_month],
				];
				$user_reg_date[]   = date('y年m月', strtotime(($month1)));
				$sum               = $order_object->where($map)->sum('pay_price');
				$user_reg_count[]  = $sum;
				$i++;
			}
			$count_day  = $i;
			$start_date = date('Y-m-d', strtotime($start_date));
			$end_date   = date('Y-m-d', strtotime($end_date.' +1 month') - 1);
		} elseif ($type == 'year') {
			//按年统计
			$start_date = input('get.start_date') ? input('get.start_date') : date('Y-m-d', strtotime('-5 year'));
			//过去5年
			$end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d', strtotime('+0 year'));
			$i        = 0;
			$end_date = substr($end_date, 0, 4);
			$year1    = '';
			while ($year1 != $end_date) {
				$year1      = date('Y', strtotime('+'.$i.' year '.$start_date));
				$next_year1 = date('Y', strtotime('+'.($i + 1).' year'.$start_date));
				$year              = strtotime($year1.'-01-01');
				$next_year         = strtotime($next_year1.'-01-01');
				$map['createtime'] = [
					['egt', $year],
					['lt', $next_year],
				];
				$user_reg_date[]   = date('Y年', strtotime(($year1.'-01-01')));
				$sum               = $order_object->where($map)->sum('pay_price');
				$user_reg_count[]  = $sum;
			}
			$count_day  = $i;
			$start_date = date('Y', strtotime($start_date)).'-01-01';
			$end_date   = date('Y', strtotime($end_date.' +1 year')).'-01-01';
		} else {
			//按日统计 1周
			$today      = strtotime(date('Y-m-d', time())); //今天
			$start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today - 7 * 86400;
			$end_date   = input('get.end_date') ? (strtotime(input('get.end_date')) + 1) : $today + 86400;
			$count_day  = ($end_date - $start_date) / 86400; //查询最近n天
			for ($i = 0; $i < $count_day; $i++) {
				$day_stamp       = $start_date + $i * 86400; //第n天日期
				$day_after_stamp = $start_date + ($i + 1) * 86400; //第n+1天日期
				//                $day = date('Y-m-d H:i:s',$day_stamp);
				//                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
				$map['createtime'] = [
					['egt', $day_stamp],
					['lt', $day_after_stamp],
				];
				$user_reg_date[]   = date('m月d日', $day_stamp);
				$sum               = $order_object->where($map)->sum('pay_price');
				$user_reg_count[]  = $sum;
			}
			$start_date = date('Y-m-d', $start_date);
			$end_date   = date('Y-m-d', $end_date - 1);
		}
		$this->assign('type', $type);
		$this->assign('start_date', $start_date);
		$this->assign('end_date', $end_date);
		$this->assign('count_day', $count_day);
		$this->assign('user_reg_date', json_encode($user_reg_date));
		$this->assign('user_reg_count', json_encode($user_reg_count));
		$this->assign('siteList', $siteList);
		$this->assign('meta_title', "订单总金额");
		return $this->view->fetch();
	}

	public function rechargeorder()
	{
		$siteList = [
			['name' => 0, 'title' => "控制台", 'href' => url('admin/dashboard/index')],
			['name' => 1, 'title' => "订单交易金额统计", 'href' => url('admin/dashboard/goodsorder')],
			['name' => 2, 'title' => "充值交易金额统计", 'href' => url('admin/dashboard/rechargeorder'), 'active' => 1],
			['name' => 3, 'title' => "订单成交数量统计", 'href' => url('admin/dashboard/ordernum')],
		];
		//计算统计图日期
		$type         = input('get.type', '');
		$order_object = db('recharge');
		$map['type'] = '1';
		if ($type == 'month') {
			//按月统计
			$start_date = input('get.start_date') ? substr(input('get.start_date'), 0, 7) : date('Y-m', strtotime('-12 month'));
			//过去12个月
			$end_date = input('get.end_date') ? substr(input('get.end_date'), 0, 7) : date('Y-m', strtotime('+0 month'));
			$i        = 0;
			$month1   = '';
			while ($month1 != $end_date) {
				$month1      = date('Y-m', strtotime('+'.$i.' month '.$start_date));
				$next_month1 = date('Y-m', strtotime('+'.($i + 1).' month'.$start_date));
				$month             = strtotime($month1.'-01');
				$next_month        = strtotime($next_month1.'-01');
				$map['createtime'] = [
					['egt', $month],
					['lt', $next_month],
				];
				$user_reg_date[]   = date('y年m月', strtotime(($month1)));
				$sum               = $order_object->where($map)->sum('money');
				$user_reg_count[]  = $sum;
				$i++;
			}
			$count_day  = $i;
			$start_date = date('Y-m-d', strtotime($start_date));
			$end_date   = date('Y-m-d', strtotime($end_date.' +1 month') - 1);
		} elseif ($type == 'year') {
			//按年统计
			$start_date = input('get.start_date') ? input('get.start_date') : date('Y-m-d', strtotime('-5 year'));
			//过去5年
			$end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d', strtotime('+0 year'));
			$i        = 0;
			$end_date = substr($end_date, 0, 4);
			$year1    = '';
			while ($year1 != $end_date) {
				$year1      = date('Y', strtotime('+'.$i.' year '.$start_date));
				$next_year1 = date('Y', strtotime('+'.($i + 1).' year'.$start_date));
				$year              = strtotime($year1.'-01-01');
				$next_year         = strtotime($next_year1.'-01-01');
				$map['createtime'] = [
					['egt', $year],
					['lt', $next_year],
				];
				$user_reg_date[]   = date('Y年', strtotime(($year1.'-01-01')));
				$sum               = $order_object->where($map)->sum('money');
				$user_reg_count[]  = $sum;
				$i++;
			}
			$count_day  = $i;
			$start_date = date('Y', strtotime($start_date)).'-01-01';
			$end_date   = date('Y', strtotime($end_date.' +1 year')).'-01-01';
		} else {
			//按日统计 2周
			$today      = strtotime(date('Y-m-d', time())); //今天
			$start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today - 7 * 86400;
			$end_date   = input('get.end_date') ? (strtotime(input('get.end_date')) + 1) : $today + 86400;
			$count_day  = ($end_date - $start_date) / 86400; //查询最近n天
			for ($i = 0; $i < $count_day; $i++) {
				$day_stamp       = $start_date + $i * 86400; //第n天日期
				$day_after_stamp = $start_date + ($i + 1) * 86400; //第n+1天日期
				//                $day = date('Y-m-d H:i:s',$day_stamp);
				//                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
				$map['createtime'] = [
					['egt', $day_stamp],
					['lt', $day_after_stamp],
				];
				$user_reg_date[]   = date('m月d日', $day_stamp);
				$sum               = $order_object->where($map)->sum('money');
				$user_reg_count[]  = $sum;
			}
			$start_date = date('Y-m-d', $start_date);
			$end_date   = date('Y-m-d', $end_date - 1);
		}
		$this->assign('type', $type);
		$this->assign('start_date', $start_date);
		$this->assign('end_date', $end_date);
		$this->assign('count_day', $count_day);
		$this->assign('user_reg_date', json_encode($user_reg_date));
		$this->assign('user_reg_count', json_encode($user_reg_count));
		$this->assign('siteList', $siteList);
		$this->assign('meta_title', "订单总金额");
		return $this->view->fetch();
	}

	public function ordernum()
	{
		$siteList = [
			['name' => 0, 'title' => "控制台", 'href' => url('admin/dashboard/index')],
			['name' => 1, 'title' => "订单交易金额统计", 'href' => url('admin/dashboard/goodsorder')],
			['name' => 2, 'title' => "充值交易金额统计", 'href' => url('admin/dashboard/rechargeorder')],
			['name' => 3, 'title' => "订单成交数量统计", 'href' => url('admin/dashboard/ordernum'), 'active' => 1],
		];
		//计算统计图日期
		$type         = input('get.type', '');
		$order_object = db('goods_order');
		$map['status'] = '3';
		if ($type == 'month') {
			//按月统计
			$start_date = input('get.start_date') ? substr(input('get.start_date'), 0, 7) : date('Y-m', strtotime('-12 month'));
			//过去12个月
			$end_date = input('get.end_date') ? substr(input('get.end_date'), 0, 7) : date('Y-m', strtotime('+0 month'));
			$i        = 0;
			$month1   = '';
			while ($month1 != $end_date) {
				$month1      = date('Y-m', strtotime('+'.$i.' month '.$start_date));
				$next_month1 = date('Y-m', strtotime('+'.($i + 1).' month'.$start_date));
				$month             = strtotime($month1.'-01');
				$next_month        = strtotime($next_month1.'-01');
				$map['createtime'] = [
					['egt', $month],
					['lt', $next_month],
				];
				$user_reg_date[]   = date('y年m月', strtotime(($month1)));
				$sum               = $order_object->where($map)->count();
				$user_reg_count[]  = $sum;
				$i++;
			}
			$count_day  = $i;
			$start_date = date('Y-m-d', strtotime($start_date));
			$end_date   = date('Y-m-d', strtotime($end_date.' +1 month') - 1);
		} elseif ($type == 'year') {
			//按年统计
			$start_date = input('get.start_date') ? input('get.start_date') : date('Y-m-d', strtotime('-5 year'));
			//过去5年
			$end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d', strtotime('+0 year'));
			$i        = 0;
			$end_date = substr($end_date, 0, 4);
			$year1    = '';
			while ($year1 != $end_date) {
				$year1      = date('Y', strtotime('+'.$i.' year '.$start_date));
				$next_year1 = date('Y', strtotime('+'.($i + 1).' year'.$start_date));
				$year              = strtotime($year1.'-01-01');
				$next_year         = strtotime($next_year1.'-01-01');
				$map['createtime'] = [
					['egt', $year],
					['lt', $next_year],
				];
				$user_reg_date[]   = date('Y年', strtotime(($year1.'-01-01')));
				$sum               = $order_object->where($map)->count();
				$user_reg_count[]  = $sum;
				$i++;
			}
			$count_day  = $i;
			$start_date = date('Y', strtotime($start_date)).'-01-01';
			$end_date   = date('Y', strtotime($end_date.' +1 year')).'-01-01';
		} else {
			//按日统计 2周
			$today      = strtotime(date('Y-m-d', time())); //今天
			$start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today - 7 * 86400;
			$end_date   = input('get.end_date') ? (strtotime(input('get.end_date')) + 1) : $today + 86400;
			$count_day  = ($end_date - $start_date) / 86400; //查询最近n天
			for ($i = 0; $i < $count_day; $i++) {
				$day_stamp       = $start_date + $i * 86400; //第n天日期
				$day_after_stamp = $start_date + ($i + 1) * 86400; //第n+1天日期
				//                $day = date('Y-m-d H:i:s',$day_stamp);
				//                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
				$map['createtime'] = [
					['egt', $day_stamp],
					['lt', $day_after_stamp],
				];
				$user_reg_date[]   = date('m月d日', $day_stamp);
				$sum               = $order_object->where($map)->count();
				$user_reg_count[]  = $sum;
			}
			$start_date = date('Y-m-d', $start_date);
			$end_date   = date('Y-m-d', $end_date - 1);
		}
		$this->assign('type', $type);
		$this->assign('start_date', $start_date);
		$this->assign('end_date', $end_date);
		$this->assign('count_day', $count_day);
		$this->assign('user_reg_date', json_encode($user_reg_date));
		$this->assign('user_reg_count', json_encode($user_reg_count));
		$this->assign('siteList', $siteList);
		$this->assign('meta_title', "订单成交数");
		return $this->view->fetch();
	}
}
