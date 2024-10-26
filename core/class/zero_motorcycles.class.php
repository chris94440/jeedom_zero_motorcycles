<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
//require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
  require_once __DIR__  . '/../../../../core/php/core.inc.php';

if (!class_exists('imaProtectNewAPI')) {
	//require_once dirname(__FILE__) . '/../../3rdparty/zero_motorcyclesAPI.class.php';
  	require_once __DIR__  . '/../../3rdparty/imaProtectNewAPI.class.php';
}

class zero_motorcycles extends eqLogic {
    /*     * *************************Attributs****************************** */
	const IMA_ON=2;
	const IMA_PARTIAL=1;
	const IMA_OFF=0;
	const IMA_UNKNOWN=-1;
	const IMA_IGNORED=-2;

	const SNAPSHOT_PATH='/var/www/html/plugins/zero_motorcycles/data/img/snapshots/';
	const ICON_PATH='/var/www/html/plugins/zero_motorcycles/data/img/icons/';
	const ACCESS_ICON_PATH='/plugins/zero_motorcycles/data/img/icons/';

	const BASE_URL='https://mongol.brono.com/mongol/api.php?commandname=';
	/*
	https://mongol.brono.com/mongol/api.php?commandname=get_units&format=json&user=cdemonge91800@gmail.com&pass=ZeroSrf2024$
	https://mongol.brono.com/mongol/api.php?commandname=get_last_transmit&format=json&user=cdemonge91800@gmail.com&pass=ZeroSrf2024$&unitnumber=1073108
	*/
	  
    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom    */
	public static function cron() {
		$autorefresh = config::byKey('autorefresh', __CLASS__);
		if ($autorefresh != '') {
			try {
                $c = new Cron\CronExpression(checkAndFixCron($autorefresh), new Cron\FieldFactory);
                if ($c->isDue()) {
                    log::add(__CLASS__, 'debug', 'Exécution du cron Zero Motorcycles');
                  	
		            foreach (eqLogic::byType(__CLASS__, true) as $zero_motorcycles) {                      	
						log::add(__CLASS__, 'debug', 'ToDo cron execution');
                   }
                   
				}
			} catch (Exception $exc) {
				log::add(__CLASS__, 'error', __("Erreur lors de l'exécution du cron ", __FILE__) . $exc->getMessage());
			}
		}
	}

	public function synchronize() {
		$this->writeSeparateLine();
		log::add(__CLASS__, 'debug', 'Start' . __FUNCTION__ . ' equipement Zero Motorcycles');

		$this->checkCredentials();			
		list($httpcode, $result, $header) = $this->doRequest(self::BASE_URL.'get_units&format=json&user='..'&pass=',null, "GET", null);	
      	if (isset($httpcode) and $httpcode >= 400 ) {
          	throw new Exception($this->manageErrorMessage($httpcode,$result));
        } else {
			log::add(__CLASS__, 'debug', '    -> '. json_encode($result));
		}

		log::add(__CLASS__, 'debug', 'End' . __FUNCTION__ . ' equipement Zero Motorcycles');
		$this->writeSeparateLine();
	}

	private static function checkCredentials() {
		if (empty($this->getConfiguration('login_zero_motorcycles'))) {
			throw new Exception(__('L\'identifiant ne peut pas être vide',__FILE__));
		}

		if (empty($this->getConfiguration('password_zero_motorcycles'))) {
			throw new Exception(__('Le mot de passe ne peut etre vide',__FILE__));
		}
	}

	private static function doRequest($url, $data, $method, $headers) {		
		log::add(__CLASS__, 'debug', "			==> doRequest");
		log::add(__CLASS__, 'debug', "				==> Params : $url | $data | $method | ".json_encode($headers));
		log::add(__CLASS__, 'debug', "				==> Params json input : " . json_encode($data));
	  	$curl = curl_init();
	  	curl_setopt($curl, CURLOPT_URL,				$url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
	  	curl_setopt($curl, CURLOPT_HEADER, 			true);
	
	
		//voir la gestion de $cookie
		switch($method)  {
			case "GET":
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 	true);
				//curl_setopt($curl, CURLOPT_HTTPHEADER, 		$headers);
				break;
		}
			  
		$resultCurl = curl_exec($curl);
		
		//Get http response code
		$httpRespCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		//Get header info
		$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
			$header = substr($resultCurl, 0, $header_size);
		
		//Get body
		$body = substr($resultCurl, $header_size);
	  
	  	//close curl
		curl_close($curl);

		log::add(__CLASS__, 'debug', "				==> Response");
		log::add(__CLASS__, 'debug', "					# Code Http : $httpRespCode");
   
		if (strpos($body, 'rejected')) {
			throw new Exception($this->manageErrorMessage('500','Request was rejected by server -> '.$url));
		} else {
			if ($this->isJson($body)) {
				log::add(__CLASS__, 'debug', "					# Body  : ".$body);
			}
		}
		log::add(__CLASS__, 'debug', "					# Header  : ".$header);
	  
	  return array($httpRespCode, $body, $header);
  	}

