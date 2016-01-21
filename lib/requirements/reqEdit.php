<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @filesource  reqEdit.php
 * @author      Martin Havlat
 *
 * Screen to view existing requirements within a req. specification.
 *
 * @internal revision
 * @since 1.9.10
 *
 *
**/
require_once("../../config.inc.php");
require_once("common.php");
require_once("users.inc.php");
require_once('requirements.inc.php');
require_once('attachments.inc.php');
require_once("csv.inc.php");
require_once("xml.inc.php");
require_once("configCheck.php");
require_once("web_editor.php");
require_once("email_api.php");
require_once('../functions/lang_api.php');

$editorCfg = getWebEditorCfg('requirement');
require_once(require_web_editor($editorCfg['type']));

testlinkInitPage($db,false,false,"checkRights");

$templateCfg = templateConfiguration();
$commandMgr = new reqCommands($db);

$args = init_args($db);
sendMailOnStatusChange($db,$args);
$gui = initialize_gui($db,$args,$commandMgr);
$pFn = $args->doAction;
$op = null;

if(method_exists($commandMgr,$pFn))
{
  $op = $commandMgr->$pFn($args,$_REQUEST);
}
renderGui($args,$gui,$op,$templateCfg,$editorCfg,$db);


/**
 * init_args
 *
 */
function init_args(&$dbHandler)
{
  $reqTitleSize = config_get('field_size')->requirement_title;
  $iParams = array("requirement_id" => array(tlInputParameter::INT_N),
                   "req_version_id" => array(tlInputParameter::INT_N),
                   "req_spec_id" => array(tlInputParameter::INT_N),
                   "req_title" => array(tlInputParameter::STRING_N,0,$reqTitleSize),
                   "req_id_cbox" => array(tlInputParameter::ARRAY_INT),
                   "reqDocId" => array(tlInputParameter::STRING_N,0,64), 
                   "reqStatus" => array(tlInputParameter::STRING_N,0,1),
                   "reqType" => array(tlInputParameter::STRING_N,0,1),
                   "containerID" => array(tlInputParameter::INT_N),
                   "scope" => array(tlInputParameter::STRING_N),
                   "countReq" => array(tlInputParameter::INT_N),
                   "expected_coverage" => array(tlInputParameter::INT_N),
                   "doAction" => array(tlInputParameter::STRING_N,0,20),
                   "itemSet" => array(tlInputParameter::ARRAY_INT),
                   "testcase_count" => array(tlInputParameter::ARRAY_INT),
                   "copy_testcase_assignment" => array(tlInputParameter::CB_BOOL),
                   "relation_id" => array(tlInputParameter::INT_N),
                   "relation_source_req_id" => array(tlInputParameter::INT_N),
                   "relation_type" => array(tlInputParameter::STRING_N),
                   "relation_destination_req_doc_id" => array(tlInputParameter::STRING_N,0,64),
                   "relation_destination_testproject_id" => array(tlInputParameter::INT_N),
                   "save_rev" => array(tlInputParameter::INT_N),
                   "do_save" => array(tlInputParameter::INT_N),
                   "log_message" => array(tlInputParameter::STRING_N),
                   "tcaseIdentity" => array(tlInputParameter::STRING_N),
                   "file_id" => array(tlInputParameter::INT_N),
                   "fileTitle" => array(tlInputParameter::STRING_N,0,100));

  $args = new stdClass();
  R_PARAMS($iParams,$args);
  $_REQUEST=strings_stripSlashes($_REQUEST);
    
  $args->req_id = $args->requirement_id;
  $args->title = $args->req_title;
  $args->arrReqIds = $args->req_id_cbox;

  $args->basehref = $_SESSION['basehref'];
  $args->tproject_id = isset($_SESSION['testprojectID']) ? intval($_SESSION['testprojectID']) : 0;
  if($args->tproject_id <= 0)
  {
    throw new Exception(__FILE__ . '::' . __FUNCTION__ . " Test project ID can not be <= 0 ");  
  }                                                                                        
  
  $mgr = new testproject($dbHandler);
  $info = $mgr->get_by_id($args->tproject_id);
  if(is_null($info))
  {
    throw new Exception(__FILE__ . '::' . __FUNCTION__ . " Unable to get test project data ");  
  }                                                                                        
  
  $args->tproject_name = $info['name'];
  $args->tcasePrefix = $info['prefix'];
  
  
  $args->user_id = isset($_SESSION['userID']) ? $_SESSION['userID'] : 0;
  
  if (!is_numeric($args->expected_coverage)) {
    $args->expected_coverage = 0;
  }
  
  $args->refreshTree = isset($_SESSION['setting_refresh_tree_on_action'])
                       ? $_SESSION['setting_refresh_tree_on_action'] : 0;
  
  $args->stay_here = isset($_REQUEST['stay_here']) ? 1 : 0;
  return $args;
}

/**
 * 
 *
 */
