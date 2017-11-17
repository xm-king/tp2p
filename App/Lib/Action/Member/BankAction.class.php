<?php
// 本类由系统自动生成，仅供测试用途
class BankAction extends MCommonAction {

    public function index(){
		$this->display();
    }

    public function bank(){
		$ids = M('members_status')->getFieldByUid($this->uid,'id_status');
        if($ids!=1){
            $data['html'] = '<style type="text/css"> .error_msg{padding:20px; font-size:17px; color:#333333;} .error_msg a{color:#1D53BF; font-weight: bold;}  </style>
            <div class="error_msg">您还未完成身份验证，请先进行实名认证.点击这里<a href="'.__APP__.'/member/verify?id=1#fragment-3">进行实名认证</a></div>';
            
        }else{
            if(!M('escrow_account')->where("uid={$this->uid} and account <>''")->count('uid')){
               $data['html'] = '<style type="text/css"> .error_msg{padding:20px; font-size:17px; color:#333333;} .error_msg a{color:#1D53BF; font-weight: bold;}  </style>
               <div class="error_msg">你还未绑定托管账户，请先绑定托管账户:马上<a href="'.U('bindingAccount').'" >绑定托管账户</a></div>'; 
               exit(json_encode($data));
            }
			$voinfo = M("member_info")->field('idcard,real_name')->find($this->uid);
			$vobank = M("member_banks")->field(true)->where("uid = {$this->uid} and bank_num !=''")->find();
			$this->assign("voinfo",$voinfo);
			$this->assign("vobank",$vobank);
			$data['html'] = $this->fetch();
		}

		exit(json_encode($data));
    }
	public function bindbank(){
		
		// 银行卡绑定操作
        $returnURL = C('WEB_URL').U("member/bank/bindbankret");
        $notifyURL = C('WEB_URL').U("notify/bindbank");
        $user = M('escrow_account')->field('qdd_marked')->where("uid={$this->uid}")->find();
        
        import("ORG.Loan.Escrow");
        $loan = new Escrow();
		$uid = $loan->rsa->encrypt($this->uid);
        $data =  $loan->toloanfastpay($user['qdd_marked'], 2, $returnURL, $notifyURL,'','','','','','',$uid);
        $form =  $loan->setForm($data, 'toloanfastpay');
        echo $form;
        exit;
	}
	
	
	public function getarea(){
		$rid = intval($_GET['rid']);
		if(empty($rid)) return;
		$map['reid'] = $rid;
		$alist = M('area')->field('id,name')->order('sort_order DESC')->where($map)->select();
		if(count($alist)===0){
			$str="<option value=''>--该地区下无下级地区--</option>\r\n";
		}else{
			foreach($alist as $v){
				$str.="<option value='{$v['id']}'>{$v['name']}</option>\r\n";
			}
		}
		$data['option'] = $str;
		$res = json_encode($data);
		echo $res;
	}	
    
    /**
    * 绑定乾多多账号
    * 
    */
    public function  bindingAccount()
    {
        header("Content-type:text/html;charset=utf-8");
        $status = M('members_status')->field('*')->where("uid={$this->uid}")->find();
        //$status['email_status']!=1 &&  $this->error('请先认证邮箱再来绑定托管账户', '/member/verify#fragment-1');
        $status['phone_status']!=1 &&  $this->error('请先认证手机号再来绑定托管账户', '/member/verify#fragment-2');
        $status['id_status']!=1 &&  $this->error('请先实名认证再来绑定托管账户', '/member/verify#fragment-3');
        
        if(M('escrow_account')->where("uid={$this->uid}")->count('uid')){
             $this->error('您已经绑定了托管账户，无需重复绑定', '/member.html');   
        }
        
        $user_info = M('members')->field('user_name, user_email, user_phone')->where("id={$this->uid}")->find();
        $id_info = M("member_info")->field('idcard, real_name')->where("uid={$this->uid}")->find();
        import("ORG.Loan.Escrow");
        $loan = new Escrow();

        $data =  $loan->registerAccount($user_info['user_phone'], $user_info['user_email'], $id_info['real_name'], $id_info['idcard'],$user_info['user_name']);
        $form =  $loan->setForm($data, 'register');
        echo $form;
        exit; 

    }
    /**
    * 绑定乾多多返回地址
    * 
    */
    public function bindReturn()
    {
		
        $lang = L('Binding');
        $msg = $lang[$_POST['ResultCode']];
		$_POST['ResultCode']==88 && $msg = "成功绑定托管账户！";
        $this->assign('msg', $msg);
        $this->display();
        
    }
    
    /**
    * 余额查询接口
    */
    public function balance()
    {
        import("ORG.Loan.Escrow");
        $loan = new Escrow();
        $qdd = M("escrow_account")->field('qdd_marked')->where('uid='.$this->uid)->find();
        $balance  = $loan->balance($qdd['qdd_marked']);
        if(!empty($balance)){
            $Funds = explode('|', $balance);
            $money = M('member_money')->field('back_money, money_freeze, money_collect, account_money')->where('uid='.$this->uid)->find();
            $account_money = $Funds[0]- $money['back_money'];
            M('member_money')->where('uid='.$this->uid)->save(array('account_money'=>$account_money, 'money_freeze'=>$Funds[2]));
            $moneylog['uid'] =  $this->uid;
            $moneylog['type'] = 51;
            $moneylog['affect_money'] = $account_money-$money['account_money'];
            $moneylog['account_money'] = $account_money;
            $moneylog['back_money'] = $money['back_money'];
            $moneylog['collect_money'] =  $money['money_collect'];
            $moneylog['freeze_money']  =  $Funds[2];
            $moneylog['info'] = '查询余额';
            $moneylog['add_time'] = time();
            $moneylog['add_ip'] = get_client_ip();
            $moneylog['target_uid'] = 0;
            $moneylog['target_uname'] = '第三方托管';
            
            M("member_moneylog")->add($moneylog);
            echo 'success';
        }else{
            echo 'error';
        }
    }
	public function checkLoan()
    {
        $msg = '';
        $url = '';
        $status = M('members_status')->field('*')->where("uid={$this->uid}")->find();
        /*if($status['email_status']!=1){
            $msg = "请点击确定完成邮箱验证";
            $url = '/member/verify#fragment-1';    
        }else*/
        if($status['phone_status']!=1){
            $msg = "请点击确定完成手机验证";
            $url = '/member/verify#fragment-2';
        }elseif($status['id_status']!=1){
            $msg = "请点击确定完成实名认证";
            $url = '/member/verify#fragment-3';
        }
        
        if(!$msg){
            $escrow = M('escrow_account')->field('qdd_marked')->where("uid={$this->uid}")->find();
            if(!$escrow['qdd_marked']){
                $url = U('member/bank/bindingAccount');
                $msg = "点击确定完成绑定托管";
            }else{
                $msg = 'ok';
            }
        }
        echo json_encode(array('msg'=>$msg, 'url'=>$url));
        
    }


	/**
	*	绑定银行卡通知地址
	**/
	public function bindbankret()
	{
		import("ORG.Loan.Escrow");
        $loan = new Escrow();
        if($loan->toloanfastpayVerify($_POST)){
            $msg = $_POST['Message'];
            if($_POST['ResultCode']!=88){
                $this->error($msg,'/member/bank/index.html#fragment-1'); 
            }else{
                $this->success('银行卡绑定'.$msg, '/member/bank/index.html#fragment-1');
            }
        }
        $msg = "返回信息被篡改";
        $this->error($msg, '/member/bank/index.html#fragment-1'); 
	}
}