	private static function manageErrorMessage($httpCode,$error) {
		log::add(__CLASS__, 'debug', "			" . __FUNCTION__ . " : " . $error . "|" .$httpCode);
		$errorMessage="Unknown error";
		if (!$this->IsNullOrEmpty($error)) {
			$errorMessage=str_replace("\"","",$error);
				$errorArray=json_decode($error,true);
				if (!$this->IsNullOrEmpty($errorArray["error"])) {
					$errorMsg=json_decode($errorArray,true);
					if (!$this->IsNullOrEmpty($errorMsg["code"]) and !$this->IsNullOrEmpty($errorMsg["message"]) ) {
					$errorMsgCode=$errorMsg["code"];
					$errorMsgMessage=$errorMsg["message"];
						$errorMessage=$errorMsg["message"] .' - '. $errorMsgCode;
						log::add(__CLASS__, 'debug', "				==> decode json  : " . $errorMsgCode . "|" . $errorMsgMessage);
				}
			}
		}
	  
		if (!$this->IsNullOrEmpty($httpCode)) {
			$errorMessage .= " (". $httpCode . ")";
		}
	  
		log::add(__CLASS__, 'debug', "			==> errorMessage : " . $errorMessage);
		return $errorMessage;
  	}

	private static function stringContains($string_1, $string_2) {
      	if ((strtolower($string_1) == strtolower($string_2)) or 
              (strpos(strtolower($string_1),strtolower($string_2)) !== false ) or 
              (strpos(strtolower($string_2),strtolower($string_1)) !== false )) {
          	return true;
        } else {
          	return false;
        }      
    }

	private function fmt_date($timeStamp) {
		setlocale(LC_TIME, 'fr_FR.utf8','fra');
		return(ucwords(strftime("%a %d %b %T",$timeStamp)));
	}


     /* Fonction exécutée automatiquement toutes les heures par Jeedom */
    public static function cronHourly() {
      log::add(__CLASS__, 'debug', 'Exécution du cron hourly zero_motorcycles - Start');
      foreach (eqLogic::byType(__CLASS__, true) as $zero_motorcycles) {
        $zero_motorcycles->writeSeparateLine();
        
        $zero_motorcycles->writeSeparateLine();
      }
      log::add(__CLASS__, 'debug', 'Exécution du cron hourly zero_motorcycles - End');
    }

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {        
    }

    public function postInsert() {        
    }

    public function preSave() {        
    }

    public function postSave() {
    }
  
