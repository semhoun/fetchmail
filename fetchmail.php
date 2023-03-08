<?php
/*******************************************************************************
 * Fetchmail Roundcube Plugin (Roundcube version 1.0-beta and above)
 * This software distributed under the terms of the GNU General Public License
 * as published by the Free Software Foundation
 * Further details on the GPL license can be found at
 * http://www.gnu.org/licenses/gpl.html
 * By contributing authors release their contributed work under this license
 * For more information see README.md file
 ******************************************************************************/

class fetchmail extends rcube_plugin
{
    public $task = 'settings';
    private $rc;
    private $db;
    private $show_folder;
    public function init()
    {
        $this->rc = rcube::get_instance();

        $this->load_config('config.inc.php.dist');
        $this->load_config();
        $this->add_texts('localization/', true);
        $this->include_stylesheet($this->local_skin_path() .'/fetchmail.css');
        $this->show_folder = $this->rc->config->get('fetchmail_folder');

        $this->add_hook('settings_actions', [$this, 'settings_actions']);

        $this->register_action('plugin.fetchmail', [$this, 'init_html']);
        $this->register_action('plugin.fetchmail.edit', [$this, 'init_html']);
        $this->register_action('plugin.fetchmail.save', [$this, 'save']);
        $this->register_action('plugin.fetchmail.delete', [$this, 'delete']);
    }

    public function init_html()
    {
        $this->rc->output->set_pagetitle($this->gettext('fetchmail'));
        $this->include_script('fetchmail.js');

        if ($this->rc->action == 'plugin.fetchmail.edit') {
        	$this->rc->output->add_handler('fetchmailform', [$this, 'gen_form']);
            $this->rc->output->send('fetchmail.fetchmailedit');
        } else {
            $this->rc->output->add_handler('sectionslist', [$this, 'section_list']);
            $this->rc->output->add_handler('fetchmail.quota', [$this, 'quota']);
            $this->rc->output->send('fetchmail.fetchmail');
        }
    }

    public function delete()
    {
        $this->_db_connect('w');
        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_POST);

