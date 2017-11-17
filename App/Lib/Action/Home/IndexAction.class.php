<?php
// 本类由系统自动生成，仅供测试用途
class IndexAction extends HCommonAction {
    public function index(){
		
		$per = C('DB_PREFIX');
	    $Bconfig = require C("APP_ROOT")."Conf/borrow_config.php";
		//网站公告
		$parm['type_id'] = 9;
		$parm['limit'] =4;
		$this->assign("noticeList",getArticleList($parm));
    //网站公告
    
    //正在进行的贷款
    $searchMap = array();
    $searchMap['b.borrow_status']=array("in",'2,4,6,7');
    $searchMap['b.is_tuijian']=array("in",'0,1');
    $parm=array();
    $parm['map'] = $searchMap;
    $parm['limit'] = 6;
    $parm['orderby']="b.borrow_status ASC,b.id DESC";
    $listBorrow = getBorrowList($parm);
    $this->assign("listBorrow",$listBorrow);
    
    //正在进行的贷款
    
    ///////////////企业直投列表开始  fan 2013-10-21//////////////
    $parm = array();
    $searchMap = array();
    //$searchMap['borrow_status']=2;
    $searchMap['b.is_show'] = array('in','0,1');
	$searchMap['b.borrow_status'] = array('neq','3');
    $parm['map'] = $searchMap;
    $parm['limit'] = 3;
    $parm['orderby'] = "b.is_show desc,b.progress asc";
    $listTBorrow = getTBorrowList($parm);
    $this->assign("listTBorrow",$listTBorrow);
    ///////////////企业直投列表结束  fan 2013-10-21//////////////
    
    $this->display();
    
    }	
  }
	