  	private function createCmd(){
      log::add(__CLASS__, 'debug',  "Création des commandes : start");

        $zero_motorcyclesCmd = $this->getCmd(null, 'statusAlarme');
		if (! is_object($zero_motorcyclesCmd))
		{
			$zero_motorcyclesCmd = new zero_motorcyclesCmd();
			$zero_motorcyclesCmd->setName(__('Statut alarme', __FILE__));
			//$zero_motorcyclesCmd->setOrder(1);
			$zero_motorcyclesCmd->setEqLogic_id($this->id);
			$zero_motorcyclesCmd->setLogicalId('statusAlarme');
			$zero_motorcyclesCmd->setConfiguration('data', 'statusAlarme');
			$zero_motorcyclesCmd->setConfiguration('historizeMode', 'none');
			$zero_motorcyclesCmd->setType('info');
			$zero_motorcyclesCmd->setSubType('numeric');
			$zero_motorcyclesCmd->setTemplate('dashboard', 'line');
			$zero_motorcyclesCmd->setTemplate('mobile', 'line');
			$zero_motorcyclesCmd->setIsHistorized(1);
			$zero_motorcyclesCmd->setDisplay('graphStep', '1');
			$zero_motorcyclesCmd->setConfiguration("MaxValue", self::IMA_ON);
			$zero_motorcyclesCmd->setConfiguration("MinValue", self::IMA_UNKNOWN);
			//$zero_motorcyclesCmd->save();
			$zero_motorcyclesCmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
        }
		$zero_motorcyclesCmd->save();
      
      
      	$zero_motorcyclesCmd = $this->getCmd(null, 'alarmMode');
		if (! is_object($zero_motorcyclesCmd))		{
			$zero_motorcyclesCmd = new zero_motorcyclesCmd();
			$zero_motorcyclesCmd->setName(__('Mode alarme', __FILE__));
			//$zero_motorcyclesCmd->setOrder(11);
			$zero_motorcyclesCmd->setEqLogic_id($this->id);
			$zero_motorcyclesCmd->setLogicalId('alarmMode');
			$zero_motorcyclesCmd->setConfiguration('data', 'alarmMode');
			$zero_motorcyclesCmd->setConfiguration('historizeMode', 'none');
			$zero_motorcyclesCmd->setType('info');
			$zero_motorcyclesCmd->setSubType('string');
			$zero_motorcyclesCmd->setTemplate('dashboard', 'line');
			$zero_motorcyclesCmd->setTemplate('mobile', 'line');
			$zero_motorcyclesCmd->setIsHistorized(1);
			$zero_motorcyclesCmd->setDisplay('graphStep', '1');
			$zero_motorcyclesCmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
        }
		$zero_motorcyclesCmd->save();
      
      	$zero_motorcyclesCmd = $this->getCmd(null, 'alarmState');
		if (! is_object($zero_motorcyclesCmd))		{
			$zero_motorcyclesCmd = new zero_motorcyclesCmd();
			$zero_motorcyclesCmd->setName(__('Etat alarme', __FILE__));
			//$zero_motorcyclesCmd->setOrder(12);
			$zero_motorcyclesCmd->setEqLogic_id($this->id);
			$zero_motorcyclesCmd->setLogicalId('alarmState');
			$zero_motorcyclesCmd->setConfiguration('data', 'alarmState');
			$zero_motorcyclesCmd->setConfiguration('historizeMode', 'none');
			$zero_motorcyclesCmd->setType('info');
			$zero_motorcyclesCmd->setSubType('binary');
			$zero_motorcyclesCmd->setTemplate('dashboard', 'line');
			$zero_motorcyclesCmd->setTemplate('mobile', 'line');
			$zero_motorcyclesCmd->setIsHistorized(1);
			$zero_motorcyclesCmd->setDisplay('graphStep', '1');
          	$zero_motorcyclesCmd->setConfiguration("MaxValue", 1);
			$zero_motorcyclesCmd->setConfiguration("MinValue", 0);
			$zero_motorcyclesCmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
        }
		$zero_motorcyclesCmd->save();
      
        $zero_motorcyclesCmd = $this->getCmd(null, 'binaryAlarmStatus');
		if (! is_object($zero_motorcyclesCmd))		{
			$zero_motorcyclesCmd = new zero_motorcyclesCmd();
			$zero_motorcyclesCmd->setName(__('Statut binaire alarme', __FILE__));
			//$zero_motorcyclesCmd->setOrder(13);
			$zero_motorcyclesCmd->setEqLogic_id($this->id);
			$zero_motorcyclesCmd->setLogicalId('binaryAlarmStatus');
			$zero_motorcyclesCmd->setConfiguration('data', 'binaryAlarmStatus');
			$zero_motorcyclesCmd->setConfiguration('historizeMode', 'none');
			$zero_motorcyclesCmd->setType('info');
			$zero_motorcyclesCmd->setSubType('binary');
			$zero_motorcyclesCmd->setTemplate('dashboard', 'line');
			$zero_motorcyclesCmd->setTemplate('mobile', 'line');
			$zero_motorcyclesCmd->setIsHistorized(1);
			$zero_motorcyclesCmd->setDisplay('graphStep', '1');
			$zero_motorcyclesCmd->setConfiguration("MaxValue", 1);
			$zero_motorcyclesCmd->setConfiguration("MinValue", 0);
			$zero_motorcyclesCmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
        }
		$zero_motorcyclesCmd->save();      
      
      	$cmd = $this->getCmd(null, 'alarmeEvents');
		if (! is_object($cmd))		{
          	$cmd = new zero_motorcyclesCmd();
            $cmd->setName('Evenements');
			//$cmd->setOrder(2);
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId('alarmeEvents');
            $cmd->setUnite('');
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setIsVisible(1);
            $cmd->setIsHistorized(0);
          	$cmd->setConfiguration('cmdsMaked', true);
          	$cmd->setTemplate('dashboard', 'default');
			$cmd->setTemplate('mobile','default');
			$cmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
        }
		$cmd->save();

      	$cmd = $this->getCmd(null, 'alarmeEventsBrute');
		if (! is_object($cmd))		{
          	$cmd = new zero_motorcyclesCmd();
            $cmd->setName('Evenements données brutes');
			//$cmd->setOrder(4);
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId('alarmeEventsBrute');
            $cmd->setUnite('');
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setIsVisible(1);
            $cmd->setIsHistorized(0);
          	$cmd->setConfiguration('cmdsMaked', true);
          	$cmd->setTemplate('dashboard', 'default');
			$cmd->setTemplate('mobile','default');
			$cmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
        }
		$cmd->save();
		
      	$cmdCameraSnapshot = $this->getCmd(null, 'cameraSnapshot');
		if (! is_object($cmdCameraSnapshot))		{
          	$cmdCameraSnapshot = new zero_motorcyclesCmd();
            $cmdCameraSnapshot->setName('Images caméras');
			//$cmdCameraSnapshot->setOrder(3);
            $cmdCameraSnapshot->setEqLogic_id($this->getId());
            $cmdCameraSnapshot->setLogicalId('cameraSnapshot');
            $cmdCameraSnapshot->setUnite('');
            $cmdCameraSnapshot->setType('info');
            $cmdCameraSnapshot->setSubType('string');
            $cmdCameraSnapshot->setIsVisible(1);
            $cmdCameraSnapshot->setIsHistorized(0);
          	$cmdCameraSnapshot->setTemplate('dashboard', 'default');
			$cmdCameraSnapshot->setTemplate('mobile','default');
			$cmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdCameraSnapshot->getName().' (LogicalId : '.$cmdCameraSnapshot->getLogicalId().')');
        }
		$cmdCameraSnapshot->save();
      
