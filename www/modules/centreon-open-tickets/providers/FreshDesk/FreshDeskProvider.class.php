<?php
/*
* Copyright 2018 YPSI SASU (http://www.ypsi.fr/)
*
* YPSI is a company focused on Audit, Performance and Supervision in
* all IT Domains.
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
*    http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,*
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*
* Author : <Yann PILPRE> <<yann.pilpre@ypsi.fr>>
*/

class FreshDeskProvider extends AbstractProvider {

  const FRESHDESK_LIST_PRIORITY = 7;
  protected $_close_advanced = 1;

  protected function _setDefaultValueMain($body_html = 0) {

    parent::_setDefaultValueMain($body_html);

    $this->default_data['url'] = 'https://{$freshdesk_domain}.freshdesk.com/a/tickets/{$ticket_id}';

    $this->default_data['clones']['groupList'] = array(
      array('Id' => 'freshdesk_priority', 'Label' => _('Priority'), 'Type' => self::FRESHDESK_LIST_PRIORITY, 'Filter' => '', 'Mandatory' => true),
    );
    $this->default_data['clones']['customList'] = array(
      array('Id' => 'freshdesk_priority', 'Value' => '1', 'Default' => ''),
      array('Id' => 'freshdesk_priority', 'Value' => '2', 'Default' => ''),
      array('Id' => 'freshdesk_priority', 'Value' => '3', 'Default' => ''),
      array('Id' => 'freshdesk_priority', 'Value' => '4', 'Default' => ''),
    );

  }


  protected function _setDefaultValueExtra() {


  }

  protected function _getConfigContainer1Extra(){

    $tpl = new Smarty();
    $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/FreshDesk/templates', $this->_centreon_path);

    $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
    $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
    $tpl->assign("header", array("mail" => _("FreshDesk")));
    $freshdesk_domain_html = '<input size="50" name="freshdesk_domain" type="text" value="' . $this->_getFormValue('freshdesk_domain') . '" />';
    $freshdesk_apikey_html = '<input size="50" name="freshdesk_apikey" type="text" value="' . $this->_getFormValue('freshdesk_apikey') . '" />';

    $array_form = array(
      'freshdesk_domain' => array('label' => _("FreshDesk Domain") . $this->_required_field, 'html' => $freshdesk_domain_html),
      'freshdesk_apikey' => array('label' => _("FreshDesk API Key") . $this->_required_field, 'html' => $freshdesk_apikey_html)

    );

    $tpl->assign('form', $array_form);

    $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
  }



  protected function _getConfigContainer2Extra(){}

    protected function _checkConfigForm(){
      $this->_check_error_message = '';
      $this->_check_error_message_append = '';

      $this->_checkFormValue('freshdesk_domain', 'Please set a Freshdesk domain.');
      $this->_checkFormValue('freshdesk_apikey', 'Please set a Freshdesk API Token.');

      $this->_checkLists();

      if ($this->_check_error_message != '') {
        throw new Exception($this->_check_error_message);
      }
    }

    protected function saveConfigExtra(){

      $this->_save_config['simple']['freshdesk_domain'] = $this->_submitted_config['freshdesk_domain'];
      $this->_save_config['simple']['freshdesk_apikey'] = $this->_submitted_config['freshdesk_apikey'];

    }

    public function validateFormatPopup() {
      $result = array('code' => 0, 'message' => 'ok');

      $this->validateFormatPopupLists($result);
      return $result;
    }