function renderGui(&$argsObj,$guiObj,$opObj,$templateCfg,$editorCfg,&$dbHandler)
{
  $smartyObj = new TLSmarty();
  $renderType = 'none';
  // @TODO document
  $actionOpe = array('create' => 'doCreate', 'edit' => 'doUpdate',
                     'doDelete' => '', 'doReorder' => '', 'reorder' => '',
                     'createTestCases' => 'doCreateTestCases',
                     'doCreateTestCases' => 'doCreateTestCases',
                     'doCreate' => 'doCreate', 'doUpdate' => 'doUpdate',
                     'copy' => 'doCopy', 'doCopy' => 'doCopy',
                     'doCreateVersion' => 'doCreateVersion','doCreateRevision' => 'doCreateRevision',
                     'doDeleteVersion' => '', 'doFreezeVersion' => 'doFreezeVersion',
                     'doAddRelation' => 'doAddRelation', 'doDeleteRelation' => 'doDeleteRelation',
                     'doUnfreezeVersion' => 'doUnfreezeVersion',
                     'fileUpload' => '', 'deleteFile' => '');

  $owebEditor = web_editor('scope',$argsObj->basehref,$editorCfg) ;
  switch($argsObj->doAction)
  {
    case "edit":
    case "doCreate":
      $owebEditor->Value = $argsObj->scope;
    break;

    case "fileUpload":
    case "deleteFile":
    break;

    default:
      if($opObj->suggest_revision || $opObj->prompt_for_log) 
      {
        $owebEditor->Value = $argsObj->scope;
      }
      else
      {
        $owebEditor->Value = getItemTemplateContents('requirement_template',$owebEditor->InstanceName, 
                                                     $argsObj->scope);
      }
    break;
  }

  $guiObj->askForRevision = $opObj->suggest_revision ? 1 : 0;
  $guiObj->askForLog = $opObj->prompt_for_log ? 1 : 0;


  $guiObj->scope = $owebEditor->CreateHTML();
  $guiObj->editorType = $editorCfg['type'];
  switch($argsObj->doAction) 
  {
    case "doDelete":
      $guiObj->refreshTree = 1; // has to be forced
    break;
  
    case "doCreate":
      $guiObj->refreshTree = $argsObj->refreshTree;
    break;
  
    case "doUpdate":
      // IMPORTANT NOTICE
      // we do not set tree refresh here, because on this situation
      // tree update has to be done when reqView page is called.
      // If we ask for tree refresh here we are going to do double refresh (useless and time consuming)
    break;
  }
  
  switch($argsObj->doAction)
  {
    case "addTestCase":
    case "edit":
    case "create":
    case "reorder":
    case "doDelete":
    case "doReorder":
    case "createTestCases":
    case "doCreateTestCases":
    case "doCreate":
    case "doFreezeVersion":
    case "doUnfreezeVersion":
    case "doUpdate":
    case "copy":
    case "doCopy":
    case "doCreateVersion":
    case "doDeleteVersion":
    case "doAddRelation":
    case "doDeleteRelation":
    case "doCreateRevision":
    case "removeTestCase":
    case "fileUpload":
    case "deleteFile":
      $renderType = 'template';
      $key2loop = get_object_vars($opObj);
	  
      foreach($key2loop as $key => $value)
      {
        $guiObj->$key = $value;
      }
	
      // exceptions
      $guiObj->askForRevision = $opObj->suggest_revision ? 1 : 0;
      $guiObj->askForLog = $opObj->prompt_for_log ? 1 : 0;
      $guiObj->operation = isset($actionOpe[$argsObj->doAction]) ? $actionOpe[$argsObj->doAction] : $argsObj->doAction;
      	  
      $tplDir = (!isset($opObj->template_dir)  || is_null($opObj->template_dir)) ? $templateCfg->template_dir : $opObj->template_dir;
      $tpl = is_null($opObj->template) ? $templateCfg->default_template : $opObj->template;

      $pos = strpos($tpl, '.php');
      if($pos === false)
      {
        $tpl = $tplDir . $tpl;      
      }
      else
      {
        $renderType = 'redirect';
      } 
    break;
  }
    
  $req_mgr = new requirement_mgr($dbHandler);
  $guiObj->last_doc_id = $req_mgr->get_last_doc_id_for_testproject($argsObj->tproject_id);
  $guiObj->doAction = $argsObj->doAction;

  switch($renderType)
  {
    case 'template':
      $smartyObj->assign('gui',$guiObj);
      $smartyObj->display($tpl);
    break;  
 
    case 'redirect':
      header("Location: {$tpl}");
      exit();
    break;

    default:
    break;
  }
}

/**
 * 
 *
 */