      	$cmdCameraSnapshotBrute = $this->getCmd(null, 'cameraSnapshotBrute');
		if (! is_object($cmdCameraSnapshotBrute))		{
          	$cmdCameraSnapshotBrute = new zero_motorcyclesCmd();
            $cmdCameraSnapshotBrute->setName('Images caméras données brutes');
			//$cmdCameraSnapshotBrute->setOrder(5);
            $cmdCameraSnapshotBrute->setEqLogic_id($this->getId());
            $cmdCameraSnapshotBrute->setLogicalId('cameraSnapshotBrute');
            $cmdCameraSnapshotBrute->setUnite('');
            $cmdCameraSnapshotBrute->setType('info');
            $cmdCameraSnapshotBrute->setSubType('string');
            $cmdCameraSnapshotBrute->setIsVisible(1);
            $cmdCameraSnapshotBrute->setIsHistorized(0);
          	$cmdCameraSnapshotBrute->setTemplate('dashboard', 'default');
			$cmdCameraSnapshotBrute->setTemplate('mobile','default');
			$cmdCameraSnapshotBrute->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdCameraSnapshotBrute->getName().' (LogicalId : '.$cmdCameraSnapshotBrute->getLogicalId().')');
        }
		$cmdCameraSnapshotBrute->save();
      
      	$cmdRefreshAlarmStatus = $this->getCmd(null, 'refreshAlarmeStatus');
		if (!is_object($cmdRefreshAlarmStatus)) {
			$cmdRefreshAlarmStatus = new zero_motorcyclesCmd();
			//$cmdRefreshAlarmStatus->setOrder(6);
			$cmdRefreshAlarmStatus->setName('Rafraichir statut alarme');
			$cmdRefreshAlarmStatus->setEqLogic_id($this->getId());
			$cmdRefreshAlarmStatus->setLogicalId('refreshAlarmeStatus');
			$cmdRefreshAlarmStatus->setType('action');
			$cmdRefreshAlarmStatus->setSubType('other');
          	$cmdRefreshAlarmStatus->setTemplate('dashboard', 'default');
			$cmdRefreshAlarmStatus->setTemplate('mobile','default');
          	$cmdRefreshAlarmStatus->dontRemoveCmd();
			$cmdRefreshAlarmStatus->setOrder($this->getLastindexCmd());
			log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdRefreshAlarmStatus->getName().' (LogicalId : '.$cmdRefreshAlarmStatus->getLogicalId().')');
		}
		$cmdRefreshAlarmStatus->save();
      
      	$cmdRefreshCameraSnapshot = $this->getCmd(null, 'refreshCameraSnapshot');
		if (!is_object($cmdRefreshCameraSnapshot)) {
			$cmdRefreshCameraSnapshot = new zero_motorcyclesCmd();
			//$cmdRefreshCameraSnapshot->setOrder(8);
			$cmdRefreshCameraSnapshot->setName('Rafraichir capture caméras');
			$cmdRefreshCameraSnapshot->setEqLogic_id($this->getId());
			$cmdRefreshCameraSnapshot->setLogicalId('refreshCameraSnapshot');
			$cmdRefreshCameraSnapshot->setType('action');
			$cmdRefreshCameraSnapshot->setSubType('other');
          	$cmdRefreshCameraSnapshot->setTemplate('dashboard', 'default');
			$cmdRefreshCameraSnapshot->setTemplate('mobile','default');
			$cmdRefreshCameraSnapshot->setOrder($this->getLastindexCmd());
			log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdRefreshCameraSnapshot->getName().' (LogicalId : '.$cmdRefreshCameraSnapshot->getLogicalId().')');
		}
		$cmdRefreshCameraSnapshot->save();
      

      	$cmdRefreshEventsAlarm = $this->getCmd(null, 'refreshAlarmEvents');
		if (!is_object($cmdRefreshEventsAlarm)) {
			$cmdRefreshEventsAlarm = new zero_motorcyclesCmd();
			//$cmdRefreshEventsAlarm->setOrder(7);
			$cmdRefreshEventsAlarm->setName('Rafraichir évènements alarme');
			$cmdRefreshEventsAlarm->setEqLogic_id($this->getId());
			$cmdRefreshEventsAlarm->setLogicalId('refreshAlarmEvents');
			$cmdRefreshEventsAlarm->setType('action');
			$cmdRefreshEventsAlarm->setSubType('other');
          	$cmdRefreshEventsAlarm->setTemplate('dashboard', 'default');
			$cmdRefreshEventsAlarm->setTemplate('mobile','default');
			$cmdRefreshEventsAlarm->setOrder($this->getLastindexCmd());
			log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdRefreshEventsAlarm->getName().' (LogicalId : '.$cmdRefreshEventsAlarm->getLogicalId().')');
		}
		$cmdRefreshEventsAlarm->save();
	  
      	$cmdActionModeAlarme = $this->getCmd(null, 'setModeAlarme');
        if ( ! is_object($cmdActionModeAlarme)) {
          $cmdActionModeAlarme = new zero_motorcyclesCmd();
          //$cmdActionModeAlarme->setOrder(9);
          $cmdActionModeAlarme->setName('Action mode alarme');
          $cmdActionModeAlarme->setEqLogic_id($this->getId());
          $cmdActionModeAlarme->setLogicalId('setModeAlarme');
          $cmdActionModeAlarme->setType('action');
          $cmdActionModeAlarme->setSubType('message');
		  $cmdActionModeAlarme->setOrder($this->getLastindexCmd());
          log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdActionModeAlarme->getName().' (LogicalId : '.$cmdActionModeAlarme->getLogicalId().')');
        }
  
		$cmdActionModeAlarme->setConfiguration('title', '');
		$cmdActionModeAlarme->setConfiguration('listValue', '');
		$cmdActionModeAlarme->setDisplay('title_placeholder','Mode alarme');
		$cmdActionModeAlarme->setDisplay('title_disable', 0);
		$cmdActionModeAlarme->setDisplay('title_possibility_list', 'on,off,partial');
		$cmdActionModeAlarme->save();
      
      	$cmdActionScreenshot = $this->getCmd(null, 'actionScreenshot');
		if (!is_object($cmdActionScreenshot)) {
			$cmdActionScreenshot = new zero_motorcyclesCmd();
			//$cmdActionScreenshot->setOrder(10);
			$cmdActionScreenshot->setName('Actions sur une image caméra');
			$cmdActionScreenshot->setEqLogic_id($this->getId());
			$cmdActionScreenshot->setLogicalId('actionScreenshot');
			$cmdActionScreenshot->setType('action');
			$cmdActionScreenshot->setSubType('message');
          	$cmdActionScreenshot->setTemplate('dashboard', 'default');
			$cmdActionScreenshot->setTemplate('mobile','default');
			$cmdActionScreenshot->setOrder($this->getLastindexCmd());
			log::add(__CLASS__, 'debug', 'Création de la commande '.$cmdActionScreenshot->getName().' (LogicalId : '.$cmdActionScreenshot->getLogicalId().')');
		}
      	$cmdActionScreenshot->setDisplay('title_placeholder','Action sur caméra');
		$cmdActionScreenshot->save();

		$cmd = $this->getCmd(null, 'cameraSnapshotImage');
		if (! is_object($cmd))		{
          	$cmd = new zero_motorcyclesCmd();
            $cmd->setName('Dernière image snapshot');
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId('cameraSnapshotImage');
            $cmd->setUnite('');
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setIsVisible(1);
            $cmd->setIsHistorized(0);
          	$cmd->setTemplate('dashboard', 'default');
			$cmd->setTemplate('mobile','default');
			$cmd->setOrder($this->getLastindexCmd());
          	log::add(__CLASS__, 'debug', 'Création de la commande '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
        }
		$cmd->save();

		log::add(__CLASS__, 'debug',  "Création des commandes - End");
    }

    public function preUpdate() {
		log::add(__CLASS__, 'debug',  "appel preUpdate");
   		$this->checkCredentials();            
    }

    public function postUpdate() {
      	//$this->createCmd();
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }


    /*     * **********************Getteur Setteur*************************** */
  private function mefDateTime($dateTime) {
	try{
		$date=new DateTime($dateTime);
		return $date->format('Y-m-d H:i:s');
	} catch (Exception $e) {
		log::add(__CLASS__, 'error',  "  Error on DateTime conversion -> " . $e->getMessage() . '('.$dateTime.')');
		//force actual date
		$date = new DateTime();
		return $date->format('Y-m-d H:i:s');
	}
  }

  private function IsNullOrEmpty($input){
    return (!isset($input) || trim($input)==='');
  }
  
  public function manageErrorAPI($function,$errorMessage) {
    	$message="$function => ".$errorMessage;
    	throw new Exception($message);
  }
  
  public function writeSeparateLine(){
		log::add(__CLASS__, 'debug',  "*********************************************************************");
  }

 
	public function toHtml($_version = 'dashboard') {
	  log::add(__CLASS__, 'debug',  "Function toHtml - Start");
	  
	  $replace = $this->preToHtml($_version);
	  log::add(__CLASS__, 'debug',  "Function toHtml - replace avant remplacement : $replace");
	  //$replace=array();
	  log::add(__CLASS__, 'debug',  "Function toHtml - ap pretohtml");
	  if (!is_array($replace)) {
		log::add(__CLASS__, 'debug',  "Function toHtml - dans le if");
		return $replace;
		log::add(__CLASS__, 'debug',  "Function toHtml - return replace");
		
	  }

      $version = jeedom::versionAlias($_version);
		log::add(__CLASS__, 'debug',  "Function toHtml - new version $version");
		$cmdis=$this->getCmd('info', null);
		foreach ($cmdis as $cmd) {
			$cmd_LogId=$cmd->getLogicalId(); 
			log::add(__CLASS__, 'debug',  "Function toHtml - commande info : $cmd_LogId | id : ". $cmd->getId());
			$replace['#' . $cmd_LogId . '#'] = $cmd->execCmd();
			$replace['#' . $cmd_LogId . '_id#'] = $cmd->getId();
			$replace['#' . $cmd_LogId . '_collectDate#'] =date('d-m-Y H:i:s',strtotime($cmd->getCollectDate()));
			$replace['#' . $cmd_LogId . '_updatetime#'] =date('d-m-Y H:i:s',strtotime( $this->getConfiguration('updatetime')));
			
		}
	  
		$cmdas=$this->getCmd('action', null);
		foreach ($cmdas as $cmd) {
			$cmd_LogId=$cmd->getLogicalId(); 
			$replace['#' . $cmd_LogId . '_id#'] = $cmd->getId();
			log::add(__CLASS__, 'debug',  "Function toHtml - commande action : $cmd_LogId | id : ". $cmd->getId());
			if ($cmd->getConfiguration('listValue', '') != '') {
				$listOption = '';
				$elements = explode(';', $cmd->getConfiguration('listValue'));
				$foundSelect = false;
				foreach ($elements as $element) {
					//list($item_val, $item_text) = explode('|', $element);
					$coupleArray = explode('|', $element);
					$item_val = $coupleArray[0];
					$item_text  = (isset($coupleArray[1])) ? $coupleArray[1]: $item_val;
				  
					$cmdValue = $cmd->getCmdValue();
					
					if (is_object($cmdValue) && $cmdValue->getType() == 'info') {
						if ($cmdValue->execCmd() == $item_val) {
							$valSelected=$item_text;
							$listOption .= '<option value="' . $item_val . '" selected>' . $item_text . '</option>';
							$foundSelect = true;
						} else {
							$listOption .= '<option value="' . $item_val . '">' . $item_text . '</option>';
						}
					} else {
						$listOption .= '<option value="' . $item_val . '">' . $item_text . '</option>';
					}
				}
				if (!$foundSelect) {
					$listOption = '<option value="" selected>Aucun</option>' . $listOption;
					$replace['#' . $cmd->getLogicalId() . '_Value#'] = 'Aucun';
				}else{
					$replace['#' . $cmd->getLogicalId() . '_Value#'] = $valSelected;
				}
				  
				
				$replace['#' . $cmd->getLogicalId() . '_listValue#'] = $listOption;
			}
		}
		 
		//pass ima option for xo code
		$replace['#checkPwdXO#'] = $this->getConfiguration('checkPwdXO');

		//pass ima option if xo code is alphanumeric
		$replace['#cfgXOAlpha#'] = $this->getConfiguration('cfgXOAlpha');

      log::add(__CLASS__, 'debug',  "Function toHtml - Value replace : ".json_encode($replace));	
      $html = template_replace($replace, getTemplate('core', $_version, 'default_zero_motorcycles', __CLASS__));
      cache::set('widgetHtml' . $_version . $this->getId(), $html, 1);
      log::add(__CLASS__, 'debug',  "Function toHtml - End");
      return $html;
	}
}


