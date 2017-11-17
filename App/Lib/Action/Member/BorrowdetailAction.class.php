<?php
// 本类由系统自动生成，仅供测试用途
class BorrowdetailAction extends MCommonAction {

    public function index(){
        $this->assign("bid",intval($_GET['id']));
        $this->display();
    }

    public function borrowdetail(){
        $pre = C('DB_PREFIX');
        $borrow_id = intval($_GET['id']);
        $list = getBorrowInvest($borrow_id,$this->uid);
        
        $this->assign("list",$list);
        $data['html'] = $this->fetch();
        exit(json_encode($data));
    }

    /**
    * 乾多多还款
    * 
    */
    public function repayment(){
        $secodary = "";
        $loanconfig = FS("Webconfig/loanconfig");
        $borrow_id = intval($_GET['bid']);
        $sort_order = intval($_GET['sort_order']);
        $vo = M("borrow_info")->field('id')->where("id={$borrow_id} AND borrow_uid={$this->uid}")->find();
        if(!is_array($vo)) $this->error("数据有误");
        
        
        $borrow_qdd = M('escrow_account')->field('qdd_marked')->where("uid={$this->uid}")->find();
        $repayment = $this->repaymentList($borrow_id, $sort_order);   // 测试列表
                
        import("ORG.Loan.Escrow");
        $loan = new Escrow();
        
        foreach($repayment['list'] as $k=> $val){
            if(floatval($val['interest_fee'])){
                $secodary[0] = $loan->secondaryJsonList($loanconfig['pfmmm'], $val['interest_fee'],'利息管理费');  
            }
            $secodary && $secodary = json_encode($secodary);
            $money = $val['capital']+$val['interest'];
			$orders = date("YmdHi").$val['invest_id'].'_'.$sort_order;
            $loanList[] = $loan->loanJsonList($borrow_qdd['qdd_marked'], $val['qdd_marked'], $orders, $borrow_id, $money, '','还款',"对{$borrow_id}号标第{$sort_order}期还款",$secodary); 
            $secodary = "";
        }  
        if($repayment['is_expired']){
            $order_no = 'yqfk'.date("YmdHi").'_'.$borrow_id.'_'.$sort_order;
            $fine = floatval($repayment['call_fee'] + $repayment['expired_money']);
			if($fine>0)
				$loanList[] = $loan->loanJsonList( $borrow_qdd['qdd_marked'], $loanconfig['pfmmm'], $order_no, $borrow_id, $fine, '','逾期罚款+催收费用',"对第{$borrow_id}号标第{$sort_order}期逾期{$repayment['expired_days']}天罚款{$repayment['expired_money']}元罚款+催收费用（{$repayment['call_fee']}）元");    
        }
        
        $loanJsonList = json_encode($loanList);
        $returnURL = C('WEB_URL').U("detailReturn");
        $notifyURL = C('WEB_URL').U("notify/detail");
        $expired = "{$repayment['is_expired']}/{$repayment['expired_days']}/{$repayment['expired_money']}/{$repayment['call_fee']}";
		
        $data =  $loan->transfer($loanJsonList, $returnURL , $notifyURL, 2, 1, 1, 1, $borrow_id.'_'.$sort_order, $expired);
       
        $form =  $loan->setForm($data, 'transfer');
        echo $form;
        exit; 
        
    }
    /**
    * 计算还款金额
    * 
    * @param int $borrow_id  借款id
    * @param int $sort_order   还款期数
    * @param int $type=1 自己还款 2网站带还
    */
    private function repaymentList($borrow_id, $sort_order, $type=1)
    {
        $pre = C('DB_PREFIX');
        $loanconfig = FS("Webconfig/loanconfig"); 
        $detail = array();
        
        $borrowDetail = D('investor_detail');
        $binfo = M("borrow_info")->field("id,borrow_uid, borrow_type, borrow_money, borrow_duration,repayment_type,has_pay,total,deadline, borrow_status")->find($borrow_id);
		
        $b_member=M('members')->field("user_name")->find($binfo['borrow_uid']);
        if($binfo['has_pay']>=$sort_order) $this->error("本期已还过，不用再还");
        if( $binfo['has_pay'] == $binfo['total'])  $this->error("此标已经还完，不用再还");
        if( ($binfo['has_pay']+1)<$sort_order) $this->error("对不起，此借款第".($binfo['has_pay']+1)."期还未还，请先还第".($binfo['has_pay']+1)."期") ;
        if( $binfo['deadline']>time() && $type==2)  $this->error("此标还没逾期，不用代还"); 
        
        $accountMoney_borrower = M('member_money')->field('money_freeze,money_collect,account_money,back_money')->find($binfo['borrow_uid']);
        
        $voxe = $borrowDetail
                    ->field('sort_order,sum(capital) as capital, sum(interest) as interest,sum(interest_fee) as interest_fee,deadline,substitute_time')
                    ->where("borrow_id={$borrow_id} and sort_order={$sort_order} and pay_status=1")
                    ->group('sort_order')
                    ->find();
        
        if($voxe['deadline'] < time()){//此标已逾期
            $is_expired = 1; 
            $expired_days = getExpiredDays($voxe['deadline']);
            $expired_money = getExpiredMoney($expired_days,$voxe['capital'],$voxe['interest']); // 预期管理费
            $call_fee = getExpiredCallFee($expired_days,$voxe['capital'],$voxe['interest']); // 催收费用
            //逾期的相关计算
        }else{
            $is_expired = 0;
            $expired_days = 0;
            $expired_money = 0;
            $call_fee = 0;
        }       
        $detail['is_expired'] = $is_expired;
        //逾期的相关计算 start
        $detail['expired_days'] = $expired_days;
        $detail['expired_money'] = $expired_money;
        $detail['call_fee'] = $call_fee;
        //逾期的相关计算 end
     
        if($type==1 && $binfo['borrow_type']<>3 && ($accountMoney_borrower['account_money']+$accountMoney_borrower['back_money'])<($vo['capital']+$vo['interest']+$expired_money+$call_fee)) 
        $this->error("帐户可用余额不足，本期还款共需".($voxe['capital']+$voxe['interest']+$expired_money+$call_fee)."元，请先充值");
        
        $vo = $borrowDetail
                    ->field('invest_id, investor_uid, sort_order,capital, interest, interest_fee , deadline,substitute_time')
                    ->where("borrow_id={$borrow_id} and sort_order={$sort_order} and pay_status=1")
                    ->select();
       
        foreach($vo as $k=>$v){
            if($v['substitute_time'] > 0){   //已代还 将资金给网站
                $v['qdd_marked'] = $loanconfig['pfmmm'];  
            }else { // 没有待还将资金还给投资人
                $escrow = M('escrow_account')->field('qdd_marked')->where("uid={$v['investor_uid']}")->find();
                $v['qdd_marked'] = $escrow['qdd_marked'];
            
            
            }
            $detail['list'][$k] = $v;
        }
//print_R($detail);exit;
        return $detail;
        
    }
    /**
    * 还款后台通知地址
    * 
    */
    public function detailReturn()
    {  
        import("ORG.Loan.Escrow");
        $loan = new Escrow();
        $lang = L('invest'); 
        $msg = $lang[$_POST['ResultCode']];
        if($loan->loanVerify($_POST)){
			//print_R($_POST);exit;
            
            if(intval($_POST['ResultCode'])==88){
                $this->success($msg, U('/member/index'));
                exit;
            }
        }
        $this->error($msg, U('/member/index'));
    }

}