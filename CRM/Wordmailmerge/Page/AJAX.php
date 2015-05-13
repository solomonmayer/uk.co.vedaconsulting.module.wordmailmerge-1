<?php

class CRM_Wordmailmerge_Page_AJAX {
	static function msgTemplateIdAjax(){
			$msgTemplateId = $_POST['entityID'];
			$mysql  = "DELETE FROM `veda_civicrm_wordmailmerge` WHERE msg_template_id = %1";
		    $params = array(1 => array($msgTemplateId, 'Integer'));
			CRM_Core_DAO::executeQuery($mysql, $params);
      		exit;
		}
  
  
}
