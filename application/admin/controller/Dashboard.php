<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;

/**
 * 控制台
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
//        $seventtime = \fast\Date::unixtime('day', -7);
//        $paylist = $createlist = [];
//        for ($i = 0; $i < 7; $i++)
//        {
//            $day = date("Y-m-d", $seventtime + ($i * 86400));
//            $createlist[$day] = mt_rand(20, 200);
//            $paylist[$day] = mt_rand(1, mt_rand(1, $createlist[$day]));
//        }
//        $hooks = config('addons.hooks');
//        $uploadmode = isset($hooks['upload_config_init']) && $hooks['upload_config_init'] ? implode(',', $hooks['upload_config_init']) : 'local';
//        $addonComposerCfg = ROOT_PATH . '/vendor/karsonzhang/fastadmin-addons/composer.json';
//        Config::parse($addonComposerCfg, "json", "composer");
//        $config = Config::get("composer");
//        $addonVersion = isset($config['version']) ? $config['version'] : __('Unknown');
//        $this->view->assign([
//            'totaluser'        => 35200,
//            'totalviews'       => 219390,
//            'totalorder'       => 32143,
//            'totalorderamount' => 174800,
//            'todayuserlogin'   => 321,
//            'todayusersignup'  => 430,
//            'todayorder'       => 2324,
//            'unsettleorder'    => 132,
//            'sevendnu'         => '80%',
//            'sevendau'         => '32%',
//            'paylist'          => $paylist,
//            'createlist'       => $createlist,
//            'addonversion'       => $addonVersion,
//            'uploadmode'       => $uploadmode
//        ]);

        //计算统计图日期
        $type = input('get.type','');
        $order_object = db('wxuser');
        if ($type == 'month') {
            //按月统计
            $start_date = input('get.start_date') ? substr(input('get.start_date'),0,7) : date('Y-m',strtotime('-12 month'));
            //过去12个月
            $end_date = input('get.end_date') ? substr(input('get.end_date'),0,7) : date('Y-m',strtotime('+0 month'));
            $i = 0;
            $month = '';
            while($month != $end_date ){
                $month = date('Y-m',strtotime('+'.$i.' month '.$start_date));
                $next_month = date('Y-m',strtotime('+'.($i+1).' month'.$start_date));
                $map['create_time'] = [
                    ['egt',$month.'-00'],
                    ['lt',$next_month.'-00'],
                ];
                $user_reg_date[] = date('y年m月', strtotime(($month)));
                $user_reg_count[] = (int)$order_object->where($map)->count();
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date = date('Y-m-d', strtotime($end_date.' +1 month')-1);
        }else if($type == 'year'){
            //按年统计
            $start_date = input('get.start_date') ?input('get.start_date') : date('Y-m-d',strtotime('-5 year'));
            //过去5年
            $end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d',strtotime('+0 year'));
            $i = 0;
            $end_date = substr($end_date,0,4);
            $year = '';
            while($year != $end_date ){
                $year = date('Y',strtotime('+'.$i.' year '.$start_date));
                $next_year = date('Y',strtotime('+'.($i+1).' year'.$start_date));
                $map['create_time'] = [
                    ['egt',$year.'-00-00'],
                    ['lt',$next_year.'-00-00'],
                ];
                $user_reg_date[] = date('Y年', strtotime(($year.'-01-01')));
                $user_reg_count[] = (int)$order_object->where($map)->count();
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y', strtotime($start_date)).'-01-01';
            $end_date = date('Y', strtotime($end_date.' +1 year')).'-01-01';
        }else{
            //按日统计 2周
            $today = strtotime(date('Y-m-d', time())); //今天
            $start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today-14*86400;
            $end_date   = input('get.end_date') ? (strtotime(input('get.end_date'))+1) : $today+86400;
            $count_day  = ($end_date-$start_date)/86400; //查询最近n天
            for($i = 0; $i < $count_day; $i++){
                $day_stamp = $start_date + $i*86400; //第n天日期
                $day_after_stamp = $start_date + ($i+1)*86400; //第n+1天日期

                $day = date('Y-m-d H:i:s',$day_stamp);
                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
                $map['create_time'] = array(
                    array('egt', $day),
                    array('lt', $day_after)
                );
                $user_reg_date[] = date('m月d日', $day_stamp);
                $user_reg_count[] = (int)$order_object->where($map)->count();
            }
            $start_date = date('Y-m-d', $start_date);
            $end_date = date('Y-m-d', $end_date-1);
        }

        $this->assign('type', $type);
        $this->assign('start_date', $start_date);
        $this->assign('end_date', $end_date);
        $this->assign('count_day', $count_day);
        $this->assign('user_reg_date', json_encode($user_reg_date));
        $this->assign('user_reg_count', json_encode($user_reg_count));

        return $this->view->fetch();
    }

    public function ordercount()
    {
//        $seventtime = \fast\Date::unixtime('day', -7);
//        $paylist = $createlist = [];
//        for ($i = 0; $i < 7; $i++)
//        {
//            $day = date("Y-m-d", $seventtime + ($i * 86400));
//            $createlist[$day] = mt_rand(20, 200);
//            $paylist[$day] = mt_rand(1, mt_rand(1, $createlist[$day]));
//        }
//        $hooks = config('addons.hooks');
//        $uploadmode = isset($hooks['upload_config_init']) && $hooks['upload_config_init'] ? implode(',', $hooks['upload_config_init']) : 'local';
//        $addonComposerCfg = ROOT_PATH . '/vendor/karsonzhang/fastadmin-addons/composer.json';
//        Config::parse($addonComposerCfg, "json", "composer");
//        $config = Config::get("composer");
//        $addonVersion = isset($config['version']) ? $config['version'] : __('Unknown');
//        $this->view->assign([
//            'totaluser'        => 35200,
//            'totalviews'       => 219390,
//            'totalorder'       => 32143,
//            'totalorderamount' => 174800,
//            'todayuserlogin'   => 321,
//            'todayusersignup'  => 430,
//            'todayorder'       => 2324,
//            'unsettleorder'    => 132,
//            'sevendnu'         => '80%',
//            'sevendau'         => '32%',
//            'paylist'          => $paylist,
//            'createlist'       => $createlist,
//            'addonversion'       => $addonVersion,
//            'uploadmode'       => $uploadmode
//        ]);

        //计算统计图日期
        $type = input('get.type','');
        $order_object = db('order');
        $map['checkinfo'] = array('gt',1);
        if ($type == 'month') {
            //按月统计
            $start_date = input('get.start_date') ? substr(input('get.start_date'),0,7) : date('Y-m',strtotime('-12 month'));
            //过去12个月
            $end_date = input('get.end_date') ? substr(input('get.end_date'),0,7) : date('Y-m',strtotime('+0 month'));
            $i = 0;
            $month = '';
            while($month != $end_date ){
                $month = date('Y-m',strtotime('+'.$i.' month '.$start_date));
                $next_month = date('Y-m',strtotime('+'.($i+1).' month'.$start_date));
                $map['create_time'] = [
                    ['egt',$month.'-00'],
                    ['lt',$next_month.'-00'],
                ];
                $user_reg_date[] = date('y年m月', strtotime(($month)));
                $user_reg_count[] = (int)$order_object->where($map)->count();
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date = date('Y-m-d', strtotime($end_date.' +1 month')-1);
        }else if($type == 'year'){
            //按年统计
            $start_date = input('get.start_date') ?input('get.start_date') : date('Y-m-d',strtotime('-5 year'));
            //过去5年
            $end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d',strtotime('+0 year'));
            $i = 0;
            $end_date = substr($end_date,0,4);
            $year = '';
            while($year != $end_date ){
                $year = date('Y',strtotime('+'.$i.' year '.$start_date));
                $next_year = date('Y',strtotime('+'.($i+1).' year'.$start_date));
                $map['create_time'] = [
                    ['egt',$year.'-00-00'],
                    ['lt',$next_year.'-00-00'],
                ];
                $user_reg_date[] = date('Y年', strtotime(($year.'-01-01')));
                $user_reg_count[] = (int)$order_object->where($map)->count();
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y', strtotime($start_date)).'-01-01';
            $end_date = date('Y', strtotime($end_date.' +1 year')).'-01-01';
        }else{
            //按日统计 2周
            $today = strtotime(date('Y-m-d', time())); //今天
            $start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today-14*86400;
            $end_date   = input('get.end_date') ? (strtotime(input('get.end_date'))+1) : $today+86400;
            $count_day  = ($end_date-$start_date)/86400; //查询最近n天
            for($i = 0; $i < $count_day; $i++){
                $day_stamp = $start_date + $i*86400; //第n天日期
                $day_after_stamp = $start_date + ($i+1)*86400; //第n+1天日期

                $day = date('Y-m-d H:i:s',$day_stamp);
                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
                $map['create_time'] = array(
                    array('egt', $day),
                    array('lt', $day_after)
                );
                $user_reg_date[] = date('m月d日', $day_stamp);
                $user_reg_count[] = (int)$order_object->where($map)->count();
            }
            $start_date = date('Y-m-d', $start_date);
            $end_date = date('Y-m-d', $end_date-1);
        }

        $this->assign('type', $type);
        $this->assign('start_date', $start_date);
        $this->assign('end_date', $end_date);
        $this->assign('count_day', $count_day);
        $this->assign('user_reg_date', json_encode($user_reg_date));
        $this->assign('user_reg_count', json_encode($user_reg_count));
        $this->assign('meta_title', "订单数量");


        return $this->view->fetch();
    }

    //销售额统计
    public function ordersale()
    {

        //计算统计图日期
        $type = input('get.type','');
        $order_object = db('order');
        $map['checkinfo'] = array('gt',1);
        if ($type == 'month') {
            //按月统计
            $start_date = input('get.start_date') ? substr(input('get.start_date'),0,7) : date('Y-m',strtotime('-12 month'));
            //过去12个月
            $end_date = input('get.end_date') ? substr(input('get.end_date'),0,7) : date('Y-m',strtotime('+0 month'));
            $i = 0;
            $month = '';
            while($month != $end_date ){
                $month = date('Y-m',strtotime('+'.$i.' month '.$start_date));
                $next_month = date('Y-m',strtotime('+'.($i+1).' month'.$start_date));
                $map['create_time'] = [
                    ['egt',$month.'-00'],
                    ['lt',$next_month.'-00'],
                ];
                $user_reg_date[] = date('y年m月', strtotime(($month)));
                $user_reg_count[] = (int)$order_object->where($map)->sum('payment');
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date = date('Y-m-d', strtotime($end_date.' +1 month')-1);
        }else if($type == 'year'){
            //按年统计
            $start_date = input('get.start_date') ?input('get.start_date') : date('Y-m-d',strtotime('-5 year'));
            //过去5年
            $end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d',strtotime('+0 year'));
            $i = 0;
            $end_date = substr($end_date,0,4);
            $year = '';
            while($year != $end_date ){
                $year = date('Y',strtotime('+'.$i.' year '.$start_date));
                $next_year = date('Y',strtotime('+'.($i+1).' year'.$start_date));
                $map['create_time'] = [
                    ['egt',$year.'-00-00'],
                    ['lt',$next_year.'-00-00'],
                ];
                $user_reg_date[] = date('Y年', strtotime(($year.'-01-01')));
                $user_reg_count[] = (int)$order_object->where($map)->sum('payment');
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y', strtotime($start_date)).'-01-01';
            $end_date = date('Y', strtotime($end_date.' +1 year')).'-01-01';
        }else{
            //按日统计 2周
            $today = strtotime(date('Y-m-d', time())); //今天
            $start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today-14*86400;
            $end_date   = input('get.end_date') ? (strtotime(input('get.end_date'))+1) : $today+86400;
            $count_day  = ($end_date-$start_date)/86400; //查询最近n天
            for($i = 0; $i < $count_day; $i++){
                $day_stamp = $start_date + $i*86400; //第n天日期
                $day_after_stamp = $start_date + ($i+1)*86400; //第n+1天日期

                $day = date('Y-m-d H:i:s',$day_stamp);
                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
                $map['create_time'] = array(
                    array('egt', $day),
                    array('lt', $day_after)
                );
                $user_reg_date[] = date('m月d日', $day_stamp);
                $user_reg_count[] = (int)$order_object->where($map)->sum('payment');
            }
            $start_date = date('Y-m-d', $start_date);
            $end_date = date('Y-m-d', $end_date-1);
        }

        $this->assign('type', $type);
        $this->assign('start_date', $start_date);
        $this->assign('end_date', $end_date);
        $this->assign('count_day', $count_day);
        $this->assign('user_reg_date', json_encode($user_reg_date));
        $this->assign('user_reg_count', json_encode($user_reg_count));
        $this->assign('meta_title', "订单数量");


        return $this->view->fetch();
    }

    public function ordercount2()
    {
//        $seventtime = \fast\Date::unixtime('day', -7);
//        $paylist = $createlist = [];
//        for ($i = 0; $i < 7; $i++)
//        {
//            $day = date("Y-m-d", $seventtime + ($i * 86400));
//            $createlist[$day] = mt_rand(20, 200);
//            $paylist[$day] = mt_rand(1, mt_rand(1, $createlist[$day]));
//        }
//        $hooks = config('addons.hooks');
//        $uploadmode = isset($hooks['upload_config_init']) && $hooks['upload_config_init'] ? implode(',', $hooks['upload_config_init']) : 'local';
//        $addonComposerCfg = ROOT_PATH . '/vendor/karsonzhang/fastadmin-addons/composer.json';
//        Config::parse($addonComposerCfg, "json", "composer");
//        $config = Config::get("composer");
//        $addonVersion = isset($config['version']) ? $config['version'] : __('Unknown');
//        $this->view->assign([
//            'totaluser'        => 35200,
//            'totalviews'       => 219390,
//            'totalorder'       => 32143,
//            'totalorderamount' => 174800,
//            'todayuserlogin'   => 321,
//            'todayusersignup'  => 430,
//            'todayorder'       => 2324,
//            'unsettleorder'    => 132,
//            'sevendnu'         => '80%',
//            'sevendau'         => '32%',
//            'paylist'          => $paylist,
//            'createlist'       => $createlist,
//            'addonversion'       => $addonVersion,
//            'uploadmode'       => $uploadmode
//        ]);

        //计算统计图日期
        $type = input('get.type','');
        $order_object = db('orderding');
        $map['checkinfo'] = array('gt',1);
        if ($type == 'month') {
            //按月统计
            $start_date = input('get.start_date') ? substr(input('get.start_date'),0,7) : date('Y-m',strtotime('-12 month'));
            //过去12个月
            $end_date = input('get.end_date') ? substr(input('get.end_date'),0,7) : date('Y-m',strtotime('+0 month'));
            $i = 0;
            $month = '';
            while($month != $end_date ){
                $month = date('Y-m',strtotime('+'.$i.' month '.$start_date));
                $next_month = date('Y-m',strtotime('+'.($i+1).' month'.$start_date));
                $map['create_time'] = [
                    ['egt',$month.'-00'],
                    ['lt',$next_month.'-00'],
                ];
                $user_reg_date[] = date('y年m月', strtotime(($month)));
                $user_reg_count[] = (int)$order_object->where($map)->count();
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date = date('Y-m-d', strtotime($end_date.' +1 month')-1);
        }else if($type == 'year'){
            //按年统计
            $start_date = input('get.start_date') ?input('get.start_date') : date('Y-m-d',strtotime('-5 year'));
            //过去5年
            $end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d',strtotime('+0 year'));
            $i = 0;
            $end_date = substr($end_date,0,4);
            $year = '';
            while($year != $end_date ){
                $year = date('Y',strtotime('+'.$i.' year '.$start_date));
                $next_year = date('Y',strtotime('+'.($i+1).' year'.$start_date));
                $map['create_time'] = [
                    ['egt',$year.'-00-00'],
                    ['lt',$next_year.'-00-00'],
                ];
                $user_reg_date[] = date('Y年', strtotime(($year.'-01-01')));
                $user_reg_count[] = (int)$order_object->where($map)->count();
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y', strtotime($start_date)).'-01-01';
            $end_date = date('Y', strtotime($end_date.' +1 year')).'-01-01';
        }else{
            //按日统计 2周
            $today = strtotime(date('Y-m-d', time())); //今天
            $start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today-14*86400;
            $end_date   = input('get.end_date') ? (strtotime(input('get.end_date'))+1) : $today+86400;
            $count_day  = ($end_date-$start_date)/86400; //查询最近n天
            for($i = 0; $i < $count_day; $i++){
                $day_stamp = $start_date + $i*86400; //第n天日期
                $day_after_stamp = $start_date + ($i+1)*86400; //第n+1天日期

                $day = date('Y-m-d H:i:s',$day_stamp);
                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
                $map['create_time'] = array(
                    array('egt', $day),
                    array('lt', $day_after)
                );
                $user_reg_date[] = date('m月d日', $day_stamp);
                $user_reg_count[] = (int)$order_object->where($map)->count();
            }
            $start_date = date('Y-m-d', $start_date);
            $end_date = date('Y-m-d', $end_date-1);
        }

        $this->assign('type', $type);
        $this->assign('start_date', $start_date);
        $this->assign('end_date', $end_date);
        $this->assign('count_day', $count_day);
        $this->assign('user_reg_date', json_encode($user_reg_date));
        $this->assign('user_reg_count', json_encode($user_reg_count));
        $this->assign('meta_title', "订单数量");


        return $this->view->fetch();
    }

    //销售额统计
    public function ordersale2()
    {

        //计算统计图日期
        $type = input('get.type','');
        $order_object = db('orderding');
        $map['checkinfo'] = array('gt',1);
        if ($type == 'month') {
            //按月统计
            $start_date = input('get.start_date') ? substr(input('get.start_date'),0,7) : date('Y-m',strtotime('-12 month'));
            //过去12个月
            $end_date = input('get.end_date') ? substr(input('get.end_date'),0,7) : date('Y-m',strtotime('+0 month'));
            $i = 0;
            $month = '';
            while($month != $end_date ){
                $month = date('Y-m',strtotime('+'.$i.' month '.$start_date));
                $next_month = date('Y-m',strtotime('+'.($i+1).' month'.$start_date));
                $map['create_time'] = [
                    ['egt',$month.'-00'],
                    ['lt',$next_month.'-00'],
                ];
                $user_reg_date[] = date('y年m月', strtotime(($month)));
                $user_reg_count[] = (int)$order_object->where($map)->sum('payment');
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date = date('Y-m-d', strtotime($end_date.' +1 month')-1);
        }else if($type == 'year'){
            //按年统计
            $start_date = input('get.start_date') ?input('get.start_date') : date('Y-m-d',strtotime('-5 year'));
            //过去5年
            $end_date = input('get.end_date') ? input('get.end_date') : date('Y-m-d',strtotime('+0 year'));
            $i = 0;
            $end_date = substr($end_date,0,4);
            $year = '';
            while($year != $end_date ){
                $year = date('Y',strtotime('+'.$i.' year '.$start_date));
                $next_year = date('Y',strtotime('+'.($i+1).' year'.$start_date));
                $map['create_time'] = [
                    ['egt',$year.'-00-00'],
                    ['lt',$next_year.'-00-00'],
                ];
                $user_reg_date[] = date('Y年', strtotime(($year.'-01-01')));
                $user_reg_count[] = (int)$order_object->where($map)->sum('payment');
                $i++;
            }
            $count_day = $i;
            $start_date = date('Y', strtotime($start_date)).'-01-01';
            $end_date = date('Y', strtotime($end_date.' +1 year')).'-01-01';
        }else{
            //按日统计 2周
            $today = strtotime(date('Y-m-d', time())); //今天
            $start_date = input('get.start_date') ? strtotime(input('get.start_date')) : $today-14*86400;
            $end_date   = input('get.end_date') ? (strtotime(input('get.end_date'))+1) : $today+86400;
            $count_day  = ($end_date-$start_date)/86400; //查询最近n天
            for($i = 0; $i < $count_day; $i++){
                $day_stamp = $start_date + $i*86400; //第n天日期
                $day_after_stamp = $start_date + ($i+1)*86400; //第n+1天日期

                $day = date('Y-m-d H:i:s',$day_stamp);
                $day_after = date('Y-m-d H:i:s',$day_after_stamp);
                $map['create_time'] = array(
                    array('egt', $day),
                    array('lt', $day_after)
                );
                $user_reg_date[] = date('m月d日', $day_stamp);
                $user_reg_count[] = (int)$order_object->where($map)->sum('payment');
            }
            $start_date = date('Y-m-d', $start_date);
            $end_date = date('Y-m-d', $end_date-1);
        }

        $this->assign('type', $type);
        $this->assign('start_date', $start_date);
        $this->assign('end_date', $end_date);
        $this->assign('count_day', $count_day);
        $this->assign('user_reg_date', json_encode($user_reg_date));
        $this->assign('user_reg_count', json_encode($user_reg_count));
        $this->assign('meta_title', "订单数量");


        return $this->view->fetch();
    }

}
