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

  	private function fmt_date($timeStamp) {
		setlocale(LC_TIME, 'fr_FR.utf8','fra');
		return(ucwords(strftime("%a %d %b %T",$timeStamp)));
	}
  
    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom    */
	public static function cron() {
		$autorefresh = config::byKey('autorefresh', 'zero_motorcycles');
		if ($autorefresh != '') {
			try {
                $c = new Cron\CronExpression(checkAndFixCron($autorefresh), new Cron\FieldFactory);
                if ($c->isDue()) {
                    log::add('zero_motorcycles', 'debug', 'Exécution du cron Ima Protect');
                  	
		            foreach (eqLogic::byType('zero_motorcycles', true) as $zero_motorcycles) {
                      	
                      	$bEventsRefreshed=(bool)FALSE;
						$newValue=$zero_motorcycles->GetAlarmState();                    	
                        $zero_motorcycles->writeSeparateLine();
                   }
                   
				}
			} catch (Exception $exc) {
				log::add('zero_motorcycles', 'error', __("Erreur lors de l'exécution du cron ", __FILE__) . $exc->getMessage());
			}
		}
	}
  
  	private function initCache() {
      	//cache::set('zero_motorcycles::alarmStatus::'.$this->getId(),1637308809, 0);
      	if (((cache::byKey('zero_motorcycles::alarmStatus::'.$this->getId()))->getValue(microtime(true)) === '') || ((cache::byKey('zero_motorcycles::alarmStatus::'.$this->getId()))->getValue(microtime(true)) > time())) {
          	cache::set('zero_motorcycles::alarmStatus::'.$this->getId(),time(), 0);
        }
      
      	if (((cache::byKey('zero_motorcycles::alarmIntrusion::'.$this->getId()))->getValue(microtime(true)) === '') || ((cache::byKey('zero_motorcycles::alarmIntrusion::'.$this->getId()))->getValue(microtime(true)) > time())) {
          	cache::set('zero_motorcycles::alarmIntrusion::'.$this->getId(),time(), 0);
        }
      
      	if (((cache::byKey('zero_motorcycles::alarmOpenedDoor::'.$this->getId()))->getValue(microtime(true)) === '') || ((cache::byKey('zero_motorcycles::alarmOpenedDoor::'.$this->getId()))->getValue(microtime(true)) > time())) {
          	cache::set('zero_motorcycles::alarmOpenedDoor::'.$this->getId(),time(), 0);
        }
    }
  
  	public function manageNotifications($bEventsRefreshed,$notifCmd) {
            
      try {
        $this->initCache();
        
        if ($bEventsRefreshed == FALSE) {
          	$this->getCmd(null, 'refreshAlarmEvents')->execCmd();
        } else {
			log::add('zero_motorcycles', 'debug',  "  ". __FUNCTION__ ." Start");

			$eventResponse=$this->getCmd(null, 'alarmeEventsBrute')->execCmd();
			if ($this->getConfiguration('cfgAlertChangeStatus') === '1') {
              		$this->checkActivity('activ','alarmStatus',$eventResponse,$notifCmd);
			}

			if ($this->getConfiguration('cfgAlertIntrusion') === '1') {
              	  $this->checkActivity('intrusion','alarmIntrusion',$eventResponse,$notifCmd);
			}

			if ($this->getConfiguration('cfgAlertOpenedDoor') === '1') {
              		$this->checkActivity('ouverture','alarmOpenedDoor',$eventResponse,$notifCmd);
			}
			log::add('zero_motorcycles', 'debug',  "  ". __FUNCTION__ ." End");
		}

      } catch (Exception $e) {
          $this->manageErrorAPI("GetAlarmState",$e->getMessage());
      }
    }
  
  	private function checkActivity($activity,$cacheName,$eventResponse,$notifCmd) {
		$response = $this->getLastEvent($eventResponse,$activity);
        log::add('zero_motorcycles', 'debug', "			-> response event " . $activity  ." ". json_encode($response));
        $cache=(cache::byKey('zero_motorcycles::'. $cacheName .'::'.$this->getId()))->getValue(false);
      	log::add('zero_motorcycles', 'debug', "				=> cache value for " . $cacheName  ." ". $cache);
      	log::add('zero_motorcycles', 'debug', "				=> timestamp response  " . $response["timestamp"]);

        if (!(is_null($response)) && $response["timestamp"] != '' and ($response["timestamp"] > $cache)) {
          log::add('zero_motorcycles', 'debug', '					-> send notif for ' . $activity);
          cache::set('zero_motorcycles::'.$cacheName.'::'.$this->getId(),$response["timestamp"], 0);
          
          switch ($cacheName) {
              case 'alarmStatus':
                  $options = array('title' => $this->getConfiguration('cfgMsgTitle'), 'message'=>$response["event"] .' par ' . $response["detailEvent"]);
                  break;
              case 'alarmOpenedDoor':
                  $options = array('title' => $this->getConfiguration('cfgMsgTitle'), 'message'=>$response["event"] .' -> ' . $response["detailEvent"]);
                  break;
              case 'alarmIntrusion':
                  $options = array('title' => $this->getConfiguration('cfgMsgTitle'), 'message'=>$response["event"]);
				  $this->checkAndUpdateCmd('alarmState', 1);
                  break;
          }
          
          $notifCmd->execCmd($options, $cache=0);
        } else {
			if ($cacheName == 'alarmIntrusion') {
				$this->checkAndUpdateCmd('alarmState', 0);
			}
		}
    }
  
  	private static function getLastEvent($eventResponse,$eventType) {
      
        $resultArr=json_decode($eventResponse,true);

        foreach($resultArr as $journalK=>$journalV) {
          foreach($journalV as $eventDateK=>$eventDateV){
			if ($eventDateK != 'error') {
				foreach($eventDateV as $eventDetailK=>$eventDetailV){
					if (array_key_exists('title', $eventDetailV['fields'])) {
						$event=str_replace("'"," ",$eventDetailV['fields']['title']);

						if (self::stringContains($eventType,$event)) {
							$mefDate=self::mefDateTime($eventDetailV['fields']['creationDatetime']);
							return array("date" => $mefDate, "timestamp"=> strtotime($mefDate),"event" => $event, "detailEvent" => str_replace("'"," ",$eventDetailV['fields']['subtitle']));
						}
					}
				            


				}
			}
          }	
        }
		
		return NULL;
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


     /* Fonction exécutée automatiquement toutes les heures par Jeedom */
    public static function cronHourly() {
      log::add('zero_motorcycles', 'debug', 'Exécution du cron hourly Alarme IMA - Start');
      foreach (eqLogic::byType('zero_motorcycles', true) as $zero_motorcycles) {
        $zero_motorcycles->writeSeparateLine();
        $zero_motorcycles->getCmd(null, 'refreshAlarmEvents')->execCmd();
        $zero_motorcycles->getCmd(null, 'refreshCameraSnapshot')->execCmd();
        $zero_motorcycles->writeSeparateLine();
      }
      log::add('zero_motorcycles', 'debug', 'Exécution du cron hourly Alarme IMA - End');
    }

    /* Fonction exécutée automatiquement tous les jours par Jeedom */
      public static function cronDayly() {
		log::add('zero_motorcycles', 'debug', 'Exécution du cron daily Alarme IMA - Start');
		log::add('zero_motorcycles', 'debug', '	* Suppression snapshot de plus de 10J');
		
		//10 days
		$threshold = 864000;
		$nbDelete=0;
		
		foreach (eqLogic::byType('zero_motorcycles', true) as $zero_motorcycles) {
			$folder=new DirectoryIterator(self::SNAPSHOT_PATH. $zero_motorcycles->getId());
			log::add('zero_motorcycles', 'debug',' 	  - équipement Ima Protec traité : ' . $zero_motorcycles->getId());
			log::add('zero_motorcycles', 'debug',' 		-> esapce utilisé avant purge : ' . shell_exec('du -sh '. self::SNAPSHOT_PATH. $zero_motorcycles->getId()));
			foreach($folder as $file) {
			  try {			
				if($file->isFile() && !$file->isDot() && (time() - $file->getMTime() > $delta)) {
					log::add('zero_motorcycles', 'debug',"			- File : " . self::SNAPSHOT_PATH. $zero_motorcycles->getId().'/'.$file->getFilename()  . '| creation date : ' .  $file->getMTime() . '	==> deleted');
					unlink(self::SNAPSHOT_PATH. $zero_motorcycles->getId().'/'.$file->getFilename());
					$nbDelete++;
				}
			  } catch (Exception $e) {
				$zero_motorcycles->manageErrorAPI("cronDayly",'Error on deleteFile function on a file : ' .  $e->getMessage());			  
			  }
			}
			log::add('zero_motorcycles', 'debug', ' 		-> Nb fichiers purgés : ' . $nbDelete);
		}

		log::add('zero_motorcycles', 'debug', 'Exécution du cron daily Alarme IMA - End');
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
      log::add('zero_motorcycles', 'debug',  "Création des commandes : start");

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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$zero_motorcyclesCmd->getName().' (LogicalId : '.$zero_motorcyclesCmd->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdCameraSnapshot->getName().' (LogicalId : '.$cmdCameraSnapshot->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdCameraSnapshotBrute->getName().' (LogicalId : '.$cmdCameraSnapshotBrute->getLogicalId().')');
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
			log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdRefreshAlarmStatus->getName().' (LogicalId : '.$cmdRefreshAlarmStatus->getLogicalId().')');
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
			log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdRefreshCameraSnapshot->getName().' (LogicalId : '.$cmdRefreshCameraSnapshot->getLogicalId().')');
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
			log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdRefreshEventsAlarm->getName().' (LogicalId : '.$cmdRefreshEventsAlarm->getLogicalId().')');
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
          log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdActionModeAlarme->getName().' (LogicalId : '.$cmdActionModeAlarme->getLogicalId().')');
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
			log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdActionScreenshot->getName().' (LogicalId : '.$cmdActionScreenshot->getLogicalId().')');
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
          	log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
        }
		$cmd->save();

		log::add('zero_motorcycles', 'debug',  "Création des commandes - End");
    }

    public function preUpdate() {
		log::add('zero_motorcycles', 'debug',  "appel preUpdate");
   		if (empty($this->getConfiguration('login_ima'))) {
			throw new Exception(__('L\'identifiant ne peut pas être vide',__FILE__));
		}

		if (empty($this->getConfiguration('password_ima'))) {
			throw new Exception(__('Le mot de passe ne peut etre vide',__FILE__));
		}
      
      
    }

    public function postUpdate() {
      	$this->createCmd();
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin */
//    public function toHtml($_version = 'dashboard') {
//		$ret=parent::toHtml();
//        log::add('zero_motorcycles', 'debug', "ceci".$ret);
//		return $ret;
//      }

    /*
     * Non obligatoire mais ca permet de déclancher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclancher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
  public function GetAlarmState()	{
  	log::add('zero_motorcycles', 'debug',  "  GetAlarmState Start");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
    try {
      	$myImaProtectAlarm = $this->getInstanceIMAApi();
		log::add('zero_motorcycles', 'debug',  "	* Recuperation statut de l'alarme");
		
		$alarmeStatus = $myImaProtectAlarm->getAlarmStatus();
      
      	if (!isset($alarmeStatus)) {
			$oldValue=$this->getCmd(null, 'statusAlarme')->execCmd();
            log::add('zero_motorcycles', 'error', "	    - Impossible de trouver le status, on conserve la valeur précédente : " . $oldValue);
            return $oldValue;
        }

        $convStatusToNumeric=array(
          "on" => "2",
          "partial" => "1",
          "off"=> "0"
        );
		
		$convStatusToFrenchStatus=array(
          "on" => "Total",
          "partial" => "Partiel",
          "off"=> "Désactivée"
        );

        $numericStatus=$convStatusToNumeric[$alarmeStatus];
        log::add('zero_motorcycles', 'debug', "	    - Nouveau status numerique alarme: $numericStatus | $alarmeStatus");	
		
		$oldValue=$this->getCmd(null, 'statusAlarme')->execCmd();	
      
      
        $bEventsRefreshed=(bool)FALSE;              
		if (isset($numericStatus) and $numericStatus!=self::IMA_IGNORED)  {
			if (isset($oldValue) && (is_numeric($oldValue) OR $oldValue =='')) {
			  if (strcmp($oldValue,$numericStatus) > 0 OR  strcmp($oldValue,$numericStatus) < 0) {
				log::add('zero_motorcycles', 'debug',  " Le statut de l alarme a change (old|new): $oldValue | $numericStatus");
				$this->checkAndUpdateCmd('statusAlarme', $numericStatus);		
				$this->checkAndUpdateCmd('binaryAlarmStatus',($numericStatus > 0 ? 1:0));
				$this->checkAndUpdateCmd('alarmMode',$convStatusToFrenchStatus[$alarmeStatus]);
				
				$this->getCmd(null, 'refreshAlarmEvents')->execCmd();
				$bEventsRefreshed=(bool)TRUE;
			  } else {
				log::add('zero_motorcycles', 'debug',  " Le statut de l'alarme n'a pas changé (old|new): $oldValue | $numericStatus");
			  }
			}
		} else {
			log::add('zero_motorcycles', 'debug', "Retour ignoré");
		}
		
		if ($this->getConfiguration('cfgSendMsg') === '1' and $this->getConfiguration('cfgCmdSendMsg') != '' ) {
			$notifCmd=cmd::byId(str_replace('#','',$this->getConfiguration('cfgCmdSendMsg')));
			if (is_object($notifCmd)) {
				$this->manageNotifications($bEventsRefreshed,$notifCmd);
			}
		}
		
        log::add('zero_motorcycles', 'debug',  "  GetAlarmState End");	
    } catch (Exception $e) {
      	$this->manageErrorAPI("GetAlarmState",$e->getMessage());
    }
  }
 
  public function GetAlarmEvents()	{
  	log::add('zero_motorcycles', 'debug',  "  GetAlarmEvents Start");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try{
		$myImaProtectAlarm = $this->getInstanceIMAApi();
		log::add('zero_motorcycles', 'debug',  "	* Recover alarm events");
		$alarmEvent = $myImaProtectAlarm->getAlarmEvent();
        log::add('zero_motorcycles', 'debug',  "  GetAlarmEvents End");
		return $alarmEvent;
	} catch (Exception $e) {
		$this->manageErrorAPI("GetAlarmEvents",$e->getMessage());
	}
  }
  
  public function GetCamerasSnapshot()	{
  	log::add('zero_motorcycles', 'debug',  "  GetCamerasSnapshot Start");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try {
		$myImaProtectAlarm = $this->getInstanceIMAApi();
		log::add('zero_motorcycles', 'debug',  "	* Recover alarm events");
		$cameraEvents = $myImaProtectAlarm->getCamerasSnapshot();
		log::add('zero_motorcycles', 'debug',  "  GetCamerasSnapshot End");
      	return $cameraEvents;
       
	} catch (Exception $e) {
		$this->manageErrorAPI("GetCamerasSnapshot",$e->getMessage());
	}
  }

	private function getLastPictureTaken($cameraEvents) {
		log::add('zero_motorcycles', 'debug',  "		* getLastPictureTaken - Start : ".$cameraEvents);
		$response='';
    	$resultArr=json_decode($cameraEvents,true);
        foreach($resultArr as $event) {        
          foreach($event['images'] as $img) {
            	if (!$this->IsNullOrEmpty($img)) {
					$response=$img;
					break;
                }
          }
		  
			if (!$this->IsNullOrEmpty($response)) {
				break;
			}
        }
		log::add('zero_motorcycles', 'debug',  "		* getLastPictureTaken - response : ".$response);
		return $response;
	}
	
  public function buildTabCamerasEvents($cameraEvents){
    	log::add('zero_motorcycles', 'debug',  "		* buildTabCamerasEvents - Start : ".$cameraEvents);
    	$resultArr=json_decode($cameraEvents,true);
    
    	$cameraEventTab  = "<div class=\"tableWrap\">";
		$cameraEventTab .= "<table>";
		$cameraEventTab .= "<thead>";
		$cameraEventTab .= "<tr>";
    	$cameraEventTab .= "<th></th>";
		$cameraEventTab .= "<th>Date</th>";
		$cameraEventTab .= "<th>Etat</th>";
		$cameraEventTab .= "<th>Elément</th>";
		$cameraEventTab .= "<th>Photos</th>";
		$cameraEventTab .= "</tr>";
		$cameraEventTab .= "</thead>";
		$cameraEventTab .= "<tbody>";
    

        foreach($resultArr as $event) {
          $date=$event['date'];
          $etat=$event['type'];
          $element=$event['name'];
          $photos="";
          $pk=$event['pk'];
          $item=0;
          
          foreach($event['images'] as $img) {
            	if (!$this->IsNullOrEmpty($img)) {
                  if ($item > 0) {
                    $photos.=',' . $img;
                  } else {	
                    $photos=$img;
                  }
                  $item++;
                }
          }

		  
          $cameraEventTab .=  "<tr>";
		  $cameraEventTab .= "<td><i class=\"fa fa-trash\" aria-hidden=\"true\" onclick=deletePicture(\"";
		  $cameraEventTab .= $pk;
		  $cameraEventTab .= "\")></i></td>";
          $cameraEventTab .=  "<td>$date</td>";
          $cameraEventTab .=  "<td>$etat</td>";
          $cameraEventTab .=  "<td>$element</td>";
          $cameraEventTab .=  "<td>";
		  $cameraEventTab .=  "<a class=\"zoom\" href=\"#\" onclick=getPicture(\"";
		  $cameraEventTab .= $photos;
		  $cameraEventTab .=  "\") data-eqLogic_id=\"#id#\">";
          
          if ($item > 1) {
            $cameraEventTab .=  " photos</a>";
          } else {
            $cameraEventTab .=  " photo</a>";
          }

          
          $cameraEventTab .=  "</td>";
          $cameraEventTab .=  "</tr>";  
        }

          $cameraEventTab .=  "</tbody>";
          $cameraEventTab .=  "</table>";
          $cameraEventTab .=  "</div>";
          //log::add('zero_motorcycles', 'debug',  "		* buildTabCamerasEvents- End => $cameraEventTab");
		  log::add('zero_motorcycles', 'debug',  "		* buildTabCamerasEvents- End");
          return $cameraEventTab;
  }


  public function buildTabAlarmEvents($alarmEvents){
    	log::add('zero_motorcycles', 'debug',  "		* build alarm events - Start");
    	$resultArr=json_decode($alarmEvents,true);

    	$alarmeEventTab ="<div class=\"tableWrap\">";
		$alarmeEventTab .= "<table>";
		$alarmeEventTab .= "<thead>";
		$alarmeEventTab .= "<tr>";
		$alarmeEventTab .= "<th></th>";
		$alarmeEventTab .= "<th>Date</th>";
		$alarmeEventTab .= "<th>Evènement</th>";
		$alarmeEventTab .= "<th>Détail</th>";
		$alarmeEventTab .= "</tr>";
		$alarmeEventTab .= "</thead>";
		$alarmeEventTab .= "<tbody>";

		$journal=$resultArr['journal'];
		foreach($journal as $eventDateK=>$eventDateV){
			if ($eventDateK != 'error') {				
				foreach($eventDateV as $eventDetailK=>$eventDetailV){
					if (array_key_exists('title', $eventDetailV['fields'])) {
						$event='';
						$icon='';
						$mefDate='';
						$detail='';
						if (array_key_exists('title', $eventDetailV['fields'])) {
							$event=str_replace("'"," ",$eventDetailV['fields']['title']);
						}

						if (array_key_exists('subtitle', $eventDetailV['fields'])) {
							$detail=str_replace("'"," ",$eventDetailV['fields']['subtitle']);
						}

						if (array_key_exists('icon', $eventDetailV['fields'])) {
							$icon=self::storeAndBuildIcon($eventDetailV['fields']['icon']);
						}

						if (array_key_exists('creationDatetime', $eventDetailV['fields'])) {
							$mefDate=self::mefDateTime($eventDetailV['fields']['creationDatetime']);
						}
					}				
					$alarmeEventTab .=  "<tr>";
					$alarmeEventTab .=  "<td><img src=\"$icon\" alt=\"\" style=\"width: 30px\"/</td>";
					$alarmeEventTab .=  "<td>$mefDate</td>";
					$alarmeEventTab .=  "<td>$event</td>";
					$alarmeEventTab .=  "<td>$detail</td>";
					$alarmeEventTab .=  "</tr>"; 
				}
			} else {
				log::add('zero_motorcycles', 'debug',  "			* error in response of events journal : " . $eventDateK . ' -> ' . json_encode($eventDateV));
			}
		}	
        $alarmeEventTab .=  "</tbody>";
		$alarmeEventTab .=  "</table>";
    	$alarmeEventTab .=  "</div>";

		log::add('zero_motorcycles', 'debug',  "		* build alarm events tab - End");
    	return $alarmeEventTab;
  }

  private function storeAndBuildIcon($urlImg) {
	if (!file_exists(self::ICON_PATH)) {
		mkdir(self::ICON_PATH, 0777, true);
	}

	$mefImdName=str_replace(array('https://pilotageadistance.imateleassistance.com/proxy/static/hss/events_v3/'),array(''),$urlImg);
	file_put_contents(self::ICON_PATH.$mefImdName, file_get_contents($urlImg));

	return network::getNetworkAccess().self::ACCESS_ICON_PATH.$mefImdName;
	
  }

  private function mefDateTime($dateTime) {
	try{
		$date=new DateTime($dateTime);
		return $date->format('Y-m-d H:i:s');
	} catch (Exception $e) {
		log::add('zero_motorcycles', 'error',  "  Error on DateTime conversion -> " . $e->getMessage() . '('.$dateTime.')');
		//force actual date
		$date = new DateTime();
		return $date->format('Y-m-d H:i:s');
	}
  }

	public function removeDatasSession($input) {
		log::add('zero_motorcycles', 'debug',  __FUNCTION__ .' - id : ' . $input );
		config::remove('imaToken_session_'.$input,'zero_motorcycles');
	}
  
  public function getContactList($input){   
    log::add('zero_motorcycles', 'debug',  "  getContactList Start : " . $input);
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
    $response='';
	try {
      	$eqlogic = eqLogic::byId($input);
      	$imaProtectAPI = new imaProtectNewAPI($eqlogic->getConfiguration('login_ima'),$eqlogic->getConfiguration('password_ima'),$eqlogic->getConfiguration('cfgContactList'),$input,$eqlogic->getConfiguration('checkPwdXO'));
		
		if (!($imaProtectAPI->getDatasSession())) {
			log::add('zero_motorcycles', 'debug',  "	* Validation couple user / mdp");
			$imaProtectAPI->login();
			log::add('zero_motorcycles', 'debug',  "	* Recuperation information compte IMA Protect");
			$imaProtectAPI->getTokens();
		}
      	
      	log::add('zero_motorcycles', 'debug',  "	* Call backend getContactList()");
      	$response =  $imaProtectAPI->getContactList();
	} catch (Exception $e) {
	  $this->manageErrorAPI("getContactList",$e->getMessage());
	}
    log::add('zero_motorcycles', 'debug',  "  getContactList End : ");
    return $response;
  }
  
  
  public function setAlarmToOff($pwd){   
    log::add('zero_motorcycles', 'debug',  "  SetAlarmToOff Start");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try {
		$myImaProtectAlarm = $this->getInstanceIMAApi();
	    log::add('zero_motorcycles', 'debug',  "	* Extinction alarme");

		$checkPwdXO=$this->getConfiguration('checkPwdXO');
		if ($checkPwdXO == '1' && empty($pwd)) {
			$this->manageErrorAPI('setAlarmToOff','Le code XO est nécessaire pour désarmer l\'alarme');		
		}
	
		$myImaProtectAlarm->setAlarmToOff($pwd);
	} catch (Exception $e) {
	  $this->manageErrorAPI("setAlarmToOff",$e->getMessage());
	}
    log::add('zero_motorcycles', 'debug',  "  SetAlarmToOff End");
  }
  
  public function setAlarmToOn(){   
    log::add('zero_motorcycles', 'debug',  "  setAlarmToOn Start");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try{
		$myImaProtectAlarm = $this->getInstanceIMAApi();
	    log::add('zero_motorcycles', 'debug',  "	* Mise en route alarme");
		$myImaProtectAlarm->setAlarmToOn();
	} catch (Exception $e) {
	  $this->manageErrorAPI("setAlarmToOff",$e->getMessage());
	}
    log::add('zero_motorcycles', 'debug',  "  setAlarmToOn End");
  }
  
  public function setAlarmToPartial(){   
    log::add('zero_motorcycles', 'debug',  "  setAlarmToPartial Start");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try{
		$myImaProtectAlarm = $this->getInstanceIMAApi();
	    log::add('zero_motorcycles', 'debug',  "	* Mise en route alarme");
		$myImaProtectAlarm->setAlarmToPartial();
	} catch (Exception $e) {
	  $this->manageErrorAPI("setAlarmToOff",$e->getMessage());
	}
    log::add('zero_motorcycles', 'debug',  "  setAlarmToPartial End");
  }
  
  public function getPictures($pictureUrl){   
	
    log::add('zero_motorcycles', 'debug',  "  getPictures Start => $pictureUrl");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try {
		$myImaProtectAlarm = $this->getInstanceIMAApi();
		$byteArray=$myImaProtectAlarm->getPictures($pictureUrl);
	    if (isset($byteArray)) {
			$str=base64_encode($byteArray);
			return base64_encode($byteArray);
		} else {
			$this->manageErrorAPI("getPictures","Empty byte array recover");
		}
	} catch (Exception $e) {
      	$this->manageErrorAPI("getPictures",$e->getMessage());
    } 
  }
  
  public function deletePictures($picture){   
    log::add('zero_motorcycles', 'debug',  "  deletePictures Start => $picture");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try {
		$myImaProtectAlarm = $this->getInstanceIMAApi();
		$result=$myImaProtectAlarm->deletePictures($picture);
		$cameraSnapshot=$this->GetCamerasSnapshot();
		$this->checkAndUpdateCmd('cameraSnapshot', $this->buildTabCamerasEvents($cameraSnapshot));
	} catch (Exception $e) {
      	$this->manageErrorAPI("getPictures",$e->getMessage());
    } 
    log::add('zero_motorcycles', 'debug',  "  deletePictures End");
  }
  

  public function takeSnapshot($roomId) {
	log::add('zero_motorcycles', 'debug',  "  takeSnapshot Start => $roomId");
  	log::add('zero_motorcycles', 'debug',  "	* instanciation api ima protect");
	try {
		$myImaProtectAlarm = $this->getInstanceIMAApi();
		$result=$myImaProtectAlarm->takeSnapshot($roomId);
		log::add('zero_motorcycles', 'debug',  "	* pause of 20s in order to be able to retrieve new snapshot");
		sleep(20);
		$cameraSnapshot=$this->GetCamerasSnapshot();
		if (isset($cameraSnapshot)) {
			log::add('zero_motorcycles', 'debug', " * MAJ cameraSnapshotBrute");
			$this->checkAndUpdateCmd('cameraSnapshotBrute', $cameraSnapshot);
			log::add('zero_motorcycles', 'debug', " * MAJ cameraSnapshot");
			$this->checkAndUpdateCmd('cameraSnapshot', $this->buildTabCamerasEvents($cameraSnapshot));
			return $this->getLastPictureTaken($cameraSnapshot);
		}
      	return '';
	} catch (Exception $e) {
      	$this->manageErrorAPI("takeSnapshot",$e->getMessage());
    } 
    log::add('zero_motorcycles', 'debug',  "  takeSnapshot End");
  }

  
  private function getInstanceIMAApi(){
    try {
      	$imaProtectAPI = new imaProtectNewAPI($this->getConfiguration('login_ima'),$this->getConfiguration('password_ima'),$this->getConfiguration('cfgContactList'),$this->getId(),$this->getConfiguration('checkPwdXO'));
				
		if (!($imaProtectAPI->getDatasSession())) {
			log::add('zero_motorcycles', 'debug',  "	* Validation couple user / mdp");
			$imaProtectAPI->login();
			log::add('zero_motorcycles', 'debug',  "	* Recuperation token IMA Protect");
			$imaProtectAPI->getTokens();
			log::add('zero_motorcycles', 'debug',  "	* Recuperation informations sur les caméras IMA Protect");
			$imaProtectAPI->getOtherInfo();
			$this->setRoomsList($imaProtectAPI);
		}
      	return $imaProtectAPI;
    } catch (Exception $e) {
      	$this->manageErrorAPI("getInstanceIMAApi",$e->getMessage());
    }
  }
  

  private function setRoomsList($imaProtectAPI){
		log::add('zero_motorcycles', 'debug',  "	* setRoomsList Start : ". json_encode($imaProtectAPI->rooms));
		$cmdActionScreenshot = $this->getCmd(null, 'actionScreenshot');
		if (is_object($cmdActionScreenshot)) {
          	$listValue='';
            $placeholderMessage='';
			$roomsList=$imaProtectAPI->rooms;
			for ($i = 0; $i < count($roomsList); $i++) {
              	if (!empty($roomsList[$i]["room"])){
                  if (!$this->IsNullOrEmpty($listValue)) {
                  		$listValue.= ";";
                        $placeholderMessage.=',';
                  }
                  $listValue.= $roomsList[$i]["pk"] . "|" . $roomsList[$i]["room"];
                  $placeholderMessage.=$roomsList[$i]["room"];

				  //create cmd for camera snapshot
					$this->createCmdActionOther('Snapshot camera '.$roomsList[$i]["room"],$roomsList[$i]["room"],$roomsList[$i]["pk"]);
                }

				
			}
			
			if ($listValue != '') {
				$cmdActionScreenshot->setConfiguration('listValue', $listValue);
              	$cmdActionScreenshot->setDisplay('title_possibility_list','get, delete, take');
                $cmdActionScreenshot->setDisplay('message_placeholder',$placeholderMessage);
				$cmdActionScreenshot->save();
			}
		}
	  log::add('zero_motorcycles', 'debug',  "	* setRoomsList End");
  }

  private function createCmdActionOther($cmdName,$room,$pk) {
	log::add('zero_motorcycles', 'debug',  '	* createCmdActionOther : ' . $cmdName . '|' .  $room . '|' . $pk . ' for id :' . $this->getId());
	$cmdActionOther = $this->getCmd(null, 'snapshot_'.$room.'_'.$pk);
	if (!is_object($cmdActionOther)) {
		$cmdActionOther = new zero_motorcyclesCmd();
		$cmdActionOther->setName('Snapshot camera '.$room);
		$cmdActionOther->setEqLogic_id($this->getId());
		$cmdActionOther->setLogicalId('snapshot_'.$room.'_'.$pk);
		$cmdActionOther->setType('action');
		$cmdActionOther->setSubType('other');
		$cmdActionOther->setTemplate('dashboard', 'default');
		$cmdActionOther->setTemplate('mobile','default');
		$cmdActionOther->setOrder($this->getLastindexCmd());
		log::add('zero_motorcycles', 'debug', 'Création de la commande '.$cmdActionOther->getName().' (LogicalId : '.$cmdActionOther->getLogicalId().')');
		$cmdActionOther->save();
	}
  }

  private function getLastindexCmd() {		
	return sizeof($this->getCmd());
  }


  
  private function IsNullOrEmpty($input){
    return (!isset($input) || trim($input)==='');
  }
  
  public function manageErrorAPI($function,$errorMessage) {
    	$message="$function => ".$errorMessage;
    	throw new Exception($message);
  }
  
  public function writeSeparateLine(){
		log::add('zero_motorcycles', 'debug',  "*********************************************************************");
  }

  public function buildFilePathImage($id) {
	log::add('zero_motorcycles', 'debug',  "	* " . __FUNCTION__ );
	$date = new DateTime();
	$dateMef=$date->format('Y-m-d H:i:s');
	$dateMefPicture=$date->format('Y-m-d_H_i_s');

	if (!file_exists(self::SNAPSHOT_PATH.$id)) {
		mkdir(self::SNAPSHOT_PATH.$id, 0777, true);
	}

	return self::SNAPSHOT_PATH.$id.'/snap_zero_motorcycles_' .$dateMefPicture .'.jpg';
  }

  public function saveImgToFileSystem($filePath,$base64Image) {
	log::add('zero_motorcycles', 'debug',  "	* " . __FUNCTION__ .' |file path : ' . $filePath);
	$imageData = base64_decode($base64Image);
	$source = imagecreatefromstring($imageData);
	$imageSave = imagejpeg($source,$filePath,100);
	imagedestroy($source);
  }
  
	public function toHtml($_version = 'dashboard') {
	  log::add('zero_motorcycles', 'debug',  "Function toHtml - Start");
	  
	  $replace = $this->preToHtml($_version);
	  log::add('zero_motorcycles', 'debug',  "Function toHtml - replace avant remplacement : $replace");
	  //$replace=array();
	  log::add('zero_motorcycles', 'debug',  "Function toHtml - ap pretohtml");
	  if (!is_array($replace)) {
		log::add('zero_motorcycles', 'debug',  "Function toHtml - dans le if");
		return $replace;
		log::add('zero_motorcycles', 'debug',  "Function toHtml - return replace");
		
	  }

      $version = jeedom::versionAlias($_version);
		log::add('zero_motorcycles', 'debug',  "Function toHtml - new version $version");
		$cmdis=$this->getCmd('info', null);
		foreach ($cmdis as $cmd) {
			$cmd_LogId=$cmd->getLogicalId(); 
			log::add('zero_motorcycles', 'debug',  "Function toHtml - commande info : $cmd_LogId | id : ". $cmd->getId());
			$replace['#' . $cmd_LogId . '#'] = $cmd->execCmd();
			$replace['#' . $cmd_LogId . '_id#'] = $cmd->getId();
			$replace['#' . $cmd_LogId . '_collectDate#'] =date('d-m-Y H:i:s',strtotime($cmd->getCollectDate()));
			$replace['#' . $cmd_LogId . '_updatetime#'] =date('d-m-Y H:i:s',strtotime( $this->getConfiguration('updatetime')));
			
		}
	  
		$cmdas=$this->getCmd('action', null);
		foreach ($cmdas as $cmd) {
			$cmd_LogId=$cmd->getLogicalId(); 
			$replace['#' . $cmd_LogId . '_id#'] = $cmd->getId();
			log::add('zero_motorcycles', 'debug',  "Function toHtml - commande action : $cmd_LogId | id : ". $cmd->getId());
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

      log::add('zero_motorcycles', 'debug',  "Function toHtml - Value replace : ".json_encode($replace));	
      $html = template_replace($replace, getTemplate('core', $_version, 'default_zero_motorcycles', 'zero_motorcycles'));
      cache::set('widgetHtml' . $_version . $this->getId(), $html, 1);
      log::add('zero_motorcycles', 'debug',  "Function toHtml - End");
      return $html;
	}
}


class zero_motorcyclesCmd extends cmd {
  	public function execute($_options = array()) {
      	$eqlogic = $this->getEqLogic();
      	$logicalId=$this->getLogicalId();
      	log::add('zero_motorcycles', 'debug',  "  * Execution cmd alarmeIMA | cmd : $logicalId => title : ".$_options['title'] . " | message : " .$_options['message']);
      	switch ($logicalId) {
				case 'setModeAlarme':
            		log::add('zero_motorcycles', 'debug',  "Click on setModeAlarme equipement");
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
                          log::add('zero_motorcycles', 'debug',  "Click on setModeAlarme equipement ==> message absent");
                        }
                      } else {
                        log::add('zero_motorcycles', 'debug',  "Click on setModeAlarme equipement ==> action demandée non gérée");
                      }
                    } else {
                      log::add('zero_motorcycles', 'debug',  "Click on setModeAlarme equipement ==> aucune action demandée");
                    }
            		//log::add('zero_motorcycles', 'debug',  "Simulate click on refresh alarm status after action on it");
                    $eqlogic->writeSeparateLine();
            		$eqlogic->getCmd(null, 'refreshAlarmeStatus')->execCmd();
            		break;
          		case 'refreshAlarmeStatus':
            		$eqlogic->writeSeparateLine();
            		log::add('zero_motorcycles', 'debug',  "Click on refresh alarm status");
					$eqlogic->GetAlarmState();
            		$eqlogic->writeSeparateLine();
            		break;
          		case 'refreshAlarmEvents':
            		$eqlogic->writeSeparateLine();
            		log::add('zero_motorcycles', 'debug',  "Click on refresh alarm events");
					$alarmEvent=$eqlogic->GetAlarmEvents();
            		if (isset($alarmEvent)) {
                      	log::add('zero_motorcycles', 'debug', " * MAJ alarmeEventsBrute");
                      	$eqlogic->checkAndUpdateCmd('alarmeEventsBrute', $alarmEvent);
						log::add('zero_motorcycles', 'debug', " * MAJ alarmeEvents");
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
            		log::add('zero_motorcycles', 'debug',  "Click on refresh camera snapshot");
            		$cameraSnapshot=$eqlogic->GetCamerasSnapshot();
            		if (isset($cameraSnapshot)) {
						log::add('zero_motorcycles', 'debug', " * MAJ cameraSnapshotBrute");
                      	$eqlogic->checkAndUpdateCmd('cameraSnapshotBrute', $cameraSnapshot);
						log::add('zero_motorcycles', 'debug', " * MAJ cameraSnapshot");
						$eqlogic->checkAndUpdateCmd('cameraSnapshot', $eqlogic->buildTabCamerasEvents($cameraSnapshot));
                    }
            		$eqlogic->writeSeparateLine();
                    break;
          		case 'actionScreenshot':
            		$eqlogic->writeSeparateLine();
            		log::add('zero_motorcycles', 'debug',  "  * Request title : ".$_options['title'] . " | message : " .$_options['message']);
            		if (isset($_options['message']) and isset($_options['title'])){
                      	if ($_options['title']=="get") {
	                      	return $eqlogic->getPictures($_options['message']);
                        } else if ($_options['title']=="delete"){
                          	$eqlogic->deletePictures($_options['message']);
                        }  else if ($_options['title']=="take"){
							return $eqlogic->takeSnapshot($_options['message']);
						}else {
                          	log::add('zero_motorcycles', 'debug',  "  * Request non prise en charge : ".$_options['title']);
                        }
                    } else {
                      	log::add('zero_motorcycles', 'debug',  "  * Request non complète => manque title ou message");
                    }
            		$eqlogic->writeSeparateLine();
            		break;

					//manage camera snapshot
		
        }

		if (strpos($logicalId, 'snapshot') !== false) {
			$aLogicalId=explode('_',$logicalId);
			$pk=$aLogicalId[2];
			$room=$aLogicalId[1];
			log::add('zero_motorcycles', 'debug',  "  * Request snapshot on  : ". $room . ' -> ' . $pk . '|Notification : ' . $eqlogic->getConfiguration('cfgAlertSnapshot'));
			$urlImg = $eqlogic->takeSnapshot($pk);
			$base64Img = $eqlogic->getPictures($urlImg);
			$eqlogic->checkAndUpdateCmd('cameraSnapshotImage', $base64Img);

			if ($eqlogic->getConfiguration('cfgAlertSnapshot') === '1' && isset($base64Img)) {
				$filePath=$eqlogic->buildFilePathImage($eqlogic->getId());
				log::add('zero_motorcycles', 'debug',  "  	* Save snapshot image to file system : " . $filePath);
				$eqlogic->saveImgToFileSystem($filePath,$base64Img);								
				
				$notifCmd=cmd::byId(str_replace('#','',$eqlogic->getConfiguration('cfgCmdSendMsg')));
				if (is_object($notifCmd)) {
					log::add('zero_motorcycles', 'debug',  "  	* Execute notification for sending snapshot image");
					$options = array('title' => $eqlogic->getConfiguration('cfgMsgTitle') . ' : demande d\'image pour ' . $room,'message' => '', 'files'=> array($filePath));
					$notifCmd->execCmd($options, $cache=0);
				}	
			}
			$eqlogic->writeSeparateLine();		
		}
	}

}