class zero_motorcyclesCmd extends cmd {
  	public function execute($_options = array()) {
      	$eqlogic = $this->getEqLogic();
      	$logicalId=$this->getLogicalId();
      	log::add(__CLASS__, 'debug',  "  * Execution cmd alarmeIMA | cmd : $logicalId => title : ".$_options['title'] . " | message : " .$_options['message']);
      	switch ($logicalId) {
				case 'setModeAlarme':
            		log::add(__CLASS__, 'debug',  "Click on setModeAlarme equipement");
					$eqlogic->writeSeparateLine();
            		
            		if (isset($_options['title'])){
                      if ($_options['title'] == 'on') {
                        	$eqlogic->setAlarmToOn();
                        	//$eqlogic->checkAndUpdateCmd('statusAlarme', '2');
                      } else if ($_options['title'] == 'partial') {
                        	$eqlogic->setAlarmToPartial();
                        	//$eqlogic->checkAndUpdateCmd('statusAlarme', '1');
                      } else if ($_options['title'] == 'off') {
                        if (isset($_options['message'])) {
                          	$eqlogic->setAlarmToOff($_options['message']);
                          	//$eqlogic->checkAndUpdateCmd('statusAlarme', '0');
                        } else {
                          log::add(__CLASS__, 'debug',  "Click on setModeAlarme equipement ==> message absent");
                        }
                      } else {
                        log::add(__CLASS__, 'debug',  "Click on setModeAlarme equipement ==> action demandée non gérée");
                      }
                    } else {
                      log::add(__CLASS__, 'debug',  "Click on setModeAlarme equipement ==> aucune action demandée");
                    }
            		//log::add(__CLASS__, 'debug',  "Simulate click on refresh alarm status after action on it");
                    $eqlogic->writeSeparateLine();
            		$eqlogic->getCmd(null, 'refreshAlarmeStatus')->execCmd();
            		break;
          		case 'refreshAlarmeStatus':
            		$eqlogic->writeSeparateLine();
            		log::add(__CLASS__, 'debug',  "Click on refresh alarm status");
					$eqlogic->GetAlarmState();
            		$eqlogic->writeSeparateLine();
            		break;
          		case 'refreshAlarmEvents':
            		$eqlogic->writeSeparateLine();
            		log::add(__CLASS__, 'debug',  "Click on refresh alarm events");
					$alarmEvent=$eqlogic->GetAlarmEvents();
            		if (isset($alarmEvent)) {
                      	log::add(__CLASS__, 'debug', " * MAJ alarmeEventsBrute");
                      	$eqlogic->checkAndUpdateCmd('alarmeEventsBrute', $alarmEvent);
						log::add(__CLASS__, 'debug', " * MAJ alarmeEvents");
						$eqlogic->checkAndUpdateCmd('alarmeEvents', $eqlogic->buildTabAlarmEvents($alarmEvent));
                    }
					//manage notification on events
					if ($eqlogic->getConfiguration('cfgSendMsg') === '1' and $eqlogic->getConfiguration('cfgCmdSendMsg') != '' ) {
						$notifCmd=cmd::byId(str_replace('#','',$eqlogic->getConfiguration('cfgCmdSendMsg')));
						if (is_object($notifCmd)) {
							$eqlogic->manageNotifications(TRUE,$notifCmd);
						} else {
						}
					}
            		$eqlogic->writeSeparateLine();
            		break;            
         	 	case 'refreshCameraSnapshot':
            		$eqlogic->writeSeparateLine();
            		log::add(__CLASS__, 'debug',  "Click on refresh camera snapshot");
            		$cameraSnapshot=$eqlogic->GetCamerasSnapshot();
            		if (isset($cameraSnapshot)) {
						log::add(__CLASS__, 'debug', " * MAJ cameraSnapshotBrute");
                      	$eqlogic->checkAndUpdateCmd('cameraSnapshotBrute', $cameraSnapshot);
						log::add(__CLASS__, 'debug', " * MAJ cameraSnapshot");
						$eqlogic->checkAndUpdateCmd('cameraSnapshot', $eqlogic->buildTabCamerasEvents($cameraSnapshot));
                    }
            		$eqlogic->writeSeparateLine();
                    break;
          		case 'actionScreenshot':
            		$eqlogic->writeSeparateLine();
            		log::add(__CLASS__, 'debug',  "  * Request title : ".$_options['title'] . " | message : " .$_options['message']);
            		if (isset($_options['message']) and isset($_options['title'])){
                      	if ($_options['title']=="get") {
	                      	return $eqlogic->getPictures($_options['message']);
                        } else if ($_options['title']=="delete"){
                          	$eqlogic->deletePictures($_options['message']);
                        }  else if ($_options['title']=="take"){
							return $eqlogic->takeSnapshot($_options['message']);
						}else {
                          	log::add(__CLASS__, 'debug',  "  * Request non prise en charge : ".$_options['title']);
                        }
                    } else {
                      	log::add(__CLASS__, 'debug',  "  * Request non complète => manque title ou message");
                    }
            		$eqlogic->writeSeparateLine();
            		break;

					//manage camera snapshot
		
        }

		if (strpos($logicalId, 'snapshot') !== false) {
			$aLogicalId=explode('_',$logicalId);
			$pk=$aLogicalId[2];
			$room=$aLogicalId[1];
			log::add(__CLASS__, 'debug',  "  * Request snapshot on  : ". $room . ' -> ' . $pk . '|Notification : ' . $eqlogic->getConfiguration('cfgAlertSnapshot'));
			$urlImg = $eqlogic->takeSnapshot($pk);
			$base64Img = $eqlogic->getPictures($urlImg);
			$eqlogic->checkAndUpdateCmd('cameraSnapshotImage', $base64Img);

			if ($eqlogic->getConfiguration('cfgAlertSnapshot') === '1' && isset($base64Img)) {
				$filePath=$eqlogic->buildFilePathImage($eqlogic->getId());
				log::add(__CLASS__, 'debug',  "  	* Save snapshot image to file system : " . $filePath);
				$eqlogic->saveImgToFileSystem($filePath,$base64Img);								
				
				$notifCmd=cmd::byId(str_replace('#','',$eqlogic->getConfiguration('cfgCmdSendMsg')));
				if (is_object($notifCmd)) {
					log::add(__CLASS__, 'debug',  "  	* Execute notification for sending snapshot image");
					$options = array('title' => $eqlogic->getConfiguration('cfgMsgTitle') . ' : demande d\'image pour ' . $room,'message' => '', 'files'=> array($filePath));
					$notifCmd->execCmd($options, $cache=0);
				}	
			}
			$eqlogic->writeSeparateLine();		
		}
	}

}
