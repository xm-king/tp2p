<?php
// 本类由系统自动生成，仅供测试用途
class TinvestAction extends HCommonAction {
    public function index(){
		static $newpars;
		$Bconfig = require C("APP_ROOT")."Conf/borrow_config.php";
		$per = C('DB_PREFIX');
		
		$curl = $_SERVER['REQUEST_URI'];
		$urlarr = parse_url($curl);
		parse_str($urlarr['query'],$surl);//array获取当前链接参数，2.
		
		$urlArr = array('borrow_type','interest_rate','borrow_duration','leve');
		$leveconfig = FS("Webconfig/leveconfig");
		foreach($urlArr as $v){
			$newpars = $surl;//用新变量避免后面的连接受影响
			unset($newpars[$v],$newpars['type'],$newpars['order_sort'],$newpars['orderby']);//去掉公共参数，对掉当前参数
			foreach($newpars as $skey=>$sv){
				if($sv=="all") unset($newpars[$skey]);//去掉"全部"状态的参数,避免地址栏全满
			}
			$newurl = http_build_query($newpars);//生成此值的链接,生成必须是即时生成
			$searchUrl[$v]['url'] = $newurl;
			$searchUrl[$v]['cur'] = empty($_GET[$v])?"all":text($_GET[$v]);
		}
		
		$searchMap['interest_rate'] = array("all"=>"不限制","0-10"=>"10%以下","10-15"=>"10%-15%","20-100"=>"20%以上");
		$searchMap['borrow_duration'] = array("all"=>"不限制","0-3"=>"3个月以内","4-6"=>"3-6个月","7-12"=>"6-12个月","13-24"=>"12-24个月");
		$searchMap['leve'] = array("all"=>"不限制","{$leveconfig['1']['start']}-{$leveconfig['1']['end']}"=>"{$leveconfig['1']['name']}","{$leveconfig['2']['start']}-{$leveconfig['2']['end']}"=>"{$leveconfig['2']['name']}","{$leveconfig['3']['start']}-{$leveconfig['3']['end']}"=>"{$leveconfig['3']['name']}","{$leveconfig['4']['start']}-{$leveconfig['4']['end']}"=>"{$leveconfig['4']['name']}","{$leveconfig['5']['start']}-{$leveconfig['5']['end']}"=>"{$leveconfig['5']['name']}","{$leveconfig['6']['start']}-{$leveconfig['6']['end']}"=>"{$leveconfig['6']['name']}","{$leveconfig['7']['start']}-{$leveconfig['7']['end']}"=>"{$leveconfig['7']['name']}");

		$search = array();
		//搜索条件
		foreach($urlArr as $v){
			if($_GET[$v] && $_GET[$v]<>'all'){
				switch($v){
					case 'leve':
						$barr = explode("-",text($_GET[$v]));
						$search["m.credits"] = array("between",$barr);
					break;
					case 'borrow_type':
						$search["b.borrow_type"] = intval($_GET[$v]);
					break;
					case 'interest_rate':
						$barr = explode("-",text($_GET[$v]));
						$search["b.borrow_interest_rate"] = array("between",$barr);
					break;
					default:
						$barr = explode("-",text($_GET[$v]));
						$search["b.".$v] = array("between",$barr);
					break;
				}
			}
		}
		//searchMap
		$search['b.is_show'] = array("in",'0,1');
		//$search['b.borrow_status'] = array('neq','3');
		$str = "%".urldecode($_REQUEST['searchkeywords'])."%";
		if($_GET['is_keyword']=='1'){
			$search['m.user_name']=array("like",$str);
		}elseif($_GET['is_keyword']=='2'){
			$search['b.borrow_name']=array("like",$str);
		}
		$parm['map'] = $search;
		$parm['pagesize'] = 10;

		$parm['orderby']="b.is_show desc,b.progress asc";
		$list = getTBorrowList($parm);
		$this->assign("Sorder",$Sorder);
		$this->assign("searchUrl",$searchUrl);
		$this->assign("searchMap",$searchMap);
		$this->assign("Bconfig",$Bconfig);
		$this->assign("list",$list);
		$this->display();
    }


////////////////////////////////////////////////////////////////////////////////////
    public function tdetail(){
		
		if($_GET['type']=='commentlist'){
			//评论
			$cmap['tid'] = intval($_GET['id']);
			$clist = getCommentList($cmap,5);
			$this->assign("commentlist",$clist['list']);
			$this->assign("commentpagebar",$clist['page']);
			$this->assign("commentcount",$clist['count']);
			$data['html'] = $this->fetch('commentlist');
			exit(json_encode($data));
		}


		$pre = C('DB_PREFIX');
		$id = intval($_GET['id']);
		$Bconfig = require C("APP_ROOT")."Conf/borrow_config.php";
		
		//合同ID
		if($this->uid){
			$invs = M('transfer_borrow_investor')->field('id')->where("borrow_id={$id} AND (investor_uid={$this->uid} OR borrow_uid={$this->uid})")->find();
			if($invs['id']>0) $invsx=$invs['id'];
			elseif(!is_array($invs)) $invsx='no';
		}else{
			$invsx='login';
		}
		$this->assign("invid",$invsx);
		//合同ID
		//borrowinfo
		//$borrowinfo = M("borrow_info")->field(true)->find($id);
		$borrowinfo = M("transfer_borrow_info b")->join("{$pre}transfer_detail d ON d.borrow_id=b.id")->field(true)->find($id);
		/*if(!is_array($borrowinfo) || $borrowinfo['is_show'] == 0){
			$this->error("数据有误或此标已认购完");
		}*/
		$borrowinfo['progress'] = getfloatvalue($borrowinfo['transfer_out']/$borrowinfo['transfer_total'] * 100, 2);
		$borrowinfo['need'] = getfloatvalue(($borrowinfo['transfer_total'] - $borrowinfo['transfer_out'])*$borrowinfo['per_transfer'], 2 );
		$borrowinfo['updata'] = unserialize($borrowinfo['updata']);
		
		
		if($borrowinfo['danbao']!=0 ){
			$danbao = M('article')->field('id,title')->where("type_id=7 and id={$borrowinfo['danbao']}")->find();
			$borrowinfo['danbao']=$danbao['title'];//担保机构
			$borrowinfo['danbaoid'] = $danbao['id'];
		}else{
			$borrowinfo['danbao']='暂无担保机构';//担保机构
		}
		if(time()>=$borrowinfo['deadline']||$borrowinfo['progress']==100){
			$borrowinfo['restday'] =0;
			$borrowinfo['currentday'] = $borrowinfo['add_time'];
		}else{
			$borrowinfo['restday'] = ceil(($borrowinfo['deadline'] - time())/(24*60*60));
			$borrowinfo['currentday'] = time();
		}
		$now=time();
	    $borrowinfo['aa']=floor($borrowinfo['collect_day']-$now);
		$borrowinfo['lefttime'] = $borrowinfo['collect_day']-$now;
		$borrowinfo['leftday'] = ceil(($borrowinfo['collect_day']-$now)/3600/24);
	    $borrowinfo['leftdays'] = floor(($borrowinfo['collect_day']-$now)/3600/24).'天以上';
		$money = 100000;
		switch($borrowinfo['repayment_type']){//收益
		    case 2://等额本息
			    $monthData['duration'] = $borrowinfo['borrow_duration'];
				$monthData['money'] = $money;
				$monthData['year_apr'] = $borrowinfo['borrow_interest_rate'];
				$monthData['type'] = "all";
				$repay_detail = EqualMonth($monthData);
				$borrowinfo['shouyi'] = $repay_detail['repayment_money'] - $money;
			    break;
			case 4://每月还息
			    $monthData['month_times'] = $borrowinfo['borrow_duration'];
				$monthData['account'] = $money;
				$monthData['year_apr'] = $borrowinfo['borrow_interest_rate'];
				$monthData['type'] = "all";
				$repay_detail = EqualEndMonth($monthData);
				$borrowinfo['shouyi'] =$repay_detail['repayment_account'] - $money;
			    break;
			case 5://一次性还款
			    $borrowinfo['shouyi'] = floor($borrowinfo['borrow_interest_rate']*$money*$borrowinfo['borrow_duration']/12)/100;
			    break;
		}
		$this->assign("vo", $borrowinfo);
		
		//帐户资金情况
		$this->assign("investInfo", getMinfo($this->uid,true));					
		//帐户资金情况
							
		//此标借款利息还款相关情况
		//memberinfo
		$memberinfo = M("members m")->field("m.id,m.customer_name,m.customer_id,m.user_name,m.reg_time,m.credits,fi.*,mi.*,mm.*")->join("{$pre}member_financial_info fi ON fi.uid = m.id")->join("{$pre}member_info mi ON mi.uid = m.id")->join("{$pre}member_money mm ON mm.uid = m.id")->where("m.id={$borrowinfo['borrow_uid']}")->find();
		$areaList = getArea();
		$memberinfo['location'] = $areaList[$memberinfo['province']].$areaList[$memberinfo['city']];
		$memberinfo['location_now'] = $areaList[$memberinfo['province_now']].$areaList[$memberinfo['city_now']];
		$this->assign("minfo",$memberinfo);
		//memberinfo
		
		//investinfo
		$fieldx = "bi.investor_capital,bi.transfer_month,bi.transfer_num,bi.add_time,m.user_name,bi.is_auto,bi.final_interest_rate";
		$investinfo = M("transfer_borrow_investor bi")->field($fieldx)->join("{$pre}members m ON bi.investor_uid = m.id")->where("bi.borrow_id={$id} and bi.loanno<>''")->order("bi.id DESC")->select();
		$this->assign("investinfo",$investinfo);
		//investinfo
		
		$oneday = 86400;
		$time_1 = time() - 30 * $oneday.",".time();
		$time_6 = time() - 180 * $oneday.",".time();
		$time_12 = time() - 365 * $oneday.",".time();
		$mapxr['borrow_id'] = $id;
		$this->assign("time_all_out", M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		$mapxr['add_time'] = array("between","{$time_1}");
		$this->assign("time_1_out", M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		$mapxr['add_time'] = array("between","{$time_6}");
		$this->assign("time_6_out",M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		$mapxr['add_time'] = array("between","{$time_12}");
		$this->assign("time_12_out",M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		
		$mapxr = array();
		$mapxr['borrow_id'] = $id;
		$mapxr['status'] = 2;
		$this->assign("time_all_back", M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		$mapxr['back_time'] = array("between","{$time_1}");
		$this->assign("time_1_back",M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		$mapxr['back_time'] = array("between","{$time_6}");
		$this->assign("time_6_back", M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		$mapxr['back_time'] = array("between","{$time_12}");
		$this->assign("time_12_back", M("transfer_borrow_investor")->where($mapxr)->sum("transfer_num"));
		
		//评论
		$cmap['tid'] = $id;
		$clist = getCommentList($cmap,5);
		$this->assign("Bconfig",$Bconfig);
		$this->assign("commentlist",$clist['list']);
		$this->assign("commentpagebar",$clist['page']);
		$this->assign("commentcount",$clist['count']);
		$this->display();
    }
	
	public function investcheck()
	{
		$pre = C("DB_PREFIX");
		if (!$this->uid)
		{
			ajaxmsg("", 3);
		}
		$pin = md5($_POST['pin']);
		$borrow_id = intval($_POST['borrow_id']);
		$tnum = intval($_POST['tnum']);
		$month = intval($_POST['month']);
		$m = M("member_money")->field('account_money,back_money,money_collect')->find($this->uid);
		$amoney = $m['account_money']+$m['back_money'];
		$uname = session("u_user_name");
		$vm = getMinfo($this->uid,"m.pin_pass");
		$pin_pass = $vm['pin_pass'];
		$amoney = floatval($amoney);
		$binfo = M("transfer_borrow_info")->field( "transfer_out,transfer_back,transfer_total,per_transfer,is_show,deadline,min_month,increase_rate,reward_rate,borrow_duration")->find($borrow_id);
		$max_month = $binfo['borrow_duration'];//getTransferLeftmonth($binfo['deadline']);
		$min_month = $binfo['min_month'];
		$max_num = $binfo['transfer_total'] - $binfo['transfer_out'];
		if($tnum<1){
			ajaxmsg("购买份数必须大于等于1份！", 3);
		}
		if ($max_num < $tnum)
		{
			ajaxmsg( "本标还能认购最大份数为".$max_num."份，请重新输入认购份数", 3 );
		}
		$money = $binfo['per_transfer'] * $tnum;
		if ($amoney < $money)
		{
			$msg = "尊敬的{$uname}，您准备认购{$money}元，但您的账户可用余额为{$amoney}元，您要先去充值吗？";
			ajaxmsg($msg, 2);
		}
		else
		{
			$msg = "尊敬的{$uname}，您的账户可用余额为{$amoney}元，您确认认购{$money}元吗？";
			ajaxmsg($msg, 1);
		}
	}
	
	public function investmoney()
	{
		if(!$this->uid){
            $this->error('请先登录',U('member/common/login')); 
        }
        checkAuthorize($this->uid,array('invest_auth','secondary_percent')); 
		$borrow_id = intval($_POST['T_borrow_id']);
		$tnum = intval($_POST['transfer_invest_num']);
		$month = intval($_POST['transfer_invest_month']);
		$m = M("member_money")->field('account_money,back_money,money_collect')->find($this->uid);
		$amoney = $m['account_money']+$m['back_money'];
		$uname = session("u_user_name");
		$binfo = M("transfer_borrow_info")
                    ->field( "borrow_uid,borrow_interest_rate,borrow_money,transfer_out,transfer_back,transfer_total,per_transfer,is_show,deadline,min_month,increase_rate,reward_rate,borrow_duration")
                    ->find($borrow_id);
		
		if($this->uid == $binfo['borrow_uid']){
           $this->error("不能去投自己的标"); 
        } 
		$max_month = $binfo['borrow_duration'];
		$min_month = $binfo['min_month'];
		$max_num = $binfo['transfer_total'] - $binfo['transfer_out'];
		if($tnum<1){
			$this->error("购买份数必须大于等于1份！");
		}
		
		if($max_num < $tnum){
			$this->error( "本标还能认购最大份数为".$max_num."份，请重新输入认购份数" );
		}
		$money = $binfo['per_transfer'] * $tnum;
		if($amoney < $money){
			$this->error( "尊敬的{$uname}，您准备认购{$money}元，但您的账户可用余额为{$amoney}元，请先去充值再认购.",__APP__."/member/charge#fragment-1");
		}
		$vm = getMinfo($this->uid,"m.pin_pass,mm.invest_vouch_cuse,mm.money_collect");
		$tinvest_id = TinvestMoney($this->uid,$borrow_id,$tnum,$month);//投企业直投
        
		if($tinvest_id){
            $loanconfig = FS("Webconfig/loanconfig");
            $orders = 'T'.date("YmdHi").$tinvest_id;
			// 发送到乾多多
            $invest_qdd = M("escrow_account")->field('*')->where("uid={$this->uid}")->find();
            $borrow_qdd = M("escrow_account")->field('*')->where("uid={$binfo['borrow_uid']}")->find();
            $invest_info = M("transfer_borrow_investor")->field("reward_money, borrow_fee")->where("id={$tinvest_id}")->find();
            $secodary = '';
            import("ORG.Loan.Escrow");
            $loan = new Escrow();
            if($invest_info['reward_money']>0.00){  // 投标奖励
                $secodary[] = $loan->secondaryJsonList($invest_qdd['qdd_marked'], $invest_info['reward_money'],'二次分配', '投标奖励'); 
            }
            if($invest_info['borrow_fee']>0.00){  // 借款管理费
                $secodary[] = $loan->secondaryJsonList($loanconfig['pfmmm'], $invest_info['borrow_fee'],'二次分配', '借款管理费'); 
            }
            
            $secodary && $secodary = json_encode($secodary);
            
            $loanList = $loan->loanJsonList($invest_qdd['qdd_marked'], $borrow_qdd['qdd_marked'], $orders, 'T_'.$borrow_id, $money, $binfo['borrow_money'],'投标',"对{$borrow_id}号企业直投进行投标",$secodary);
			
            $loanJsonList = json_encode($loanList);
            $returnURL = C('WEB_URL').U("tinvest/investReturn");
            $notifyURL = C('WEB_URL').U("tinvest/notify");
            $data =  $loan->transfer($loanJsonList, $returnURL , $notifyURL,1,1,2,1); // 自动到帐
			
            $form =  $loan->setForm($data, 'transfer');
            echo $form;
            exit;
		}else{
			$this->error("对不起，认购失败，请重试!");
		}
	}

	public function addcomment(){
		$data['comment'] = text($_POST['comment']);
		if(!$this->uid)  ajaxmsg("请先登陆",0);
		if(empty($data['comment']))  ajaxmsg("留言内容不能为空",0);
		$data['type'] = 2;
		$data['add_time'] = time();
		$data['uid'] = $this->uid;
		$data['uname'] = session("u_user_name");
		$data['tid'] = intval($_POST['tid']);
		$data['name'] = M('transfer_borrow_info')->getFieldById($data['tid'],'borrow_name');
		$newid = M('comment')->add($data);
		//$this->display("Public:_footer");
		if($newid) ajaxmsg();
		else ajaxmsg("留言失败，请重试",0);
	}
	
	public function jubao(){
		if($_POST['checkedvalue']){
			$data['reason'] = text($_POST['checkedvalue']);
			$data['text'] = text($_POST['thecontent']);
			$data['uid'] = $this->uid;
			$data['uemail'] = text($_POST['uemail']);
			$data['b_uid'] = text($_POST['b_uid']);
			$data['b_uname'] = text($_POST['theuser']);
			$data['add_time'] = time();
			$data['add_ip'] = get_client_ip();
			$newid = M('jubao')->add($data);
			if($newid) exit("1");
			else exit("0");
		}else{
			$id=intval($_GET['id']);
			$u['id'] = $id;
			$u['uname']=M('members')->getFieldById($id,"user_name");
			$u['uemail']=M('members')->getFieldById($this->uid,"user_email");
			$this->assign("u",$u);
			$data['content'] = $this->fetch("Public:jubao");
			exit(json_encode($data));
		}
	}
	
	public function ajax_invest()
	{
		if ( !$this->uid )
		{
			ajaxmsg( "请先登陆", 0 );
		}
		$pre = C( "DB_PREFIX" );
		$id = intval( $_GET['id'] );
		$num = intval( $_GET['num'] );
		$Bconfig = require( C("APP_ROOT" )."Conf/borrow_config.php" );
		$field = "id,borrow_uid,borrow_money,borrow_interest_rate,borrow_duration,repayment_type,transfer_out,transfer_back,transfer_total,per_transfer,is_show,deadline,min_month,increase_rate,reward_rate";
		$vo = M("transfer_borrow_info" )->field($field)->find($id);
		if ($this->uid == $vo['borrow_uid'])
		{
			ajaxmsg("不能去投自己的标", 0);
		}
		if ($vo['transfer_out'] == $vo['transfer_total'])
		{
			ajaxmsg( "此标可认购份数为0", 0 );
		}
		if ($vo['is_show'] == 0)
		{
			ajaxmsg( "只能投正在借款中的标", 0 );
		}
		$vo['transfer_leve'] = $vo['transfer_total'] - $vo['transfer_out'];
		$vo['uname'] = M("members")->getFieldById($vo['borrow_uid'], "user_name");
		$vm = getMinfo($this->uid,'mm.account_money,mm.back_money,mm.money_collect');
		$amoney = $vm['account_money']+$vm['back_money'];
		$this->assign( "vo", $vo );
		$this->assign( "account_money", $amoney);
		$this->assign( "Bconfig", $Bconfig );
		$this->assign( "num", $num );
		$data['content'] = $this->fetch();
		ajaxmsg($data);
	}
	public function ajax_tanchu(){
	    $id =intval( $_GET['id'] );
		$ziduan = $_GET['ziduan'];
		$field = "borrow_id,borrow_breif,borrow_capital,borrow_use,borrow_risk";
		$vo = M("transfer_detail")->field($field)->find($id);
		$this->assign("vo",$vo[$ziduan]);
		$data['content'] = $this->fetch();
		ajaxmsg($data);
	}			
	public function getarea(){
		$rid = intval($_GET['rid']);
		if(empty($rid)){
			$data['NoCity'] = 1;
			exit(json_encode($data));
		}
		$map['reid'] = $rid;
		$alist = M('area')->field('id,name')->order('sort_order DESC')->where($map)->select();

		if(count($alist)===0){
			$str="<option value=''>--该地区下无下级地区--</option>\r\n";
		}else{
			if($rid==1) $str.="<option value='0'>请选择省份</option>\r\n";
			foreach($alist as $v){
				$str.="<option value='{$v['id']}'>{$v['name']}</option>\r\n";
			}
		}
		$data['option'] = $str;
		$res = json_encode($data);
		echo $res;
	}	
	
	public function addfriend(){
		if(!$this->uid) ajaxmsg("请先登陆",0);
		$fuid = intval($_POST['fuid']);
		$type = intval($_POST['type']);
		if(!$fuid||!$type) ajaxmsg("提交的数据有误",0);
		
		$save['uid'] = $this->uid;
		$save['friend_id'] = $fuid;
		$vo = M('member_friend')->where($save)->find();	
		
		if($type==1){//加好友
		if($this->uid == $fuid) ajaxmsg("您不能对自己进行好友相关的操作",0);
			if(is_array($vo)){
				if($vo['apply_status']==3){
					$msg="已经从黑名单移至好友列表";
					$newid = M('member_friend')->where($save)->setField("apply_status",1);
				}elseif($vo['apply_status']==1){
					$msg="已经在你的好友名单里，不用再次添加";
				}elseif($vo['apply_status']==0){
					$msg="已经提交加好友申请，不用再次添加";
				}elseif($vo['apply_status']==2){
					$msg="好友申请提交成功";
					$newid = M('member_friend')->where($save)->setField("apply_status",0);
				}
			}else{
				$save['uid'] = $this->uid;
				$save['friend_id'] = $fuid;
				$save['apply_status'] = 0;
				$save['add_time'] = time();
				$newid = M('member_friend')->add($save);	
				$msg="好友申请成功";
			}
		}elseif($type==2){//加黑名单
		if($this->uid == $fuid) ajaxmsg("您不能对自己进行黑名单相关的操作",0);
			if(is_array($vo)){
				if($vo['apply_status']==3) $msg="已经在黑名单里了，不用再次添加";
				else{
					$msg="成功移至黑名单";
					$newid = M('member_friend')->where($save)->setField("apply_status",3);	
				}
			}else{
				$save['uid'] = $this->uid;
				$save['friend_id'] = $fuid;
				$save['apply_status'] = 3;
				$save['add_time'] = time();
				$newid = M('member_friend')->add($save);	
				$msg="成功加入黑名单";
			}
		}
		if($newid) ajaxmsg($msg);
		else ajaxmsg($msg,0);
	}
	
	
	public function innermsg(){
		if(!$this->uid) ajaxmsg("请先登陆",0);
		$fuid = intval($_GET['uid']);
		if($this->uid == $fuid) ajaxmsg("您不能对自己进行发送站内信的操作",0);
		$this->assign("touid",$fuid);
		$data['content'] = $this->fetch("Public:innermsg");
		ajaxmsg($data);
	}
	public function doinnermsg(){
		$touid = intval($_POST['to']);
		$msg = text($_POST['msg']);	
		$title = text($_POST['title']);	
		$newid = addMsg($this->uid,$touid,$title,$msg);
		if($newid) ajaxmsg();
		else ajaxmsg("发送失败",0);
		
	}
    
    private function investRollback($invest_id)
    {
        $invest_id = intval($invest_id);
        M('transfer_investor_detail')->where("invest_id={$invest_id} and pay_status = 0")->delete();
        M('transfer_borrow_investor')->where("id={$invest_id} and loanno =''")->delete();
    }
    
    /**
    * 返回地址
    * 
    */
    public function investReturn()
    {  

        import("ORG.Loan.Escrow");
        $loan = new Escrow();
        if($loan->loanVerify($_POST)){
            $lang = L('invest');  
            $msg = $lang[$_POST['ResultCode']];
            if($_POST['ResultCode']!=88){
				$loan_list = json_decode(urldecode($_POST['LoanJsonList']),  true);
				isset($loan_list[0]) ? $invest_info = $loan_list[0]:$invest_info = $loan_list;
                $orders = $invest_info['OrderNo'];
                $invest_id = substr($orders,13);
                $this->investRollback($invest_id);
                $this->error($msg, U('index')); 
				
            }else{
                $this->success($msg, U('index'));
				exit;
            }
        }
        $msg = "返回信息被篡改";
        $this->error($msg,U('index'));
    }
    /**
    * 后台通知地址
    * 
    */
    public function notify()
    {
		/*$_POST = Array( 'LoanJsonList' => '%5B%7B%22LoanOutMoneymoremore%22%3A%22m1318%22%2C%22LoanInMoneymoremore%22%3A%22m1333%22%2C%22LoanNo%22%3A%22LN19507972014060511135367195%22%2C%22OrderNo%22%3A%22T62%22%2C%22BatchNo%22%3A%22T_14%22%2C%22Amount%22%3A%22100.00%22%2C%22TransferName%22%3A%22%E6%8A%95%E6%A0%87%22%2C%22Remark%22%3A%22%E5%AF%B914%E5%8F%B7%E4%BC%81%E4%B8%9A%E7%9B%B4%E6%8A%95%E8%BF%9B%E8%A1%8C%E6%8A%95%E6%A0%87%22%2C%22SecondaryJsonList%22%3A%22%5B%7B%5C%22LoanInMoneymoremore%5C%22%3A%5C%22p67%5C%22%2C%5C%22Amount%5C%22%3A%5C%221.00%5C%22%2C%5C%22TransferName%5C%22%3A%5C%22%E4%BA%8C%E6%AC%A1%E5%88%86%E9%85%8D%5C%22%2C%5C%22Remark%5C%22%3A%5C%22%E5%80%9F%E6%AC%BE%E7%AE%A1%E7%90%86%E8%B4%B9%5C%22%7D%5D%22%7D%5D',
			'PlatformMoneymoremore' => 'p67',
			'Action' =>'1',
			'RandomTimeStamp' =>'',
			'Remark1' =>'',
			'Remark2' =>'',
			'Remark3' =>'',
			'ResultCode' => 88,
			'Message' => '成功',
			'SignInfo' => 'YE7pWnQ0ImH3cWsWDGWhDIfMwmBggFF9zKj6hZrLYGBl2dywbd+Wmtrmw/61+5cM69ASHAC1iYus pcX673El3DtKLvKX1IvoOAPIM9xoS2nXOShPzp2tNDWrVqAkT6fRA0JXNsw51UbN8tfo7lhBwv6a D+REwIcmtzR6XFzUd3w=' );*/
		
        import("ORG.Loan.Escrow");
        $loan = new Escrow();
        if($loan->loanVerify($_POST)){
			$_P_fee=get_global_setting();
            $loan_list = json_decode(urldecode($_POST['LoanJsonList']),true);
			
            isset($loan_list[0]) ? $invest_info = $loan_list[0]:$invest_info = $loan_list;
            $orders = $invest_info['OrderNo'];
            $invest_id = substr($orders,13);
            
            $datag = get_global_setting();
            $invest_integral = $datag['invest_integral'];//投资积分
            
            if(intval($_POST['ResultCode']) != 88){
                $this->investRollback($invest_id); 
				notifyMsg('企业直投',$_POST, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], $_POST['ResultCode']);
            }else{
				if(intval($_POST['Action'])!=1){
					notifyMsg('企业直投',$_POST, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], 'SUCCESS');
                    echo "SUCCESS";exit;
				}
                $pre = C('DB_PREFIX');
                $BatchNo = explode('_', $invest_info['BatchNo']);
                $borrow_id = $BatchNo['1'];
                $money =  $invest_info['Amount'];

                $binfo = M("transfer_borrow_info")
                        ->field('borrow_money, borrow_type, borrow_uid, transfer_out, transfer_total, per_transfer')
                        ->where("id={$borrow_id}")
                        ->find();
                $iinfo = M('transfer_borrow_investor')
                        ->field('investor_uid, borrow_fee, transfer_month, investor_interest, invest_fee, reward_money, loanno')
                        ->where("id={$invest_id}")
                        ->find();
                if($iinfo['loanno']){
                    die("SUCCESS");
                }
                $loan_arr = array('loanno'=>$invest_info['LoanNo']);  
				$pay_arr = array('pay_status'=>1);
                $investor_status = M('transfer_borrow_investor')->where("id={$invest_id}")->save($loan_arr); 
				
                $detail_status = M('transfer_investor_detail')->where("invest_id={$invest_id}")->save($pay_arr);         
                if(!$investor_status || !$detail_status){
                    die('保存流水号错误');
                }        
                        
                $num =  intval($money/$binfo['per_transfer']);
                $res = memberMoneyLog($iinfo['investor_uid'],37,-$money,"对{$borrow_id}号企业直投进行了投标",$binfo['borrow_uid']);
        
                //借款人资金增加
                $_borraccount = memberMoneyLog($binfo['borrow_uid'],17,$money,"第{$borrow_id}号企业直投已被认购{$money}元，{$money}元已入帐");//借款入帐
                if(!$_borraccount) exit;//借款者帐户处理出错
                $_borrfee = memberMoneyLog($binfo['borrow_uid'],18,-$iinfo['borrow_fee'],"第{$borrow_id}号企业直投({$invest_id})，扣除借款管理费{$iinfo['borrow_fee']}元");//借款管理费扣除      
                if(!$_borrfee) exit;//借款者帐户处理出错
                                        
                
                //借款天数、还款时间
                $endTime = strtotime(date("Y-m-d",time())." ".$_P_fee['back_time']);
                $deadline_last = strtotime("+{$iinfo['transfer_month']} month",$endTime);
                $getIntegralDays = intval(($deadline_last-$endTime)/3600/24);//借款天数

                //////////////////////////增加投资者的投资积分 2013-08-28 fans////////////////////////////////////

                $integ = intval($money*$getIntegralDays*$invest_integral/1000);
                if($integ>0){
                    $reintegral = memberIntegralLog($iinfo['investor_uid'],2,$integ,"对{$borrow_id}号企业直投进行投标，应获积分：".$integ."分,投资金额：".$money."元,投资天数：".$getIntegralDays."天");
                    if(isBirth($iinfo['investor_uid'])){
                        $reintegral = memberIntegralLog($iinfo['investor_uid'],2,$integ,"亲，祝您生日快乐，本站特赠送您{$integ}积分作为礼物，以表祝福。");
                    }
                }
                    
                //////////////////////////增加投资者的投资积分 2013-08-28 fans////////////////////////////////////
                    
                $res1 = memberMoneyLog($iinfo['investor_uid'],39,$money,"您对第{$borrow_id}号企业直投投标成功，冻结本金成为待收金额",$binfo['borrow_uid']);
                $res2 = memberMoneyLog($iinfo['investor_uid'],38,$iinfo['investor_interest'] - $iinfo['invest_fee'], "第{$borrow_id}号企业直投应收利息成为待收利息", $binfo['borrow_uid']);
                    
                //投标奖励
                if($iinfo['reward_money']>0){
                    $_remoney_do = false;
                    $_reward_m = memberMoneyLog($iinfo['investor_uid'],41,$iinfo['reward_money'],"第{$borrow_id}号企业直投认购成功，获取投标奖励",$binfo['borrow_uid']);
                    $_reward_m_give = memberMoneyLog($binfo['borrow_uid'],42,-$iinfo['reward_money'],"第{$borrow_id}号企业直投已被认购，支付投标奖励",$iinfo['investor_uid']);
                    if($_reward_m && $_reward_m_give) $_remoney_do = true;
                }
                //投标奖励end
                //////////////////////邀请奖励开始////////////////////////////////////////
                $vo = M('members')->field('user_name,recommend_id')->where('id='.$iinfo['investor_uid'])->find();
                if($vo['recommend_id']!=0){
                    $_rate = $_P_fee['award_invest']/1000;//推广奖励
                    $jiangli = getFloatValue($_rate * $money,2);
			
                    updateInvite($vo['recommend_id'], $jiangli, "{$vo['user_name']}对{$borrow_id}号企业直投投资{$money}元，你获得推广奖励".$jiangli."元。",$iinfo['investor_uid'], $money );
                    
                }
                /////////////////////邀请奖励结束/////////////////////////////////////////
                if(intval($binfo['transfer_out'])==0){
                    $binfo['transfer_out'] = $num;
                }
                $progress = getfloatvalue($binfo['transfer_out'] / $binfo['transfer_total'] * 100, 2);
                $upborrowsql = "update `{$pre}transfer_borrow_info` set ";
                $upborrowsql .= "`transfer_out` = `transfer_out` + {$num},";
                $upborrowsql .= "`progress`= {$progress}";
                if ($progress == 100 || ($binfo['transfer_out'] + $num == $binfo['transfer_total'])){
                    $upborrowsql .= ",`is_show` = 0";
                }
                $upborrowsql .= " WHERE `id`={$borrow_id}";
                
                $upborrow_res = M()->execute($upborrowsql);
                if(!$res || !$res1 || !$res2){
					notifyMsg('企业直投',$_POST, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], 'ERROR');
                    echo 'ERROR';exit;
                }else{
					notifyMsg('企业直投',$_POST, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], 'SUCCESS');
                    echo 'SUCCESS';exit;
                }
                 
            }
			notifyMsg('企业直投',$_POST, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], '');
        }

    }

}
?>
