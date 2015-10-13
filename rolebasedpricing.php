<?php

require_once 'rolebasedpricing.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function rolebasedpricing_civicrm_config(&$config) {
  _rolebasedpricing_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function rolebasedpricing_civicrm_xmlMenu(&$files) {
  _rolebasedpricing_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function rolebasedpricing_civicrm_enable() {
  return _rolebasedpricing_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function rolebasedpricing_civicrm_disable() {
  return _rolebasedpricing_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function rolebasedpricing_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _rolebasedpricing_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function rolebasedpricing_civicrm_managed(&$entities) {
  return _rolebasedpricing_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function rolebasedpricing_civicrm_caseTypes(&$caseTypes) {
  _rolebasedpricing_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function rolebasedpricing_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _rolebasedpricing_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Function to allow multiple participant roles to register for an event
 */
function rolebasedpricing_civicrm_buildForm($formName, &$form) {
  $action = $form->getVar('_action');
  if ($formName == 'CRM_Price_Form_Field' && $action == CRM_Core_Action::UPDATE) {
    $participantRoles    = CRM_Event_PseudoConstant::participantRole();
    if (!empty($participantRoles)) {
      $form->add('select', 'participant_roles', 'participant_roles', $participantRoles);
      $element = $form->getElement('participant_roles');
      $element->setMultiple(true);
    }

    //Set Defaults
    $price_field_id = CRM_Utils_Request::retrieve('fid', 'String', $form);
    if ($price_field_id) {
      $fieldID = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $price_field_id, 'id', 'price_field_id');
    }
    if ($fieldID) {
      $defaults = rolebasedpricing_getParticipantRole($fieldID);
      $form->setDefaults(array('participant_roles' => $defaults['pids']));
    }
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postProcess
 */
function rolebasedpricing_civicrm_postProcess($formName, &$form) {
  $action = $form->getVar('_action');
  if ($formName == 'CRM_Price_Form_Field' && $action == CRM_Core_Action::UPDATE) {
    $price_field_id = CRM_Utils_Request::retrieve('fid', 'String', $form);
    $participantRoles = $form->getElement('participant_roles')->getValue();

    if ($price_field_id) {
      $fieldID = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $price_field_id, 'id', 'price_field_id');
    }

    if ($fieldID) {
      //delete any records with this field id first
      $sql = "DELETE FROM civicrm_participantrole_price WHERE field_id= %1";
      $delparams = array(1 => array($fieldID, 'Integer'));
      CRM_Core_DAO::executeQuery($sql, $delparams);

      if (!empty ($participantRoles)) {
        foreach ($participantRoles as $participantRoleId) {
          //insert new records
          $sql = "INSERT INTO civicrm_participantrole_price (participant_role, price_field_id, field_id) VALUES (%1, %2, %3)";
          $params = array(1 => array((int)$participantRoleId, 'Integer'),
            2 => array((int)$price_field_id, 'Integer'),
            3 => array((int)$fieldID, 'Integer'));
          CRM_Core_DAO::executeQuery($sql, $params);
        }
      }
    }
  }
}

/**
 * Implementation of buildAmount hook
 * To modify the priceset on the basis of participant role/price field id provided from the url
 */
function rolebasedpricing_civicrm_buildAmount($pageType, &$form, &$amount) {
  if ($pageType == 'event') {
    $priceSetId = $form->get( 'priceSetId' );
    $backupPriceSet = $form->_priceSet;
    $priceSet = &$form->_priceSet;

    $participantrole = '';
    $participantroleHashed = CRM_Utils_Request::retrieve('participantrole', 'String', $form);
    $allParticipantRoles    = CRM_Event_PseudoConstant::participantRole();

    //If we find participantrole in url
    if ($participantroleHashed) {
      foreach($allParticipantRoles as $roleId => $roleName) {
        if (md5($roleId) == $participantroleHashed) {
          $participantrole = $roleId;
          break;
        }
      }
    }
    else {
      $defaultParticipantRole = $form->_values['event']['default_role_id'];
      $participantrole = $defaultParticipantRole;
    }

    if (isset($participantrole) && !empty($participantrole)) {
      if ( !empty( $priceSetId ) ) {
        $backupAmount = $amount;
        $feeBlock =& $amount;

        $counter = 0;
        foreach( $feeBlock as $k => &$fee ) {
          if ( !is_array( $fee['options'] ) ) {
            continue;
          }

          $price_field_id = $fee['id'];
          foreach ( $fee['options'] as $key => &$option ) {
            $fieldID = $option['id'];

            $sql = "SELECT COUNT(*) as count
              FROM civicrm_participantrole_price
              WHERE participant_role = %1
              AND price_field_id = %2
              AND field_id = %3";

            $params = array(1 => array((int)$participantrole, 'Integer'),
            2 => array((int)$price_field_id, 'Integer'),
            3 => array((int)$fieldID, 'Integer'));

            $founInPriceRoleSetting = CRM_Core_DAO::singleValueQuery($sql, $params);
            if ($founInPriceRoleSetting == 1) {
              $counter++;
            }
            else {
              unset($feeBlock[$k]);
              //unsetting it from $form->priceSet as it leaves the labels of Price Options behind
              unset($priceSet['fields'][$price_field_id]);
            }
          }
        }
        //Restore priceset
        if($counter < 1) {
          $feeBlock = $backupAmount;
          $priceSet = $backupPriceSet;
        }
      }
    }
  }
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function rolebasedpricing_civicrm_install() {
  $sql = array(
    "CREATE TABLE IF NOT EXISTS `civicrm_participantrole_price` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `participant_role` int(11) unsigned NOT NULL,
      `price_field_id` int(11) unsigned NOT NULL,
      `field_id` int(11) unsigned NOT NULL,
      PRIMARY KEY (`id`)
    )",
    "ALTER TABLE `civicrm_participantrole_price`
      ADD CONSTRAINT `civicrm_participantrole_price_fk_2` FOREIGN KEY (`price_field_id`) REFERENCES `civicrm_price_field` (`id`),
      ADD CONSTRAINT `civicrm_participantrole_price_fk_1` FOREIGN KEY (`field_id`) REFERENCES `civicrm_price_field_value` (`id`);"
  );

  foreach ($sql as $query) {
    $result = CRM_Core_DAO::executeQuery($query);
  }
  return _rolebasedpricing_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function rolebasedpricing_civicrm_uninstall() {
  CRM_Core_DAO::executeQuery("DROP TABLE civicrm_participantrole_price;");
  return _rolebasedpricing_civix_civicrm_uninstall();
}

/**
 * Get Participant Roles for fieldID
 *
 * @param $oid
 *   the price option ID.
 * @return array
 */
function rolebasedpricing_getParticipantRole($fieldId) {
  $result = array('pids' => array());
  $sql = "SELECT participant_role FROM civicrm_participantrole_price WHERE field_id = %1";
  $params = array ( 1 =>
    array ( (int)$fieldId, 'Integer' )
    );
  $dao = CRM_Core_DAO::executeQuery($sql, $params);
  while ($dao->fetch()) {
    array_push($result['pids'], $dao->participant_role);
  }
  return $result;
}