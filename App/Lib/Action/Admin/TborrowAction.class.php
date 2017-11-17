<?php
class TborrowAction extends ACommonAction
{

    public function index()
    {
        $map['b.is_show'] = 1;
        $map['b.borrow_status'] = 2;
        //分页处理
        import("ORG.Util.Page");
        $count = M('transfer_borrow_info b')->join("{$this->pre}members m ON m.id=b.borrow_uid")->where($map)->count('b.id');
        $p = new Page($count, C('ADMIN_PAGE_SIZE'));
        $page = $p->show();
        $Lsql = "{$p->firstRow},{$p->listRows}";
        //分页处理
        $field= 'b.id,b.borrow_name,b.borrow_uid,b.borrow_duration,b.borrow_money,b.borrow_fee,b.borrow_interest_rate,b.repayment_type,b.transfer_out,b.transfer_total,b.add_time,m.user_name,b.level_can,b.borrow_max,progress,b.is_tuijian,b.is_auto';
        $list = M('transfer_borrow_info b')->field($field)->join("{$this->pre}members m ON m.id=b.borrow_uid")->where($map)->limit($Lsql)->order("b.id DESC")->select();
        $list = $this->_listFilter($list);
        $this->assign("list", $list);
        $this->assign("pagebar", $page);
        $this->assign("xaction",ACTION_NAME);
        $this->display();
    }

    public function endtran()
    {
        $map['is_show'] = 0;
        $map['borrow_status'] = 7;
		
        $field ="id,borrow_name,borrow_uid,borrow_duration,borrow_money,borrow_interest_rate,repayment_type,transfer_out,transfer_total,add_time,is_tuijian,is_auto";
        $this->_list(D("Tborrow"), $field, $map, "id", "DESC" );
        //dump(M()->GetLastsql());exit;
        $this->display();
    }

    public function _addFilter()
    {
        $btype = array( "3" => "企业直投");
        $this->assign("borrow_type", $btype );
        
        $vo = M('members')->field("id,user_name")->where("is_transfer=1")->select();//查询出所有流转会员
        $userlist = array();
        if(is_array($vo)){
            foreach($vo as $key => $v){
                $userlist[$v['id']]=$v['user_name'];
            }
        }
        $this->assign("userlist",$userlist);//流转会员
    }

    public function doAdd( )
    {
        $model = M("transfer_borrow_info");
        $model2 = M("transfer_detail");
        if (false === $model->create()) {
            $this->error($model->getError());
        }
        if (false === $model2->create()) {
            $this->error($model->getError());
        }
        $model->startTrans();
        $model->total = 1;
        $model->repayment_type = 5;
        $model->borrow_status = 2;
        $model->add_time = time();
        $model->deadline = time() + $_POST['borrow_duration'] * 30 * 24 * 3600;
        //$model->deadline = strtotime($model->deadline);
        $model->add_ip = get_client_ip();
        //$model->level_can = intval($_POST['level_can']);
        $model->borrow_max = intval($_POST['borrow_max']);
        foreach($_POST['updata_name'] as $key=>$v){
            $updata[$key]['name'] = $v;
            $updata[$key]['time'] = $_POST['updata_time'][$key];
        }
        $model->updata = serialize($updata);

        if(!empty($_FILES['imgfile']['name'])){
            $this->saveRule = date("YmdHis",time()).rand(0,1000);
            $this->savePathNew = C('ADMIN_UPLOAD_DIR').'Product/';
            $this->thumbMaxWidth = C('PRODUCT_UPLOAD_W');
            $this->thumbMaxHeight = C('PRODUCT_UPLOAD_H');
            $info = $this->CUpload();
            $data['b_img'] = $info[0]['savepath'].$info[0]['savename'];
        }
        if($data['b_img']) $model->b_img=$data['b_img'];//企业直投展示图
        $result = $model->add();
        //
        $suo=array();
        $suo['id']=$result; 
        $suo['suo']=0;
        $suoid = M("transfer_borrow_info_lock")->add($suo);
        
        foreach($_POST['swfimglist'] as $key=>$v){
      
            $row[$key]['img'] = substr($v,1);
            $row[$key]['info'] = $_POST['picinfo'][$key];
        }
        $model2->borrow_img=serialize($row);
        $model2->borrow_id = $result;
        $result2 = $model2->add();
        if ($result && $result2) { //保存成功
            $model->commit();
            if(intval($_POST['is_auto'])==1 &&($model->progress==0)){
                //企业直投自动投标
                autotInvest($result);
            }
            alogs("Tborrow",$result,1,'成功执行了企业直投信息的添加操作！');//管理员操作日志
          //成功提示
            $this->assign('jumpUrl', __URL__);
            $this->success(L('新增成功'));
        }else{
            alogs("Tborrow",$result,0,'执行企业直投信息的添加操作失败！');//管理员操作日志
            $model->rollback();
            //失败提示
            $this->error(L('新增失败'));
        }
    }
    
