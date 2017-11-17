<?php
// 本类由系统自动生成，仅供测试用途
class WelcomeAction extends ACommonAction {

	var $justlogin = true;
	
    public function index(){
		$row['borrow_1'] = M('borrow_info')->
where('borrow_status=0')->count('id');//初审
		$row['borrow_2'] = M('borrow_info')->where('borrow_status=4')->count('id');//复审
		$row['limit_a'] = M('member_apply')->where('apply_status=0')->count('id');//额度
		$row['data_up'] = M('member_data_info')->where('status=0')->count('id');//上传资料
		$row['vip_a'] = M('vip_apply')->where("status=0 and (loanno<>'' or vip_fee=0.00)")->count('id');//VIP审核	
		$row['face_a'] = M('face_apply')->where('apply_status=0')->count('id');//现场认证		
		$row['real_a'] = M('members_status')->where('id_status=3')->count('uid');//现场认证		
		$this->assign("row",$row);
		
		
		for($i=0; $i<7; $i++){
			
			$end = strtotime(date('Y-m-d', strtotime('+'.$i.' day')).' 23:59:59');
			$start = strtotime(date('Y-m-d', strtotime('+'.$i.' day')).' 00:00:00');	
			
			$deadline = '(deadline >='.$start.' and deadline <= '.$end.')';
			$arr = M("transfer_investor_detail")->field(" sum(interest) as interest, sum(capital) as capital")->where("status=7 and $deadline")->find();
			$arr['deadline'] = $end;
			$arr['money'] = $arr['interest'] + $arr['capital'];
			$arr['money'] > 0.00 && $list[] = $arr;
		}

		$this->assign('hlist', $list);
	
		$this->getServiceInfo();
        $this->getAdminInfo();
		$this->display();
    }
	
	private function getServiceInfo()
    {
        $service['service_name'] = php_uname('s');//服务器系统名称
        $service['service'] = $_SERVER['SERVER_SOFTWARE'];   //服务器版本
        $service['zend'] = 'Zend '.Zend_Version();    //zend版本号
        $service['ip'] = GetHostByName($_SERVER['SERVER_NAME']); //服务器ip
        $service['mysql'] = mysql_get_server_info();
        $service['filesize'] = ini_get("upload_max_filesize");
        
        $this->assign('service', $service);
    }
	
    private function getAdminInfo()
    {
        $id = $_SESSION['admin_id'];
        $userinfo = M('ausers a')
                    ->field('a.user_name, c.groupname')
                    ->join(C('DB_PREFIX').'acl as c on a.u_group_id = c.group_id')
                    ->where(" a.id={$id}")
                    ->find();                      
        $userinfo['last_log_time'] = $_SESSION['admin_last_log_time'];
        $userinfo['last_log_ip'] = $_SESSION['admin_last_log_ip'];
        $this->assign('user',$userinfo);
    }
	
}