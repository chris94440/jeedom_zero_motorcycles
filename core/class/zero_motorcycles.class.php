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
	const BASE_URL='https://mongol.brono.com/mongol/api.php?commandname=';
	  
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
						self::updateOrCreateCmd($zero_motorcycles);
                   }
                   
				}
			} catch (Exception $exc) {
				log::add(__CLASS__, 'error', __("Erreur lors de l'exécution du cron ", __FILE__) . $exc->getMessage());
			}
		}
	}

	public function synchronize() {
		self::writeSeparateLine();
		log::add(__CLASS__, 'debug', 'Start - ' . __FUNCTION__ . ' equipement Zero Motorcycles');
      	     
      	$userZero = self::getUserZero();
      	$pwdZero = self::getPwdZero();
      	
		list($httpcode, $result, $header) = self::doRequest(self::BASE_URL.'get_units&format=json&user='.$userZero.'&pass='.$pwdZero,null, "GET", null);	
      	if (isset($httpcode) and $httpcode >= 400 ) {
          	log::add(__CLASS__, 'debug', 'Start - ' . __FUNCTION__ . ' manageErrorMessage');
          	self::manageErrorMessage($httpcode,$result);
        } else {
          	self::createOrUpdateEquipement($result);
		}

      	log::add(__CLASS__, 'debug', 'End - ' . __FUNCTION__ . ' equipement Zero Motorcycles');
      	self::writeSeparateLine();
	}
  
  	private function createOrUpdateEquipement($responseBody) {
    	log::add(__CLASS__, 'debug', '	# Start - ' . __FUNCTION__ );
      	$resultArr=json_decode($responseBody,true);
      
      	$unitNumber='';
      	$unitName='';
        foreach($resultArr as $vehicule) {
        	if (array_key_exists('unitnumber', $vehicule)) {
              	$unitNumber=$vehicule['unitnumber'];
            }
          
          	if (array_key_exists('name', $vehicule)) {
              	$unitName=$vehicule['name'];
            }         
          
          	$found=false;
          	$eqLogics=eqLogic::byType(__CLASS__);
            foreach ($eqLogics as $eqLogic) {
                if ($eqLogic->getLogicalId() == $unitNumber) {
                   $found = true;
                }
            }
          
          	if (!$found) {
            	log::add(__CLASS__, 'debug', '   - create unitnumber ' . $unitName );
              	$eqLogic = new eqLogic();
                $eqLogic->setEqType_name(__CLASS__);
                $eqLogic->setIsEnable(1);
                $eqLogic->setIsVisible(1);
                $eqLogic->setName($unitName);
                $eqLogic->setConfiguration('rawDevice',json_encode($vehicule));
                $eqLogic->setLogicalId($unitNumber);
                $eqLogic->save();
            }
          
          	self::updateOrCreateCmd($eqLogic);
        }
      
      
      	log::add(__CLASS__, 'debug', '	# End - ' . __FUNCTION__ );
    }
  
  	private function updateOrCreateCmd($eqLogic) {
    	log::add(__CLASS__, 'debug', '		* Start - ' . __FUNCTION__ );
      
      	$userZero = self::getUserZero();
      	$pwdZero = self::getPwdZero();
     	
      	list($httpcode, $result, $header) = self::doRequest(self::BASE_URL.'get_last_transmit&format=json&user='.$userZero.'&pass='.$pwdZero.'&unitnumber='.$eqLogic->getLogicalId(),null, "GET", null);	
      	if (isset($httpcode) and $httpcode >= 400 ) {
          	log::add(__CLASS__, 'debug', 'Start - ' . __FUNCTION__ . ' manageErrorMessage');
          	self::manageErrorMessage($httpcode,$result);
        } else {
          	self::manageCmd($eqLogic,$result);
		}
      
      	log::add(__CLASS__, 'debug', '		* End - ' . __FUNCTION__ );
    }
  
  	private function manageCmd($eqLogic,$result) {
    	log::add(__CLASS__, 'debug', '			- Start - ' . __FUNCTION__ );
      	$resultArr=json_decode($result,true);
      	foreach($resultArr as $vehiculeDatas) {
          	foreach ($vehiculeDatas as $key => $value) {
                self::commonCreateCmd($eqLogic,$key,$value);
            }
        }
      	
      	log::add(__CLASS__, 'debug', '			- End - ' . __FUNCTION__ );
    }
  
  	private function commonCreateCmd($eqLogic,$name,$value) {
      	log::add(__CLASS__, 'debug', '			- ' . __FUNCTION__ . ' name : ' . $name . ' -> ' . $value);
    	$zero_motorcyclesCmd = $eqLogic->getCmd(null, $name.'_'.$eqLogic->getLogicalId());
		if (! is_object($zero_motorcyclesCmd)) {
			$zero_motorcyclesCmd = new zero_motorcyclesCmd();
			$zero_motorcyclesCmd->setName($name);
			$zero_motorcyclesCmd->setEqLogic_id($eqLogic->id);
			$zero_motorcyclesCmd->setLogicalId($name.'_'.$eqLogic->getLogicalId());
			$zero_motorcyclesCmd->setType('info');
          	if ($name == 'mileage' || $name == 'longitude' || $name == 'latitude' || $name == 'altitude' || $name == 'soc' || $name == 'charging' || $name == 'chargecomplete' || $name == 'pluggedin') {
              	$zero_motorcyclesCmd->setSubType('numeric');
            	$zero_motorcyclesCmd->setIsHistorized(1);
            } else {
            	$zero_motorcyclesCmd->setSubType('string');
            }
			
			$zero_motorcyclesCmd->setOrder(sizeof($eqLogic->getCmd()));
        }
      	
		$zero_motorcyclesCmd->save();
      	$zero_motorcyclesCmd->event($value);
    }
  
  
  	private function getUserZero() {
      if (empty(trim(str_replace('"', '\"', config::byKey('login_zero_motorcycles', __CLASS__))))) {
        log::add(__CLASS__, 'error', 'L\'identifiant ne peut pas être vide');
      }
      return trim(str_replace('"', '\"', config::byKey('login_zero_motorcycles', __CLASS__)));
    }
  
  	private function getPwdZero() {
      if (empty(trim(str_replace('"', '\"', config::byKey('password_zero_motorcycles', __CLASS__))))) {
        log::add(__CLASS__, 'error', 'Le mot de passe ne peut pas être vide');
      }
      return trim(str_replace('"', '\"', config::byKey('password_zero_motorcycles', __CLASS__)));
    }

	private function doRequest($url, $data, $method, $headers) {		
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
			self::manageErrorMessage('500','Request was rejected by server -> '.$url);
		} else {
			if (self::isJson($body)) {
				log::add(__CLASS__, 'debug', "					# Body  : ".$body);
			}
		}
		log::add(__CLASS__, 'debug', "					# Header  : ".$header);
	  
	  return array($httpRespCode, $body, $header);
  	}
  
  	private function isJson($inputJson) {
	   json_decode($inputJson);
	   return json_last_error() === JSON_ERROR_NONE;
	}

	private function manageErrorMessage($httpCode,$error) {
		log::add(__CLASS__, 'debug', "			" . __FUNCTION__ . " : " . $error . "|" .$httpCode);
		$errorMessage="Unknown error";
		if (!self::IsNullOrEmpty($error)) {
          	log::add(__CLASS__, 'debug', "1");
			$errorMessage=str_replace("\"","",$error);
            $errorArray=json_decode($error,true);
            if (!self::IsNullOrEmpty($errorArray["error"])) {
              log::add(__CLASS__, 'debug', "2");
              $errorMsg=json_decode($errorArray,true);
              if (!self::IsNullOrEmpty($errorMsg["code"]) and !self::IsNullOrEmpty($errorMsg["message"]) ) {
                log::add(__CLASS__, 'debug', "3");
                $errorMsgCode=$errorMsg["code"];
                $errorMsgMessage=$errorMsg["message"];
                $errorMessage=$errorMsg["message"] .' - '. $errorMsgCode;
                log::add(__CLASS__, 'debug', "				==> decode json  : " . $errorMsgCode . "|" . $errorMsgMessage);
              } else {
                log::add(__CLASS__, 'debug', "4");
                $errorMsgCode=$httpCode;
                $errorMsgMessage=$errorArray["error"];
                $errorMessage=$errorMsgMessage .' - '. $errorMsgCode;
              }
            }
		}
	    
      	log::add(__CLASS__, 'error', $errorMessage);
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
  	/*
    public static function cronHourly() {
      log::add(__CLASS__, 'debug', 'Exécution du cron hourly zero_motorcycles - Start');
      foreach (eqLogic::byType(__CLASS__, true) as $zero_motorcycles) {
        $zero_motorcycles->writeSeparateLine();
        
        $zero_motorcycles->writeSeparateLine();
      }
      log::add(__CLASS__, 'debug', 'Exécution du cron hourly zero_motorcycles - End');
    }
    */

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {        
    }

    public function postInsert() {        
    }

    public function preSave() {        
    }

    public function postSave() {
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

    private function manageErrorAPI($function,$errorMessage) {
          $message="$function => ".$errorMessage;
          throw new Exception($message);
    }

    private function writeSeparateLine(){
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