     public function edit() {
        $model = M('transfer_borrow_info');
        $model2 = M('transfer_detail');
        $id = intval($_REQUEST['id']);
        $vo = $model->find($id);
        $vo['borrow_user'] =  M('members')->field('user_name')->find($vo['borrow_uid']);
        $vo2 = $model2->find($id);
        foreach($vo2 as $key=>$v){
            if($key=="borrow_img") $vo[$key] = unserialize($v);
            else $vo[$key] = $v;
        }
        $this->assign('vo', $vo);
        $this->display();
    }
    
    //添加数据
    public function doEdit() {
        $model = M("transfer_borrow_info");
        $model2 = M("transfer_detail");
        
        if (false === $model->create()) {
            $this->error($model->getError());
        }
        if (false === $model2->create()) {
            $this->error($model->getError());
        }
		$model->borrow_status == 3 && $model->is_show = 0;

        $model->startTrans();
        //保存当前数据对象
        $model->repayment_type = 5;
        $model->borrow_max = intval($_POST['borrow_max']);
        $model->borrow_fee = intval($_POST['borrow_fee']);
        foreach($_POST['updata_name'] as $key=>$v){
            $updata[$key]['name'] = $v;
            $updata[$key]['time'] = $_POST['updata_time'][$key];
        }
        $model->updata = serialize($updata);

        if(!empty($_FILES['imgfile']['name'])){
            $this->saveRule = date("YmdHis",time()).rand(0,1000);
            $this->savePathNew = C('ADMIN_UPLOAD_DIR').'Product/';
            $this->thumbMaxWidth = C('PRODUCT_UPLOAD_W');
            $this->thumbMaxHeight = C('PRODUCT_UPLOAD_H');
            $info = $this->CUpload();
            $data['b_img'] = $info[0]['savepath'].$info[0]['savename'];
        }
        if($data['b_img']) $model->b_img=$data['b_img'];//修改企业直投展示图
        $result = $model->save();
        foreach($_POST['swfimglist'] as $key=>$v){
            $row[$key]['img'] = substr($v,1);
            $row[$key]['info'] = $_POST['picinfo'][$key];
        }
        $model2->borrow_img=serialize($row);
        $model2->borrow_id = intval($_POST['id']);
        $result2 = $model2->save();
        //$this->assign("waitSecond",1000);
        if ($result || $result2) { //保存成功
            $model->commit();
            alogs("Tborrow",0,1,'成功执行了企业直投信息的修改操作！');//管理员操作日志
          //成功提示
            $this->assign('jumpUrl', __URL__);
            $this->success(L('修改成功'));
        } else {
            alogs("Tborrow",0,0,'执行企业直投信息的修改操作失败！');//管理员操作日志
            $model->rollback();
            //失败提示
            $this->error(L('修改失败'));
        }
    }
                
    protected function _AfterDoEdit(){
        switch(strtolower(session('listaction'))){
            case "waitverify":
                $v = M('transfer_borrow_info')->field('borrow_uid,borrow_status,deal_time')->find(intval($_POST['id']));
                if(!empty( $v['deal_time'])){
                    break;
                }
                if(empty($v['deal_time'])){
                    $newid = M('members')->where("id={$v['borrow_uid']}")->setInc('credit_use',floatval($_POST['borrow_money']));
                    if($newid) M('transfer_borrow_info')->where("id={$v['borrow_uid']}")->setField('deal_time',time());
                }
            break;
        }
    }

    public function _listFilter($list){
         $Bconfig = require C("APP_ROOT")."Conf/borrow_config.php";
         $listType = $Bconfig['REPAYMENT_TYPE'];
        $row=array();
        foreach($list as $key=>$v){
            $v['repayment_type'] = $listType[intval($v['repayment_type'])];
            $v['borrow_user'] =  M('members')->field('user_name')->find($v['borrow_uid']);
            $v['invest_num'] = M('transfer_borrow_investor')->where("borrow_id={$v['id']}")->count(id);//已投资纪录数量
            $row[$key]=$v;
        }
        return $row;
    }
            