        if ($id != 0 || $id != '')
        {
            $sql = "DELETE FROM fetchmail WHERE id = '$id'";
            $delete = $this->db->query($sql);
            rcmail::get_instance()->output->command('plugin.fetchmail.delete.callback', array(
		        'result' => 'done',
		        'id' => $id
		    ));
        }
    }

    public function quota($attrib)
    {
        $this->_db_connect('w');
        $mailbox = $this->rc->user->data['username'];
        $sql = "SELECT * FROM fetchmail WHERE mailbox='" . $mailbox . "'";
        $result = $this->db->query($sql);
        $limit = $this->rc->config->get('fetchmail_limit');
        $num_rows = $this->db->num_rows($result);
        $percent = ($num_rows / $limit) * 100;
        $out = "<div id=\"".$attrib['id']."\" class=\"quota-widget\"><span class=\"count\">".$num_rows."/".$limit."</span><span class=\"bar\"><span class=\"value\" style=\"width:$percent%\"></span></span></div>";

        $this->rc->output->add_gui_object('fetchmail.quota', $attrib['id']);
        $this->rc->output->set_env('fetchmail_limit', $limit);
        return $out;
    }

    public function save()
    {
        $this->_db_connect('w');
        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_POST);
        $mailbox = $this->rc->user->data['username'];
        $explode_mailbox = explode('@', $mailbox);
        $mailbox_user = $explode_mailbox[0];
        $mailbox_domain = $explode_mailbox[1];
        $protocol = rcube_utils::get_input_value('_fetchmailprotocol', rcube_utils::INPUT_POST);
        $protocol = strtoupper($protocol); // TODO: temporary
        $server = rcube_utils::get_input_value('_fetchmailserver', rcube_utils::INPUT_POST);
        $user = rcube_utils::get_input_value('_fetchmailuser', rcube_utils::INPUT_POST);
        $pass = base64_encode(rcube_utils::get_input_value('_fetchmailpass', rcube_utils::INPUT_POST));
        $folder = rcube_utils::get_input_value('_fetchmailfolder', rcube_utils::INPUT_POST);
        $pollinterval = rcube_utils::get_input_value('_fetchmailpollinterval', rcube_utils::INPUT_POST);
        $keep = rcube_utils::get_input_value('_fetchmailkeep', rcube_utils::INPUT_POST);
        $usessl = rcube_utils::get_input_value('_fetchmailusessl', rcube_utils::INPUT_POST);
        $fetchall = rcube_utils::get_input_value('_fetchmailfetchall', rcube_utils::INPUT_POST);
        $enabled = rcube_utils::get_input_value('_fetchmailenabled', rcube_utils::INPUT_POST);
        $newentry = rcube_utils::get_input_value('_fetchmailnewentry', rcube_utils::INPUT_POST);
        if (!$keep)
        {
            $keep = 0;
        }
        else
        {
            $keep = 1;
        }
        if (!$enabled)
        {
            $enabled = 0;
        }
        else
        {
            $enabled = 1;
        }
        if (!$usessl)
        {
            $usessl = 0;
        }
        else
        {
            $usessl = 1;
        }
        if (!$fetchall)
        {
            $fetchall = 0;
        }
        else
        {
            $fetchall = 1;
        }
        $mda = $this->rc->config->get('fetchmail_mda');

        //Validate server
        $server_valid = $this->_check_server($server);
        if(!$server_valid) {
        	rcmail::get_instance()->output->command('plugin.fetchmail.save.callback', array(
	            'result' => 'dnserror',
	            'message' => $this->gettext(['name' => 'invaliddomain', 'vars' => ['s' => $server]])
	        ));
	        return;
        }
        if ($newentry or $id == '')
        {
            $sql = "SELECT * FROM fetchmail WHERE mailbox='" . $mailbox . "'";
            $result = $this->db->query($sql);
            $limit = $this->rc->config->get('fetchmail_limit');
            $num_rows = $this->db->num_rows($result);
            if ($num_rows < $limit)
            {
                if($mda != '')
                {
                    $sql = "INSERT INTO fetchmail (mailbox, domain, active, src_server, src_user, src_password, src_folder, poll_time, fetchall, keep, protocol, usessl, sslcertck, src_auth, mda) VALUES ('$mailbox', '$mailbox_domain', '$enabled', '$server', '$user', '$pass', '$folder', '$pollinterval', '$fetchall', '$keep', '$protocol', '$usessl', true, 'password', '$mda' )";
                } else {
                    $sql = "INSERT INTO fetchmail (mailbox, domain, active, src_server, src_user, src_password, src_folder, poll_time, fetchall, keep, protocol, usessl, sslcertck, src_auth) VALUES ('$mailbox', '$mailbox_domain', '$enabled', '$server', '$user', '$pass', '$folder', '$pollinterval', '$fetchall', '$keep', '$protocol', '$usessl', true, 'password')";
                }
                $insert = $this->db->query($sql);
                if ($err_str = $this->db->is_error())
				{
					rcmail::get_instance()->output->command('plugin.fetchmail.save.callback', array(
						'result' => 'dberror',
						'message' => $this->gettext(['name' => 'servererrormsg', 'vars' => ['msg' => $err_str]])
					));
				} else {
		            $new_id = $this->db->insert_id();
		            rcmail::get_instance()->output->command('plugin.fetchmail.save.callback', array(
				        'result' => 'done',
				        'id' => $new_id,
				        'title' => $server.": ".$user
				    ));
				}
            }
            else
            {
                $this->rc->output->command('display_message', 'Error: ' . $this->gettext('fetchmaillimitreached') , 'error');
            }
        }
        else
        {
        	if($mda != '')
            {
                $sql = "UPDATE fetchmail SET mailbox = '$mailbox', domain = '$mailbox_domain', active = '$enabled', keep = '$keep', protocol = '$protocol', src_server = '$server', src_user = '$user', src_password = '$pass', src_folder = '$folder', poll_time = '$pollinterval', fetchall = '$fetchall', usessl = '$usessl', src_auth = 'password', mda = '$mda' WHERE id = '$id'";
            } else {
                $sql = "UPDATE fetchmail SET mailbox = '$mailbox', domain = '$mailbox_domain', active = '$enabled', keep = '$keep', protocol = '$protocol', src_server = '$server', src_user = '$user', src_password = '$pass', src_folder = '$folder', poll_time = '$pollinterval', fetchall = '$fetchall', usessl = '$usessl', src_auth = 'password' WHERE id = '$id'";
            }
            $update = $this->db->query($sql);
            if ($err_str = $this->db->is_error())
			{
				rcmail::get_instance()->output->command('plugin.fetchmail.save.callback', array(
					'result' => 'dberror',
					'message' => $this->gettext(['name' => 'servererrormsg', 'vars' => ['msg' => $err_str]])
				));
			} else {
		        rcmail::get_instance()->output->command('plugin.fetchmail.save.callback', array(
			        'result' => 'done',
			        'id' => $id,
			        'title' => $server.": ".$user
			    ));
		    }
        }
    }

    public function gen_form($attrib)
    {
        $this->_db_connect('r');
        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
        $mailbox = $this->rc->user->data['username'];

        // reasonable(?) defaults
        $pollinterval = '10';
        $usessl = 1;
        $fetchall = 0;
        $keep = 1;
        $enabled = 1;
        $protocol = 'IMAP';

        // auslesen start
        if ($id != '' || $id != 0)
        {
            $sql = "SELECT * FROM fetchmail WHERE mailbox='" . $mailbox . "' AND id='" . $id . "'";
            $result = $this->db->query($sql);
            while ($row = $this->db->fetch_assoc($result))
            {
                $enabled = $row['active'];
                $keep = $row['keep'];
                $mailget_id = $row['id'];
                $protocol = $row['protocol'];
                $server = $row['src_server'];
                $user = $row['src_user'];
                $pass = base64_decode($row['src_password']);
                $folder = $row['src_folder'];
                $pollinterval = $row['poll_time'];
                $fetchall = $row['fetchall'];
                $usessl = $row['usessl'];
            }
        }
        $newentry = 0;
        $out .= '<form id="fetchmailform" class="needs-validation" novalidate>'."\n";
        $out .= '<fieldset class="my-2"><legend>' . $this->gettext('fetchmail_to') . ' ' . $mailbox . '</legend>' . "\n";

        $table = new html_table(['cols' => 2, 'class' => 'propform cols-sm-6-6']);
        $hidden_id = new html_hiddenfield(array(
            'name' => '_id',
            'value' => $mailget_id
        ));
        $out .= $hidden_id->show();

        $field_id = 'fetchmailprotocol';
        $input_fetchmailprotocol = new html_select(array(
            'name' => '_fetchmailprotocol',
            'id' => $field_id,
            'onchange' => 'fetchmail_toggle_folder();'
        ));
        $input_fetchmailprotocol->add(array(
            'IMAP',
            'POP3'
        ) , array(
            'IMAP',
            'POP3'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailprotocol')));
        $table->add(null, $input_fetchmailprotocol->show($protocol));

        $field_id = 'fetchmailserver';
        $input_fetchmailserver = new html_inputfield(array(
            'name' => '_fetchmailserver',
            'id' => $field_id,
            'maxlength' => 320,
            'size' => 40,
            'required' => 'required'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailserver')));
        $table->add(null, $input_fetchmailserver->show($server));

        $field_id = 'fetchmailuser';
        $input_fetchmailuser = new html_inputfield(array(
            'name' => '_fetchmailuser',
            'id' => $field_id,
            'maxlength' => 320,
            'size' => 40,
            'required' => 'required'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('username')));
        $table->add(null, $input_fetchmailuser->show($user));

        $field_id = 'fetchmailpass';
        $input_fetchmailpass = new html_passwordfield(array(
            'name' => '_fetchmailpass',
            'id' => $field_id,
            'maxlength' => 320,
            'size' => 40,
            'autocomplete' => 'off',
            'required' => 'required'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('password')));
        $table->add(null, $input_fetchmailpass->show($pass));

        if ($this->show_folder)
        {
            $field_id = 'fetchmailfolder';
            $input_fetchmailfolder = new html_inputfield(array(
                'name' => '_fetchmailfolder',
                'id' => $field_id,
                'maxlength' => 320,
                'size' => 40
            ));
            if($protocol != "IMAP") {
                $table->set_row_attribs(array(
                    'style' => 'display:none;',
                    'id' => 'fetchmail_folder_display'
                ));
            } else {
                $table->set_row_attribs(array(
                    'id' => 'fetchmail_folder_display'
                ));
            }
            $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailfolder')));
            $table->add(null, $input_fetchmailfolder->show($folder));
        }

        $field_id = 'fetchmailpollinterval';
        $input_fetchmailpollinterval = new html_select(array(
            'name' => '_fetchmailpollinterval',
            'id' => $field_id
        ));
        $input_fetchmailpollinterval->add(array(
            '5',
            '10',
            '15',
            '20',
            '25',
            '30',
            '60'
        ) , array(
            '5',
            '10',
            '15',
            '20',
            '25',
            '30',
            '60'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailpollinterval')));
        $table->add(null, $input_fetchmailpollinterval->show("$pollinterval"));

        $field_id = 'fetchmailkeep';
        $input_fetchmailkeep = new html_checkbox(array(
            'name' => '_fetchmailkeep',
            'id' => $field_id,
            'value' => '1'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailkeep')));
        $table->add(null, $input_fetchmailkeep->show($keep));

        $field_id = 'fetchmailfetchall';
        $input_fetchmailfetchall = new html_checkbox(array(
            'name' => '_fetchmailfetchall',
            'id' => $field_id,
            'value' => '1'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailfetchall')));
        $table->add(null, $input_fetchmailfetchall->show($fetchall));

        $field_id = 'fetchmailusessl';
        $input_fetchmailusessl = new html_checkbox(array(
            'name' => '_fetchmailusessl',
            'id' => $field_id,
            'value' => '1'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailusessl')));
        $table->add(null, $input_fetchmailusessl->show($usessl));

        $field_id = 'fetchmailenabled';
        $input_fetchmailenabled = new html_checkbox(array(
            'name' => '_fetchmailenabled',
            'id' => $field_id,
            'value' => '1'
        ));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailenabled')));
        $table->add(null, $input_fetchmailenabled->show($enabled));

        if ($id != '' || $id != 0)
        {
            $field_id = 'fetchmailnewentry';
            $input_fetchmailnewentry = new html_checkbox(array(
                'name' => '_fetchmailnewentry',
                'id' => $field_id,
                'value' => '1'
            ));
            $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('fetchmailnewentry')));
            $table->add(null, $input_fetchmailnewentry->show($newentry));
        }

        $out .= $table->show();
        $out .= "</fieldset>\n";
        $out .= "</form>\n";
        $this->rc->output->add_gui_object('fetchmailform', 'fetchmail-form');
        return $out;
    }

    public function section_list($attrib)
    {
    	// add id to result list table if not specified
        if (!strlen($attrib['id'])) {
            $attrib['id'] = 'rcmsectionslist';
        }

        //Fetch existing entries from Database
    	$this->_db_connect('r');
        $mailbox = $this->rc->user->data['username'];
        $sql = "SELECT id, CONCAT(src_server, ': ', src_user) as title FROM fetchmail WHERE mailbox='$mailbox'";
        $result = $this->db->query($sql);

        $accounts = $result->fetchAll();
        $sections = array();
        foreach($accounts as $account) {
            $sections[$account['id']] = array(
                'id' => $account['id'],
                'class' => 'fetchmail-account',
                'section' => $account['title']
            );
        }

        // create HTML table
        $out = $this->rc->table_output($attrib, $sections, array('section'), 'id');

        // set client env
        $this->rc->output->add_gui_object('sectionslist', $attrib['id']);
        $this->rc->output->include_script('list.js');

        return $out;
    }

    public function settings_actions($args)
    {
        $args['actions'][] = array(
            'action' => 'plugin.fetchmail',
            'class' => 'fetchmail',
            'label' => 'fetchmail.fetchmail',
            'title' => 'fetchmail.fetchmail'
        );
        return $args;
    }

    private function _db_connect($mode)
    {
    	$db_dsn = $this->rc->config->get('fetchmail_db_dsn');
    	if(!$db_dsn) {
    	    $this->db = $this->rc->db;
    	}

        if (!$this->db)
        {
            $this->db = rcube_db::factory($db_dsn, '', false);
        }

        $this->db->db_connect($mode);

        // check DB connections and exit on failure
        if ($err_str = $this->db->is_error())
        {
            rcube::raise_error(['code' => 603, 'type' => 'db', 'message' => $err_str], false, true);
        }
    }

    private function _check_server($server)
    {
    	if($this->rc->config->get('fetchmail_check_server') == false) {
            return true;
    	}

    	if(!is_string($server))
    	{
    		return false;
    	}

    	$result = dns_get_record($server, DNS_A+DNS_AAAA);
    	if(is_array($result))
    	{
    		return (sizeof($result) > 0);
    	}

    	return false;
    }
}