function initialize_gui(&$dbHandler,&$argsObj,&$commandMgr)
{
  $req_spec_mgr = new requirement_spec_mgr($dbHandler);

  // new dBug($argsObj);

  $gui = $commandMgr->initGuiBean();
  $gui->req_cfg = config_get('req_cfg');
  
  $gui->req_spec_id = $argsObj->req_spec_id;
  if ($argsObj->req_spec_id)
  {
    $gui->requirements_count = $req_spec_mgr->get_requirements_count($gui->req_spec_id);
    $gui->req_spec = $req_spec_mgr->get_by_id($gui->req_spec_id);
  }
  $gui->user_feedback = null;
  $gui->main_descr = lang_get('req_spec_short');
  if (isset($gui->req_spec))
  {
     $gui->main_descr .= config_get('gui_title_separator_1') . $gui->req_spec['title'];
  }
  $gui->action_descr = null;
  
  $gui->grants = new stdClass();
  $gui->grants->req_mgmt = has_rights($dbHandler,"mgt_modify_req");
  $gui->grants->mgt_view_events = has_rights($dbHandler,"mgt_view_events");
  
  $gui->req_version_id = $argsObj->req_version_id;
  $gui->preSelectedType = TL_REQ_TYPE_USE_CASE;
  
  $gui->stay_here = $argsObj->stay_here;
  return $gui;
}


function checkRights(&$db,&$user)
{
  return ($user->hasRight($db,'mgt_view_req') && $user->hasRight($db,'mgt_modify_req'));
}
	
function getStatusIdentifier($statusAbbr) {
	$fullStatusName = "";
	switch($statusAbbr) {
		case "D": $fullStatusName = lang_get("req_status_draft"); break;
		case "R": $fullStatusName = lang_get("req_status_review"); break;
		case "W": $fullStatusName = lang_get("req_status_rework"); break;
		case "F": $fullStatusName = lang_get("req_status_finish"); break;
		case "I": $fullStatusName = lang_get("req_status_implemented"); break;
		case "V": $fullStatusName = lang_get("review_status_valid"); break;
		case "N": $fullStatusName = lang_get("req_status_not_testable"); break;
		case "O": $fullStatusName = lang_get("req_status_obsolete"); break;
	}
	return $fullStatusName;
}
	
function sendMailOnStatusChange(&$db,&$args)
{
	if(strcmp($_POST["req_spec_id"],"") !== 0) {
		$fieldMetadataMgr = new cfield_mgr($db);
		$reqMgr = new requirement_mgr($db);
		$currentReq = $reqMgr->get_by_id($args->requirement_id)[0];
		$fieldNames = $reqMgr->getAllNotificationFieldAssignments($args->tproject_id);
		
		foreach($fieldNames as $fieldName => $fieldData) {
			if(strcmp($fieldName,"Status") === 0) {
				$reqState = getStatusIdentifier($args->reqStatus);
				$oldFieldVal = getStatusIdentifier($currentReq["status"]);
			}
			else
			{
				$fieldMetadata = array_pop($fieldMetadataMgr->get_by_name($fieldName));
				$customFieldPOSTName = "custom_field_".$fieldMetadata["type"]."_".$fieldMetadata["id"];
				
				$reqState = $_POST[$customFieldPOSTName];

				$oldFieldValArr = $reqMgr->get_linked_cfields($currentReq["id"],$currentReq["version_id"]);
				$oldFieldVal = NULL;
				//richtiges cfield raussuchen
				for($i = 1; $i<=sizeof($oldFieldValArr); $i++) {
					if(strcmp($oldFieldValArr[$i]["name"], $fieldName) === 0) {
						$oldFieldVal = trim($oldFieldValArr[$i]["value"]);
						break;
					}
				}
			}
			
			if(strcmp($oldFieldVal,$reqState) !== 0 && strlen($reqState)!==0) {
				//string contains <p> and </p> at the end/start
				if(strcmp($fieldName,"Status") === 0) {
					//$reqState = getStatusIdentifier($reqState);
					$fieldMetadata["id"] = 0;
				}

				$scope = substr($currentReq["scope"],3,strlen($currentReq["scope"])-7);
				$reqTitle = $currentReq["title"];
				$usersObj = new tlUser($db);
				$modifier = array_pop($usersObj->getNames($db,$args->user_id))["login"];
				$req_doc_id = $currentReq[req_doc_id];
				$from = config_get("from_email");
				$sqlWhere = "WHERE id = (SELECT assigned_user_id"
									."	FROM req_notify_assignments"
									."	WHERE test_project_id = {$args->tproject_id}"
									."	AND field_id = {$fieldMetadata["id"]}"
									."	AND field_value = \"$reqState\")";
				
				$to = array_pop(tlUser::getAll($db, $sqlWhere))->emailAddress;
				$subject = "[Requirement State Change] ($reqState) $req_doc_id:$reqTitle";
				$body = lang_get('req_change_notification');
				$body = str_replace("%reqTitle", $reqTitle, $body);
				$body = str_replace("%modifier", $modifier, $body);
				$body = str_replace("%oldFieldVal", $oldFieldVal, $body);
				$body = str_replace("%reqState", $reqState, $body);
				$body = str_replace("%scope", $scope, $body);
				
				$retVal = email_send($from,$to,$subject,$body,'',false,true,null);
			}
		}
	}
}
?>