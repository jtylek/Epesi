<?php
/**
 * CRM Phone Call Class
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage phonecall
 */

defined("_VALID_ACCESS") || die();

class CRM_PhoneCall extends Module {
	private $rb = null;

	public function body() {
		$this->help('Phone Call Help','main');

		$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'phonecall','phonecall');
		$me = CRM_ContactsCommon::get_my_record();
		CRM_CommonCommon::status_filter($this->rb);
		$this->rb->set_filters_defaults(array('employees'=>$this->rb->crm_perspective_default(), 'status'=>'__NO_CLOSED__'));
		$this->rb->set_defaults(array('date_and_time'=>date('Y-m-d H:i:s'), 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>CRM_CommonCommon::get_default_priority()));
		$this->rb->set_default_order(array('status'=>'ASC', 'date_and_time'=>'ASC', 'subject'=>'ASC'));
		$this->display_module($this->rb);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

	public function applet($conf, & $opts) {
		$opts['go'] = true;
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'phonecall','phonecall');
		$me = CRM_ContactsCommon::get_my_record();
		if ($me['id']==-1) {
			CRM_ContactsCommon::no_contact_message();
			return;
		}
		$crits = array('employees'=>array($me['id']), '!status'=>array(2,3));
		if (!isset($conf['past']) || !$conf['past'])
			$crits['>=date_and_time'] = date('Y-m-d 00:00:00');
		if (!isset($conf['today']) || !$conf['today']) {
			$crits['(>=date_and_time'] = date('Y-m-d 00:00:00', strtotime('+1 day'));
			$crits['|<date_and_time'] = date('Y-m-d 00:00:00');
		}
		if ($conf['future']!=-1)
			$crits['<=date_and_time'] = date('Y-m-d 23:59:59', strtotime('+'.$conf['future'].' day'));
		$conds = array(
									array(	array('field'=>'contact_name', 'width'=>14),
											array('field'=>'phone_number', 'width'=>20),
											array('field'=>'status', 'width'=>8)
										),
									$crits,
									array('status'=>'ASC','date_and_time'=>'ASC','priority'=>'DESC'),
									array('CRM_PhoneCallCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		$date = $this->get_module_variable('applet_date',date('Y-m-d H:i:s'));
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('phonecall',array('date_and_time'=>$date, 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>CRM_CommonCommon::get_default_priority()));
		$this->display_module($rb, $conds, 'mini_view');
	}

	public function messanger_addon($arg) {
		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('id'=>$arg['employees']), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
		foreach($ret as $c_id=>$data)
			if(is_numeric($data['login'])) {
				$emp[$data['login']] = CRM_ContactsCommon::contact_format_no_company($data);
			}
		$mes = $this->init_module('Utils/Messenger',array('CRM_PhoneCall:'.$arg['id'],array('CRM_PhoneCallCommon','get_alarm'),array($arg['id']),strtotime($arg['date_and_time']),$emp));
//		$mes->set_inline_display();
		$this->display_module($mes);
	}

    public function addon($r, $rb_parent) {
        $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'phonecall');
        $params = array(
            array(
                'related' => $rb_parent->tab . '/' . $r['id'],
            ),
            array(
                'related' => false,
            ),
            array(
                'date_and_time' => 'DESC'
            ),
        );

        //look for customers
        $customers = array();
        if(isset($r['customers'])) $customers = $r['customers'];
        elseif(isset($r['customer'])) $customers = $r['customer'];
        if(!is_array($customers)) $customers = array($customers);
        foreach($customers as $i=>&$customer) {
            if(preg_match('/^(C\:|company\/)([0-9]+)$/',$customer,$req)) {
                $customer = 'company/'.$req[2];
            } elseif(is_numeric($customer)) $customer = 'company/'.$customer;
            else unset($customers[$i]);
        }

        $me = CRM_ContactsCommon::get_my_record();
        $rb->set_defaults(array('related' => $rb_parent->tab . '/' . $r['id'],'employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>CRM_CommonCommon::get_default_priority(), 'date_and_time'=>date('Y-m-d H:i:s'),'customer'=>array_shift($customers)));
        $this->display_module($rb, $params, 'show_data');
    }

    public function admin() {
        if ($this->is_back()) {
            $this->parent->reset();
            return;
        }
        $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'phonecall_related', 'phonecall_related');
        $this->display_module($rb);
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
    }
}
?>