    public function getusername(){
        $uname = M("members")->field("is_transfer,user_name")->find(intval($_POST['uid']));
        if($uname['user_name'] && $uname['is_transfer']==1) exit(json_encode(array("uname"=>"<span style='color:green'>".$uname['user_name']."</span>")));
        elseif($uname['user_name'] && $uname['is_transfer']==0) exit(json_encode(array("uname"=>"<span style='color:black'>此会员不是流转会员</span>")));
        elseif(!is_array($uname)) exit(json_encode(array("uname"=>"<span style='color:orange'>不存在此会员</span>")));
    }
            
        //swf上传图片
    public function swfUpload(){
        if($_POST['picpath']){
            $imgpath = substr($_POST['picpath'],1);
            if(in_array($imgpath,$_SESSION['imgfiles'])){
                     unlink(C("WEB_ROOT").$imgpath);
                     $thumb = get_thumb_pic($imgpath);
                $res = unlink(C("WEB_ROOT").$thumb);
                if($res) $this->success("删除成功","",$_POST['oid']);
                else $this->error("删除失败","",$_POST['oid']);
            }else{
                $this->error("图片不存在","",$_POST['oid']);
            }
        }else{
            $this->savePathNew = C('ADMIN_UPLOAD_DIR').'Product/' ;
            $this->thumbMaxWidth = C('PRODUCT_UPLOAD_W');
            $this->thumbMaxHeight = C('PRODUCT_UPLOAD_H');
            $this->saveRule = date("YmdHis",time()).rand(0,1000);
            $info = $this->CUpload();
            $data['product_thumb'] = $info[0]['savepath'].$info[0]['savename'];
            if(!isset($_SESSION['count_file'])) $_SESSION['count_file']=1;
            else $_SESSION['count_file']++;
            $_SESSION['imgfiles'][$_SESSION['count_file']] = $data['product_thumb'];
            echo "{$_SESSION['count_file']}:".__ROOT__."/".$data['product_thumb'];//返回给前台显示缩略图
        }
    }
    
    
    //每个借款标的投资人记录
     public function doinvest()
    {
        $borrow_id = intval($_REQUEST['borrow_id']);
        $map=array();
        $map['bi.borrow_id'] = $borrow_id;
		$map['bi.loanno'] = array('neq','');
        //分页处理
        import("ORG.Util.Page");
        $count = M('transfer_borrow_investor bi')->join("{$this->pre}members m ON m.id=bi.investor_uid")->where($map)->count('bi.id');
        $p = new Page($count, C('ADMIN_PAGE_SIZE'));
        $page = $p->show();
        $Lsql = "{$p->firstRow},{$p->listRows}";
        //分页处理

        $field= 'bi.id bid,b.id,bi.investor_capital,bi.investor_interest,bi.invest_fee,bi.add_time,m.user_name,m.id mid,m.user_phone,b.borrow_duration,b.repayment_type,m.customer_name,b.borrow_name,bi.transfer_month,b.is_auto';
        $list = M('transfer_borrow_investor bi')->field($field)->join("{$this->pre}members m ON m.id=bi.investor_uid")->join("{$this->pre}transfer_borrow_info b ON b.id=bi.borrow_id")->where($map)->limit($Lsql)->order("bi.id DESC")->select();
        $list = $this->_listFilter($list);
        
        //dump($list);exit;
        $this->assign("list", $list);
        $this->assign("pagebar", $page);
        $this->display();
    }
    //还款中列表
    public function repayment() {
        $map['b.is_show'] = 0 ;
        $map['b.borrow_status'] = array('in','2,3');
        
        // 分页处理
        import("ORG.Util.Page");
        $count = M('transfer_borrow_info b') -> join("{$this->pre}members m ON m.id=b.borrow_uid") -> where($map) -> count('b.id');
        $p = new Page($count, C('ADMIN_PAGE_SIZE'));
        $page = $p -> show();
        $Lsql = "{$p->firstRow},{$p->listRows}"; 
        // 分页处理
        $field= 'b.id,b.borrow_name,b.borrow_uid,b.borrow_duration,b.borrow_money,b.borrow_fee,b.borrow_interest_rate,b.repayment_type,b.transfer_out,b.transfer_total,b.add_time,m.user_name,b.level_can,b.borrow_max,progress,b.is_tuijian,b.is_auto';
        $list = M('transfer_borrow_info b') -> field($field) -> join("{$this->pre}members m ON m.id=b.borrow_uid") -> where($map) -> limit($Lsql) -> order("b.id DESC") -> select();
        $list = $this -> _listFilter($list);
        $this -> assign("list", $list);
        $this -> assign("pagebar", $page);
        $this -> assign("xaction", ACTION_NAME);
        $this -> display();
    }

}

?>
