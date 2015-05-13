<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Wordmailmerge_Form_WordMailMergeForm extends CRM_Contact_Form_Task {
  
  static protected $_searchFormValues;
  function preProcess() {
    $token          = CRM_Core_SelectValues::contactTokens();
    $tokens         = CRM_Utils_Token::formatTokensForDisplay($token);
    $firstTokenmrg  = array();
    $tokenMerge     = array();
    $firstTokenmrg  = array_merge($tokens[0]['children'], $tokens[1]['children']);
    $tokenMerge     = array_merge($firstTokenmrg, $tokens[2]['children']);
    foreach ($tokenMerge as $tmKey => $tmValue) {
      $tokenMerge[$tmKey]['token_name'] =  str_replace(array('{contact.','}'),"",$tmValue['id']);
    }
    $this->assign('availableTokens', $tokenMerge);
    self::preProcessCommon($this);
  }

  /**
   * @param $form
   * @param bool $useTable
   */
  static function preProcessCommon(&$form, $useTable = FALSE) {
    $form->_contactIds = array();
    $form->_contactTypes = array();
    // get the submitted values of the search form
    // we'll need to get fv from either search or adv search in the future
    $fragment = 'search';
    if ($form->_action == CRM_Core_Action::ADVANCED) {
      self::$_searchFormValues = $form->controller->exportValues('Advanced');
      $fragment .= '/advanced';
    }
    elseif ($form->_action == CRM_Core_Action::PROFILE) {
      self::$_searchFormValues = $form->controller->exportValues('Builder');
      $fragment .= '/builder';
    }
    elseif ($form->_action == CRM_Core_Action::COPY) {
      self::$_searchFormValues = $form->controller->exportValues('Custom');
      $fragment .= '/custom';
    }
    else {
      self::$_searchFormValues = $form->controller->exportValues('Basic');
    }

    //set the user context for redirection of task actions
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $form);
    $urlParams = 'force=1';
    if (CRM_Utils_Rule::qfKey($qfKey)) {
      $urlParams .= "&qfKey=$qfKey";
    }
    $cacheKey = "civicrm search {$qfKey}";
    $url = CRM_Utils_System::url('civicrm/contact/' . $fragment, $urlParams);
    $session = CRM_Core_Session::singleton();
    $session->replaceUserContext($url);
    $form->_task = CRM_Utils_Array::value('task', self::$_searchFormValues);
    $crmContactTaskTasks = CRM_Contact_Task::taskTitles();
    $form->assign('taskName', CRM_Utils_Array::value($form->_task, $crmContactTaskTasks));
    if ($useTable) {
      $form->_componentTable = CRM_Core_DAO::createTempTableName('civicrm_task_action', TRUE, $qfKey);
      $sql = " DROP TABLE IF EXISTS {$form->_componentTable}";
      CRM_Core_DAO::executeQuery($sql);

      $sql = "CREATE TABLE {$form->_componentTable} ( contact_id int primary key) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci";
      CRM_Core_DAO::executeQuery($sql);
    }

    // all contacts or action = save a search
    if ((CRM_Utils_Array::value('radio_ts', self::$_searchFormValues) == 'ts_all') ||
      ($form->_task == CRM_Contact_Task::SAVE_SEARCH)
    ) {
      $sortByCharacter = $form->get('sortByCharacter');
      $cacheKey = ($sortByCharacter && $sortByCharacter != 'all') ? "{$cacheKey}_alphabet" : $cacheKey;

      // since we don't store all contacts in prevnextcache, when user selects "all" use query to retrieve contacts
      // rather than prevnext cache table for most of the task actions except export where we rebuild query to fetch
      // final result set
      if ($useTable) {
        $allCids = CRM_Core_BAO_PrevNextCache::getSelection($cacheKey, "getall");
      }
      else {
        $allCids[$cacheKey] = $form->getContactIds();
      }

      $form->_contactIds = array();
      if ($useTable) {
        $count = 0;
        $insertString = array();
        foreach ($allCids[$cacheKey] as $cid => $ignore) {
          $count++;
          $insertString[] = " ( {$cid} ) ";
          if ($count % 200 == 0) {
            $string = implode(',', $insertString);
            $sql = "REPLACE INTO {$form->_componentTable} ( contact_id ) VALUES $string";
            CRM_Core_DAO::executeQuery($sql);
            $insertString = array();
          }
        }
        if (!empty($insertString)) {
          $string = implode(',', $insertString);
          $sql = "REPLACE INTO {$form->_componentTable} ( contact_id ) VALUES $string";
          CRM_Core_DAO::executeQuery($sql);
        }
      }
      else {
        // filter duplicates here
        // CRM-7058
        // might be better to do this in the query, but that logic is a bit complex
        // and it decides when to use distinct based on input criteria, which needs
        // to be fixed and optimized.

        foreach ($allCids[$cacheKey] as $cid => $ignore) {
          $form->_contactIds[] = $cid;
        }
      }
    }
    elseif (CRM_Utils_Array::value('radio_ts', self::$_searchFormValues) == 'ts_sel') {
      // selected contacts only
      // need to perform action on only selected contacts
      $insertString = array();

      // refire sql in case of custom seach
      if ($form->_action == CRM_Core_Action::COPY) {
        // selected contacts only
        // need to perform action on only selected contacts
        foreach (self::$_searchFormValues as $name => $value) {
          if (substr($name, 0, CRM_Core_Form::CB_PREFIX_LEN) == CRM_Core_Form::CB_PREFIX) {
            $contactID = substr($name, CRM_Core_Form::CB_PREFIX_LEN);
            if ($useTable) {
              $insertString[] = " ( {$contactID} ) ";
            }
            else {
              $form->_contactIds[] = substr($name, CRM_Core_Form::CB_PREFIX_LEN);
            }
          }
        }
      }
      else {
        // fetching selected contact ids of passed cache key
        $selectedCids = CRM_Core_BAO_PrevNextCache::getSelection($cacheKey);
        foreach ($selectedCids[$cacheKey] as $selectedCid => $ignore) {
          if ($useTable) {
            $insertString[] = " ( {$selectedCid} ) ";
          }
          else {
            $form->_contactIds[] = $selectedCid;
          }
        }
      }

      if (!empty($insertString)) {
        $string = implode(',', $insertString);
        $sql = "REPLACE INTO {$form->_componentTable} ( contact_id ) VALUES $string";
        CRM_Core_DAO::executeQuery($sql);
      }
    }

    //contact type for pick up profiles as per selected contact types with subtypes
    //CRM-5521
    if ($selectedTypes = CRM_Utils_Array::value('contact_type', self::$_searchFormValues)) {
      if (!is_array($selectedTypes)) {
        $selectedTypes = explode(' ', $selectedTypes);
      }
      foreach ($selectedTypes as $ct => $dontcare) {
        if (strpos($ct, CRM_Core_DAO::VALUE_SEPARATOR) === FALSE) {
          $form->_contactTypes[] = $ct;
        }
        else {
          $separator = strpos($ct, CRM_Core_DAO::VALUE_SEPARATOR);
          $form->_contactTypes[] = substr($ct, $separator + 1);
        }
      }
    }

    if (CRM_Utils_Array::value('radio_ts', self::$_searchFormValues) == 'ts_sel'
      && ($form->_action != CRM_Core_Action::COPY)
    ) {
      $sel = CRM_Utils_Array::value('radio_ts', self::$_searchFormValues);
      $form->assign('searchtype', $sel);
      $result = CRM_Core_BAO_PrevNextCache::getSelectedContacts();
      $form->assign("value", $result);
    }

    if (!empty($form->_contactIds)) {
      $form->_componentClause = ' contact_a.id IN ( ' . implode(',', $form->_contactIds) . ' ) ';
      $form->assign('totalSelectedContacts', count($form->_contactIds));

      $form->_componentIds = $form->_contactIds;
    }
  }

  function buildQuickForm() {
    $mysql      = 'SELECT id FROM veda_civicrm_wordmailmerge'; 
    $tableCount = CRM_Core_DAO::executeQuery($mysql);
    $noofRows   = array();
    while ($tableCount->fetch()) {
      $noofRows = $tableCount->id;
    }
    $rowCount = count($noofRows);
    if( $rowCount == 0){
      $this->add('select', 'message_template', ts('Message Template'), array('' => '- select -'), TRUE);
      CRM_Core_Session::setStatus(ts("No attachement in the template."));
    }else{  
      $sql = " SELECT cmt.id, cmt.msg_title FROM civicrm_msg_template cmt 
               RIGHT JOIN veda_civicrm_wordmailmerge vcw ON ( vcw.msg_template_id = cmt.id)";
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $msgTemplatesResult[$dao->id] = $dao->msg_title;
      }
      // add form elements
      $this->add('select', 'message_template', ts('Message Template'), $msgTemplatesResult, TRUE);
      $this->addButtons(array(
        array(
          'type'      => 'submit',
          'name'      => ts('Merge'),
          'isDefault' => TRUE,
        ),
      ));
    }
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->_contactIds;
    $config = CRM_Core_Config::singleton();
    require_once $config->extensionsDir.'/uk.co.vedaconsulting.module.wordmailmerge/tinybutstrong/tbs_class.php';
    require_once $config->extensionsDir.'/uk.co.vedaconsulting.module.wordmailmerge/tinybutstrong-opentbs/tbs_plugin_opentbs.php';
    $msg_id = $this->_submitValues['message_template'];
    if(!empty($msg_id)){
      $mysql  =  " SELECT * FROM veda_civicrm_wordmailmerge WHERE msg_template_id = %1"; 
      $params = array(1 => array($msg_id, 'Integer'));
      $dao    = CRM_Core_DAO::executeQuery($mysql, $params);
      while ($dao->fetch()) {
        $fileId = $dao->file_id;
      }
      $sql    = "SELECT * FROM civicrm_file WHERE id = %1";
      $params = array(1 => array($fileId, 'Integer'));
      $dao    = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $default['fileID']        = $dao->id;
        $default['mime_type']     = $dao->mime_type;
        $default['fileName']      = $dao->uri;
        $default['cleanName']     = CRM_Utils_File::cleanFileName($dao->uri);
        $default['fullPath']      = $config->customFileUploadDir . DIRECTORY_SEPARATOR . $dao->uri;
        $default['deleteURLArgs'] = CRM_Core_BAO_File::deleteURLArgs('civicrm_file', $msg_id, $dao->id);
      }
      $defaults[$dao->id] = $default;
      $this->assign('defaults', $defaults);
      $noofContact = count($this->_contactIds);
      $TBS = new clsTinyButStrong; // new instance of TBS
      $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load the OpenTBS plugin
      $template = $default['fullPath'];
      $token          = CRM_Core_SelectValues::contactTokens();
      $tokens         = CRM_Utils_Token::formatTokensForDisplay($token);
      $firstTokenmrg  = array();
      $tokenMerge     = array();
      $firstTokenmrg  = array_merge($tokens[0]['children'], $tokens[1]['children']);
      $tokenMerge     = array_merge($firstTokenmrg, $tokens[2]['children']);
      foreach ($tokenMerge as $tmKey => $tmValue) {
        $tokenMerge[$tmKey]['token_name'] =  str_replace(array('{contact.','}'),"",$tmValue['id']);
      }
      foreach ($values as $key => $value) {
        if($key < $noofContact){
          $selectedCID  = $values[$key];
          $contact      = $this->getContact($selectedCID);
          foreach ($tokenMerge as $atKey => $atValue) {
            $vars[$key][$atValue['token_name']] = CRM_Utils_Token::getContactTokenReplacement($atValue['token_name'], $contact);
          }
          $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);
          $TBS->MergeBlock('CiviCRM',$vars);
        }
      }
      $output_file_name = 'CiviCRMWordExport.docx';
      $TBS->Show(OPENTBS_DOWNLOAD, $output_file_name);
      CRM_Utils_System::civiExit();
    }
    parent::postProcess();
  }

  function getContact($selectedCID) {
    require_once 'CRM/Core/DAO/Address.php';
    $contactResult = civicrm_api3('Contact', 'getsingle', array(
                           'sequential' => 1,
                           'contact_id' => $selectedCID,
    ));
    $sql = "SELECT * FROM civicrm_address WHERE contact_id = %1";
    $params         = array(1 => array($selectedCID, 'Integer'));
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $addressResult['street_number'] = $dao->street_number ; 
        $addressResult['street_number_suffix'] = $dao->street_number_suffix ; 
        $addressResult['street_name'] = $dao->street_name ; 
        $addressResult['street_unit'] = $dao->street_unit ; 
        $addressResult['address_name'] = $dao->name ; 
        $addressResult['master_id'] = $dao->master_id ; 
      }
      $sql = "SELECT cc.addressee_display, cc.created_date FROM civicrm_contact cc 
              LEFT JOIN civicrm_option_group oga ON (oga.name = 'addressee')
              LEFT JOIN civicrm_option_value cov ON (cc.addressee_id = cov.value AND oga.id = cov.option_group_id )
              WHERE cc.id = %1";
      $params         = array(1 => array($selectedCID, 'Integer'));
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $addressResult['addressee'] = $dao->addressee_display ; 
        $addressResult['created_date'] = $dao->created_date ; 
      }
      $sql = "SELECT cov.label FROM civicrm_contact cc 
              LEFT JOIN civicrm_value_constituent_information_1 cvci  ON (cc.id = cvci.entity_id)
              LEFT JOIN civicrm_option_value cov ON (cvci.marital_status_2 = cov.value) Where cc.id = %1";
      $params         = array(1 => array($selectedCID, 'Integer'));
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $addressResult['custom_2'] = $dao->label ; 
      }
      $sql = "SELECT cov.label FROM civicrm_contact cc 
              LEFT JOIN civicrm_value_constituent_information_1 cvci  ON (cc.id = cvci.entity_id)
              LEFT JOIN civicrm_option_value cov ON (cvci.most_important_issue_1 = cov.value) Where cc.id =%1";
      $params         = array(1 => array($selectedCID, 'Integer'));
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $addressResult['custom_1'] = $dao->label ; 
      }
      $sql = "SELECT cvci.marriage_date_3 FROM civicrm_contact cc 
              LEFT JOIN civicrm_value_constituent_information_1 cvci  ON (cc.id = cvci.entity_id)
              Where cc.id = %1";
      $params         = array(1 => array($selectedCID, 'Integer'));
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $addressResult['custom_3'] = $dao->marriage_date_3 ; 
      }
      $sql = "SELECT  csp.name, cc.employer_id FROM civicrm_contact cc 
              LEFT JOIN civicrm_address ca  ON (cc.id = ca.contact_id)
              LEFT JOIN civicrm_state_province csp ON (ca.state_province_id = csp.id AND ca.country_id = csp.country_id) where cc.id = %1";
      $params         = array(1 => array($selectedCID, 'Integer'));
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while ($dao->fetch()) {
        $addressResult['county'] = $dao->name ; 
        $addressResult['current_employer_id'] = $dao->employer_id ; 
      }
    $addressResult['checksum'] = CRM_Contact_BAO_Contact_Utils::generateChecksum($selectedCID);
    $token          = CRM_Core_SelectValues::contactTokens();
    $tokens         = CRM_Utils_Token::formatTokensForDisplay($token);
    $firstTokenmrg  = array();
    $tokenMerge     = array();
    $firstTokenmrg  = array_merge($tokens[0]['children'], $tokens[1]['children']);
    $tokenMerge     = array_merge($firstTokenmrg, $tokens[2]['children']);
    foreach ($tokenMerge as $tmKey => $tmValue) {
      $tokenMerge[$tmKey] =  str_replace(array('{contact.','}'),"",$tmValue['id']);
    }
    unset($contactResult['contact_sub_type']);
    unset($contactResult['id']);
    $flipArray = array_flip($tokenMerge);
    foreach ($contactResult as $key => $value) {
      if (array_key_exists( $key,$flipArray )){
        $contactArray[$key] = $value;
      }
    }
    $contactArrayToken     = array_merge($flipArray, $contactArray);
    $contactArrayForToken     = array_merge($contactArrayToken, $addressResult);
    return $contactArrayForToken;
  }
  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
