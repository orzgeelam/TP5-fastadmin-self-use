<?php
namespace \app\common\model;

use think\Model;
use think\Db;

// 商品
class Goods extends Model
{
	// 获取列表
	public function get_lists($cond)
	{
		$map = [];
		$limit;
		$start;
		if (isset($cond['map'])) {

		}
		if (isset($cond['order'])) {
			$order = $cond['order'];
		} else {
			$order = 'weigh desc';
		}
		$lists = Db::table('orz_goods')->where($map)->limit($start, $limit)->order($order)->select();
		return $lists;
	}
}