    /**
    * Create a ticket
    *
    * @param CentreonDB $db_storage The centreon_storage database connection
    * @param string $contact The contact who open the ticket
    * @param array $host_problems The list of host issues link to the ticket
    * @param array $service_problems The list of service issues link to the ticket
    * @param array $extra_ticket_arguments Extra arguments
    * @return array The status of action (
    *  'code' => int,
    *  'message' => string
    * )
    */
    protected function doSubmit($dbStorage, $contact, $hostProblems, $serviceProblems, $extra_ticket_arguments=array()) {
      $result = array('ticket_id' => null, 'ticket_error_message' => null,
      'ticket_is_ok' => 0, 'ticket_time' => time());
      // /* Build the short description */
      $title = '';
      for ($i = 0; $i < count($hostProblems); $i++) {
        if ($title !== '') {
          $title .= ' | ';
        }
        $title .= $hostProblems[$i]['name'];
      }
      for ($i = 0; $i < count($serviceProblems); $i++) {
        if ($title !== '') {
          $title .= ' | ';
        }
        $title .= $serviceProblems[$i]['host_name'] . ' - ' . $serviceProblems[$i]['description'];
      }
      /* Get default body */

      $tpl = new Smarty();
      $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);

      $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
      $tpl->assign('user', $contact);
      $tpl->assign('host_selected', $hostProblems);
      $tpl->assign('service_selected', $serviceProblems);
      $this->assignSubmittedValues($tpl);
      $tpl->assign('string', '{$body}');
      $body = $tpl->fetch('eval.ihtml');
      $tpl->assign('string', '{$user.email}');
      $from = $tpl->fetch('eval.ihtml');
      try {
        $data = $this->_submitted_config;
        $data['title'] = 'Incident on ' . $title;
        $data['body'] = $body;
        $data['from'] = $from;

        $resultInfo = $this->createTicketFreshDesk($data);
      } catch (\Exception $e) {
        $result['ticket_error_message'] = 'Error during create FreshDesk ticket';
      }
      $this->saveHistory(
        $dbStorage,
        $result,
        array(
          'contact' => $contact,
          'host_problems' => $hostProblems,
          'service_problems' => $serviceProblems,
          'ticket_value' => $resultInfo['ticketId'],
          'subject' => $title,
          'data_type' => self::DATA_TYPE_JSON,
          'data' => json_encode($data)
        )
      );
      return $result;
    }

    protected function createTicketFreshDesk($params) {
      $uri = '/tickets';

      $priority = explode('_', $params['select_freshdesk_priority'], 2);

      $data = array(
        'priority' => intval($priority[1]),
        'subject' => $params['title'],
        'status'=> 2,
        'email'=>$params['from'],
        'custom_fields'=> array("cf_domaine"=>'Supervision'),

      );
      if ($params['custom_message'] !== '') {
        $data['description'] = $params['body'];
      }
      $result = $this->runHttpRequest($uri, 'POST', $data);
      return array(
        'ticketId' => $result['id']
      );
    }

    protected function closeTicketFreshDesk($ticketid) {
      $uri = '/tickets/'.$ticketid;
      $data = array(
        'status'=>4
      );
      $result = $this->runHttpRequest($uri, 'PUT', $data);
      return 0;
    }

    protected function runHttpRequest($uri, $method = 'GET', $data = null) {
      $domain = $this->_getFormValue('');
      $url = 'https://' . $this->rule_data['freshdesk_domain'] . '.freshdesk.com/api/v2' . $uri;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch,CURLOPT_USERPWD,$this->rule_data['freshdesk_apikey'].":X");
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Content-Type: application/json'
      ));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      if ($method == 'PUT') {
        curl_setopt($ch,  CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!is_null($data)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
      }
      if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if (!is_null($data)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
      }

      $returnJson = curl_exec($ch);

      if ($returnJson === false) {
        throw new \Exception(curl_error($ch));
      }
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($status < 200 && $status >= 300) {
        throw new \Exception(curl_error($ch));}

        curl_close($ch);

        return json_decode($returnJson, true);
      }

      public function closeTicket(&$tickets) {
        if ($this->doCloseTicket()) {
          foreach ($tickets as $k => $v) {
            if ($this->closeTicketFreshDesk($k) == 0) {
              $tickets[$k]['status'] = 2;
            } else {
              $tickets[$k]['status'] = -1;
              $tickets[$k]['msg_error'] = $this->ws_error;
            }
          }
        } else {
          parent::closeTicket($tickets);
        }
      }

    }
