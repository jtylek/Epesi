<?php
/**
 * Core mail support - accounts, archive applet.
 * @author pbukowski@telaxus.com
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Mail extends Module {

	public function body() {

	}

	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}

		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$tb = $this->init_module(Utils_TabbedBrowser::module_name());
		$tb->set_tab(__('Global Signature'),array($this,'admin_signature'));
		$tb->set_tab(__('Related'),array($this,'admin_related'));
		$this->display_module($tb);
	}

	public function admin_related() {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(), 'rc_related', 'rc_related');
		$this->display_module($rb);
	}

	public function admin_signature() {
		$f = $this->init_module(Libs_QuickForm::module_name());

		$f->addElement('header',null,__('Outgoing mail global signature'));

		$fck = & $f->addElement('ckeditor', 'content', __('Content'));
		$fck->setFCKProps('800','300',true);

		$f->setDefaults(array('content'=>Variable::get('crm_mail_global_signature',false)));

		Base_ActionBarCommon::add('save',__('Save'),$f->get_submit_form_href());

		if($f->validate()) {
			$ret = $f->exportValues();
			$content = $ret['content'];
			Variable::set('crm_mail_global_signature',$content);
			Base_StatusBarCommon::message(__('Signature saved'));
			$this->parent->reset();
			return;
		}
		$f->display();
	}

	public function attachments_addon($arg,$rb) {
		$m = $this->init_module(Utils_GenericBrowser::module_name(),null,'attachments');
		$attachments = DB::GetAssoc('SELECT mime_id,name FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($arg['id']));
		$data = array();
		foreach($attachments as $k=>&$n) {
			$filename = DATA_DIR.'/CRM_Mail/attachments/'.$arg['id'].'/'.$k;
			$data[] = array('<a href="modules/CRM/Mail/get.php?'.http_build_query(array('mime_id'=>$k,'mail_id'=>$arg['id'])).'" target="_blank">'.$n.'</a>',file_exists($filename)?filesize($filename):'---');
		}
		$this->display_module($m,array(array(array('name'=>'Filename','search'=>1),
							array('name'=>'Size')),$data,false,null,array('Filename'=>'ASC')),'simple_table');
	}

	public function addon($arg, $rb) {
		$rs = $rb->tab;
		$id = $arg['id'];
		if($rs=='contact' || $rs=='company') {
			$emails = CRM_MailCommon::get_email_addresses($rs,$arg);
			if($emails) Base_ActionBarCommon::add('reload', __('Reload mails'), $this->create_callback_href(array('CRM_MailCommon', 'reload_mails'), array($rs, $id, $emails)));
		}
		if(isset($_SESSION['rc_mails_cp']) && is_array($_SESSION['rc_mails_cp']) && !empty($_SESSION['rc_mails_cp'])) {
			$ok = true;
			$mails = Utils_RecordBrowserCommon::get_records('rc_mails',array('id'=>$_SESSION['rc_mails_cp']),array('related','employee','contacts'));
			if(count($mails)!=count($_SESSION['rc_mails_cp'])) $ok=false;
			if($ok) foreach($mails as $mail) {
				if(in_array($rs.'/'.$id,$mail['related']) || in_array($rs.'/'.$id,$mail['contacts']) || ($rs=='contact' && $id==$mail['employee'])) {
			$ok = false;
			break;
		}
			}
			if($ok) {
			$this->lp = $this->init_module('Utils_LeightboxPrompt');
			$this->lp->add_option('cancel', __('Cancel'), Base_ThemeCommon::get_template_file('Base_ActionBar', 'icons/back.png'), null);
			$this->lp->add_option('paste', __('Paste'), Base_ThemeCommon::get_template_file($this->get_type(), 'copy.png'), null);
			$content = '';
			foreach($_SESSION['rc_mails_cp'] as $mid) {
				$mail = Utils_RecordBrowserCommon::get_record('rc_mails',$mid);
				$content .= '<div style="text-align:left"><b>'.__('From').':</b> <i>'.$mail['from'].'</i><br /><b>'.__('To').':</b> <i>'.$mail['to'].'</i><br /><b>'.__('Subject').':</b> <i>'.$mail['subject'].'</i><br />'.substr(strip_tags($mail['body'],'<br><hr>'),0,200).(strlen($mail['body'])>200?'...':'').'</div>';
			}
			$this->display_module($this->lp, array(__('Paste e-mail'), array(), $content, false));
			$vals = $this->lp->export_values();
			if ($vals) {
				if($vals['option']=='paste')
					$this->paste($rs,$id);
			}
			Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file($this->get_type(),'copy.png'),__('Paste mail'), $this->lp->get_href());//$this->create_confirm_callback_href(__('Paste following email?'),array($this,'paste'),array($rs,$id)));
			}
		}

		$tb = $this->init_module(Utils_TabbedBrowser::module_name());
		$tb->set_tab(__('Threaded'),array($this,'addon_threaded'),array($rs,$id));
		$tb->set_tab(__('Flat'),array($this,'addon_flat'),array($rs,$id));
		$this->display_module($tb);
	}

	public function addon_threaded($rs,$id) {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'rc_mail_threads','rc_mails_threaded');
		$rb->set_header_properties(array(
						'date'=>array('width'=>10),
						'contacts'=>array('name'=>__('Involved contacts'), 'width'=>20),
						'subject'=>array('name'=>__('Message'),'width'=>40),
						'attachments'=>array('width'=>5),
						'count'=>array('width'=>5)
		));

		//set order by threads:
		//1 - if there is reference sort by parent message date, else sort by this message date ("group" messages by "parent" date)
		//2 - if there is reference sort by parent message id, else sort by "my" message_id
/*          $rb->force_order(array(':CASE WHEN f_references is null OR (SELECT rx.f_date FROM rc_mails_data_1 rx WHERE rx.active=1 AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' LIMIT 1) is null THEN (SELECT rx.f_date FROM rc_mails_data_1 rx WHERE rx.active=1 AND rx.f_references LIKE '.DB::Concat('\'%\'','r.f_message_id','\'%\'').' ORDER BY rx.f_date DESC LIMIT 1) ELSE (SELECT rx2.f_date FROM rc_mails_data_1 rx2 WHERE rx2.active=1 AND rx2.f_references LIKE '.DB::Concat('\'%\'','(SELECT rx.f_message_id FROM rc_mails_data_1 rx WHERE rx.active=1 AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' ORDER BY rx.f_date ASC LIMIT 1)','\'%\'').' ORDER BY rx2.f_date DESC LIMIT 1) END'=>'DESC',
					':CASE WHEN f_references is null THEN f_message_id ELSE (SELECT rx.f_message_id FROM rc_mails_data_1 rx WHERE rx.f_references is null AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' ORDER BY rx.f_date ASC LIMIT 1) END'=>'DESC',
					':CASE WHEN f_references is null OR (SELECT rx.f_date FROM rc_mails_data_1 rx WHERE rx.active=1 AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' LIMIT 1) is null THEN 0 ELSE 1 END'=>'ASC',
					'date'=>'DESC'
		));*/

		$thread_query = Utils_RecordBrowserCommon::build_query('rc_mails', array('related' => $rs.'/'.$id));
		$assoc_threads_ids = empty($thread_query) ? array() : DB::GetCol('SELECT f_thread FROM '.$thread_query['sql'], $thread_query['vals']);
		if($rs=='contact') {
			//$ids = DB::GetCol('SELECT id FROM rc_mails_data_1 WHERE f_employee=%d OR (f_recordset=%s AND f_object=%d)',array($id,$rs,$id));
			$this->display_module($rb, array(array('(contacts'=>array('contact/'.$id),'|id'=>$assoc_threads_ids), array(), array('last_date'=>'DESC')), 'show_data');
		} elseif($rs=='company') {
			$form = $this->init_module(Libs_QuickForm::module_name());
			$form->addElement('checkbox', 'include_related', __('Include related e-mails'), null, array('onchange'=>$form->get_submit_form_js()));
			if ($form->validate()) {
				$show_related = $form->exportValue('include_related');
				$this->set_module_variable('include_related',$show_related);
			}
			$show_related = $this->get_module_variable('include_related');
			$form->setDefaults(array('include_related'=>$show_related));

			ob_start();
			$form->display_as_row();
			$html = ob_get_clean();

			$rb->set_button(false, $html);
			$customers = array('company/'.$id);
			if ($show_related) {
				$conts = CRM_ContactsCommon::get_contacts(array('company_name'=>$id));
				foreach ($conts as $c)
					$customers[] = 'contact/'.$c['id'];
			}
			$this->display_module($rb, array(array('(contacts'=>$customers,'|id'=>$assoc_threads_ids), array(), array('last_date'=>'DESC')), 'show_data');
		} else
		$this->display_module($rb, array(array('id'=>$assoc_threads_ids), array(), array('last_date'=>'DESC')), 'show_data');

	}

	public function addon_flat($rs,$id) {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'rc_mails','rc_mails_flat');
		$rb->set_header_properties(array(
			'date'=>array('width'=>10),
			'employee'=>array('name'=>__('Archived by'),'width'=>20),
			'contacts'=>array('name'=>__('Involved contacts'), 'width'=>20),
			'subject'=>array('name'=>__('Message'),'width'=>40),
			'attachments'=>array('width'=>5)
		));
		$rb->set_additional_actions_method(array($this, 'actions_for_mails'));

		if($rs=='contact') {
			$this->display_module($rb, array(array('(employee'=>$id,'|contacts'=>array('contact/'.$id),'|related'=>$rs.'/'.$id), array(), array('date'=>'DESC')), 'show_data');
		} elseif($rs=='company') {
			$form = $this->init_module(Libs_QuickForm::module_name());
			$form->addElement('checkbox', 'include_related', __('Include related e-mails'), null, array('onchange'=>$form->get_submit_form_js()));
			if ($form->validate()) {
				$show_related = $form->exportValue('include_related');
				$this->set_module_variable('include_related',$show_related);
			}
			$show_related = $this->get_module_variable('include_related');
			$form->setDefaults(array('include_related'=>$show_related));

			ob_start();
			$form->display_as_row();
			$html = ob_get_clean();

			$rb->set_button(false, $html);
			$customers = array('company/'.$id);
			if ($show_related) {
				$conts = CRM_ContactsCommon::get_contacts(array('company_name'=>$id));
				foreach ($conts as $c)
					$customers[] = 'contact/'.$c['id'];
			}
			$this->display_module($rb, array(array('(contacts'=>$customers,'|related'=>$rs.'/'.$id), array(), array('date'=>'DESC')), 'show_data');
		} else
			$this->display_module($rb, array(array('related'=>$rs.'/'.$id), array(), array('date'=>'DESC')), 'show_data');
	}

	public function thread_addon($arg,$rb) {
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'rc_mails','rc_mails_flat_thread');
		$rb->set_header_properties(array(
			'date'=>array('width'=>10),
			'employee'=>array('name'=>__('Archived by'),'width'=>20),
			'contacts'=>array('name'=>__('Involved contacts'), 'width'=>20),
			'subject'=>array('name'=>__('Message'),'width'=>40),
			'attachments'=>array('width'=>5)
		));
		$rb->set_additional_actions_method(array($this, 'actions_for_mails'));

		$this->display_module($rb, array(array('thread'=>$arg['id']), array(), array('date'=>'DESC')), 'show_data');
	}

	public function paste($rs,$id) {
		if(isset($_SESSION['rc_mails_cp']) && is_array($_SESSION['rc_mails_cp']) && !empty($_SESSION['rc_mails_cp'])) {
			foreach($_SESSION['rc_mails_cp'] as $mid) {
				$mail = Utils_RecordBrowserCommon::get_record('rc_mails',$mid);
				if(!in_array($rs.'/'.$id,$mail['related'])) {
					$mail['related'][] = $rs.'/'.$id;
					Utils_RecordBrowserCommon::update_record('rc_mails',$mid,array('related'=>$mail['related']));
				}
			}
			location(array());
		}
	}

	public function actions_for_mails($r, $gb_row) {
		$gb_row->add_action($this->create_callback_href(array($this,'copy'),array($r['id'])),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'));
		$gb_row->add_action('style="display:none;" href="javascript:void(0)" class="expand"','Expand', null, Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'expand.gif'), 5);
		$gb_row->add_action('style="display:none;" href="javascript:void(0)" class="collapse"','Collapse', null, Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'collapse.gif'), 5);
	}

	public function copy($id) {
		$_SESSION['rc_mails_cp'] = array($id);
	}

	public function open_mail_client($id) {
		$mail_client = Variable::get('crm_mail_default_client','');
		if(!$mail_client) return;
		Base_BoxCommon::push_module($mail_client,'body',array(array(),$id));
	}

	public function applet($conf, & $opts) {
		Epesi::load_js('modules/CRM/Mail/utils.js');
		$opts['go'] = true;
		$accounts = array();
		$ret = array();
		$update_applet = '';
		foreach($conf as $key=>$on) {
			$x = explode('_',$key);
			if($x[0]=='account' && $on) {
				$id = $x[1];
				$accounts[] = $id;
			}
		}
		$accs = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user(),'id'=>$accounts));
		print('<ul>');
		foreach($accs as $row) {
			$mail = $row['account_name'];

			$cell_id = 'mailaccount_'.$opts['id'].'_'.$row['id'];

			//interval execution
			eval_js_once('setInterval(\'CRM_Mail.update_msg_num('.$opts['id'].' ,'.$row['id'].' , 0)\',200000)');

			//and now
			$update_applet .= 'CRM_Mail.update_msg_num('.$opts['id'].' ,'.$row['id'].' ,1);';
			print('<li><i><a'.$this->create_callback_href(array($this,'open_mail_client'),$row['id']).'>'.$mail.'</a></i> - <span id="'.$cell_id.'"></span></li>');
		}
		print('</ul>');
		$this->js($update_applet);

		$href = $this->create_callback_href(array('Base_BoxCommon', 'push_module'), array($this->get_type(), 'account_manager', array(true)));
		$img = '<img src="' . Base_ThemeCommon::get_template_file('Base_Dashboard', 'configure.png') . '" border="0">';
		$tooltip = Utils_TooltipCommon::open_tag_attrs(__('Go to account settings'));
		$opts['actions'][] = "<a $tooltip $href>$img</a>";
	}

	public function mail_addresses_addon($arg,$rb) {
		$type = $rb->tab;
		$loc = Base_RegionalSettingsCommon::get_default_location();
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'rc_multiple_emails');
		$order = array(array('record_type'=>$type,'record_id'=>$arg['id']), array('record_type'=>false,'record_id'=>false), array());
		$rb->set_defaults(array('record_type'=>$type,'record_id'=>$arg['id']));
		$rb->enable_quick_new_records();
		$this->display_module($rb,$order,'show_data');
	}

	////////////////////////////////////////////////////////////
	//account management
	public function account_manager($pushed_on_top = false) {
		if ($pushed_on_top) {
			if ($this->is_back()) {
				return Base_BoxCommon::pop_main();
			}
			Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		} else {
			Base_ActionBarCommon::add('back',__('Back'),$this->create_main_href('Base_User_Settings'));
		}

		$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'rc_accounts','rc_accounts');
		$this->rb->set_defaults(array('epesi_user'=>Acl::get_user()));
		$order = array(array('login'=>'DESC'), array('epesi_user'=>Acl::get_user()),array('epesi_user'=>false));
		$this->display_module($this->rb,$order);
	}

	public function mail_body_addon($rec) {
		$theme = $this->init_module('Base_Theme');
		$rec['body'] = '<iframe id="rc_mail_body" src="modules/CRM/Mail/get_html.php?'.http_build_query(array('id'=>$rec['id'])).'" style="width:100%;border:0" border="0"></iframe>';
		$theme->assign('email', $rec);
		$theme->display('mail_body');
	}

	public function mail_headers_addon($rec) {
		$theme = $this->init_module('Base_Theme');
		$rec['headers_data'] = '<iframe id="rc_mail_body" src="modules/CRM/Mail/get_html.php?'.http_build_query(array('id'=>$rec['id'], 'field'=>'headers')).'" style="width:100%;border:0" border="0"></iframe>';
		$theme->assign('email', $rec);
		$theme->display('mail_headers');
	}

}

?>
