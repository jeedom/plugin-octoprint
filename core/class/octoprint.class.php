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

/* * ***************************Includes**********************************/
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class octoprint extends eqLogic {
	/***************************Attributs*******************************/	
	public static function cron($_eqlogic_id = null) {
		$eqLogics = ($_eqlogic_id !== null) ? array(eqLogic::byId($_eqlogic_id)) : eqLogic::byType('octoprint', true);
		foreach ($eqLogics as $octoprint) {
			try {
				$octoprint->getOctoprintInfo();
			} catch (Exception $e) {

			}
		}
	}
	
	public function getOctoprintInfo() {
		try {
			$servip = $this->getConfiguration('servip','');
			$apikey = $this->getConfiguration('apikey','');
			$urlprinter = 'http://' . $servip . '/api/printer';
			$request_http = new com_http($urlprinter);
			$request_http->setHeader(array('X-Api-Key: '.$apikey));
			$printerinfo=$request_http->exec();
			log::add('octoprint','debug',$printerinfo);
			$jsonprinter = json_decode($printerinfo,true);
			$order = 13;
			foreach($jsonprinter['temperature'] as $key => $val) {
				$octoprintCmd = $this->getCmd(null, 'target'.$key);
				if (!is_object($octoprintCmd)) {
					$octoprintCmd = new octoprintCmd();
					$octoprintCmd->setName(__('Cible ' . $key, __FILE__));
					$octoprintCmd->setLogicalId('target'.$key);
					$octoprintCmd->setEqLogic_id($this->getId());
					$octoprintCmd->setType('info');
					$octoprintCmd->setOrder($order);
					$octoprintCmd->setTemplate('dashboard', 'line');
					$octoprintCmd->setTemplate('mobile', 'line');
					$octoprintCmd->setSubType('numeric');
					$octoprintCmd->setUnite('°C');
					$octoprintCmd->setIsVisible(1);
					$octoprintCmd->save();
				}
				$order += 1;
				$this->checkAndUpdateCmd($octoprintCmd, $jsonprinter['temperature'][$key]['target']);
				$octoprintCmd = $this->getCmd(null, 'actual'.$key);
				if (!is_object($octoprintCmd)) {
					$octoprintCmd = new octoprintCmd();
					$octoprintCmd->setName(__('Actuelle ' . $key, __FILE__));
					$octoprintCmd->setLogicalId('actual'.$key);
					$octoprintCmd->setEqLogic_id($this->getId());
					$octoprintCmd->setType('info');
					$octoprintCmd->setOrder($order);
					$octoprintCmd->setTemplate('dashboard', 'line');
					$octoprintCmd->setTemplate('mobile', 'line');
					$octoprintCmd->setSubType('numeric');
					$octoprintCmd->setUnite('°C');
					$octoprintCmd->setIsVisible(1);
					$octoprintCmd->save();
				}
				$order += 1;
				$this->checkAndUpdateCmd($octoprintCmd, $jsonprinter['temperature'][$key]['actual']);
			}
			foreach($jsonprinter['state']['flags'] as $key => $val) {
				$octoprintCmd = $this->getCmd(null, $key);
				if (is_object($octoprintCmd)) {
					$this->checkAndUpdateCmd($octoprintCmd, $jsonprinter['state']['flags'][$key]);
				}
			}
			$octoprintCmd = $this->getCmd(null, 'status');
			$this->checkAndUpdateCmd($octoprintCmd, $jsonprinter['state']['text']);
			$urljob = 'http://' . $servip . '/api/job';
			$request_http = new com_http($urljob);
			$request_http->setHeader(array('X-Api-Key: '.$apikey));
			$jobinfo=$request_http->exec();
			log::add('octoprint','debug',$jobinfo);
			$jsonjob = json_decode($jobinfo,true);
			foreach($jsonjob['progress'] as $key => $val) {
				$octoprintCmd = $this->getCmd(null, $key);
				if (is_object($octoprintCmd)) {
					$this->checkAndUpdateCmd($octoprintCmd, round($jsonjob['progress'][$key],1));
				}
				$octoprintCmd = $this->getCmd(null, $key.'Human');
				if (is_object($octoprintCmd)) {
					$t = round($jsonjob['progress'][$key],1);
					$this->checkAndUpdateCmd($octoprintCmd, sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60));
				}
			}
			$octoprintCmd = $this->getCmd(null, 'filename');
			if (is_object($octoprintCmd)) {
				$this->checkAndUpdateCmd($octoprintCmd, $jsonjob['job']['file']['name']);
			}
		} catch (Exception $e) {
			$octoprintCmd = $this->getCmd(null, 'status');
			if (is_object($octoprintCmd)) {
				$this->checkAndUpdateCmd($octoprintCmd, 'Erreur communication');
			}
		}
	} 
	
	public function getImage(){
		return 'plugins/octoprint/plugin_info/octoprint_icon.png';
	}
	
	public function postSave() {
		$status = $this->getCmd(null, 'status');
		if (!is_object($status)) {
			$status = new octoprintcmd();
			$status->setLogicalId('status');
			$status->setIsVisible(1);
			$status->setOrder(1);
			$status->setName(__('Statut', __FILE__));
		}
		$status->setType('info');
		$status->setSubType('string');
		$status->setEqLogic_id($this->getId());
		$status->save();
		
		$filename = $this->getCmd(null, 'filename');
		if (!is_object($filename)) {
			$filename = new octoprintcmd();
			$filename->setLogicalId('filename');
			$filename->setIsVisible(1);
			$filename->setOrder(2);
			$filename->setName(__('Nom fichier', __FILE__));
		}
		$filename->setType('info');
		$filename->setSubType('string');
		$filename->setEqLogic_id($this->getId());
		$filename->save();
		
		$operational = $this->getCmd(null, 'operational');
		if (!is_object($operational)) {
			$operational = new octoprintcmd();
			$operational->setLogicalId('operational');
			$operational->setIsVisible(0);
			$operational->setOrder(3);
			$operational->setName(__('Opérationelle', __FILE__));
		}
		$operational->setType('info');
		$operational->setSubType('binary');
		$operational->setEqLogic_id($this->getId());
		$operational->save();
		
		$paused = $this->getCmd(null, 'paused');
		if (!is_object($paused)) {
			$paused = new octoprintcmd();
			$paused->setLogicalId('paused');
			$paused->setIsVisible(0);
			$paused->setOrder(3);
			$paused->setName(__('En pause', __FILE__));
		}
		$paused->setType('info');
		$paused->setSubType('binary');
		$paused->setEqLogic_id($this->getId());
		$paused->save();
		
		$printing = $this->getCmd(null, 'printing');
		if (!is_object($printing)) {
			$printing = new octoprintcmd();
			$printing->setLogicalId('printing');
			$printing->setIsVisible(0);
			$printing->setOrder(4);
			$printing->setName(__('En impression', __FILE__));
		}
		$printing->setType('info');
		$printing->setSubType('binary');
		$printing->setEqLogic_id($this->getId());
		$printing->save();
		
		$error = $this->getCmd(null, 'error');
		if (!is_object($error)) {
			$error = new octoprintcmd();
			$error->setLogicalId('error');
			$error->setIsVisible(0);
			$error->setOrder(5);
			$error->setName(__('En erreur', __FILE__));
		}
		$error->setType('info');
		$error->setSubType('binary');
		$error->setEqLogic_id($this->getId());
		$error->save();
		
		$ready = $this->getCmd(null, 'ready');
		if (!is_object($ready)) {
			$ready = new octoprintcmd();
			$ready->setLogicalId('ready');
			$ready->setIsVisible(0);
			$ready->setOrder(6);
			$ready->setName(__('Prête', __FILE__));
		}
		$ready->setType('info');
		$ready->setSubType('binary');
		$ready->setEqLogic_id($this->getId());
		$ready->save();
		
		$sdready = $this->getCmd(null, 'sdReady');
		if (!is_object($sdready)) {
			$sdready = new octoprintcmd();
			$sdready->setLogicalId('sdReady');
			$sdready->setIsVisible(0);
			$sdready->setOrder(7);
			$sdready->setName(__('SD prête', __FILE__));
		}
		$sdready->setType('info');
		$sdready->setSubType('binary');
		$sdready->setEqLogic_id($this->getId());
		$sdready->save();
		
		$printTimeLeft = $this->getCmd(null, 'printTimeLeft');
		if (!is_object($printTimeLeft)) {
			$printTimeLeft = new octoprintcmd();
			$printTimeLeft->setLogicalId('printTimeLeft');
			$printTimeLeft->setIsVisible(0);
			$printTimeLeft->setTemplate('dashboard', 'line');
			$printTimeLeft->setTemplate('mobile', 'line');
			$printTimeLeft->setOrder(8);
			$printTimeLeft->setName(__('Temps restant', __FILE__));
		}
		$printTimeLeft->setType('info');
		$printTimeLeft->setSubType('numeric');
		$printTimeLeft->setUnite('s');
		$printTimeLeft->setEqLogic_id($this->getId());
		$printTimeLeft->save();
		
		$printTimeLeftHuman = $this->getCmd(null, 'printTimeLeftHuman');
		if (!is_object($printTimeLeftHuman)) {
			$printTimeLeftHuman = new octoprintcmd();
			$printTimeLeftHuman->setLogicalId('printTimeLeftHuman');
			$printTimeLeftHuman->setIsVisible(1);
			$printTimeLeftHuman->setTemplate('dashboard', 'line');
			$printTimeLeftHuman->setTemplate('mobile', 'line');
			$printTimeLeftHuman->setOrder(9);
			$printTimeLeftHuman->setName(__('Temps restant (humain)', __FILE__));
		}
		$printTimeLeftHuman->setType('info');
		$printTimeLeftHuman->setSubType('string');
		$printTimeLeftHuman->setEqLogic_id($this->getId());
		$printTimeLeftHuman->save();
		
		$printTime = $this->getCmd(null, 'printTime');
		if (!is_object($printTime)) {
			$printTime = new octoprintcmd();
			$printTime->setLogicalId('printTime');
			$printTime->setIsVisible(0);
			$printTime->setTemplate('dashboard', 'line');
			$printTime->setTemplate('mobile', 'line');
			$printTime->setOrder(10);
			$printTime->setName(__('Durée d\'impression', __FILE__));
		}
		$printTime->setType('info');
		$printTime->setSubType('numeric');
		$printTime->setUnite('s');
		$printTime->setEqLogic_id($this->getId());
		$printTime->save();
		
		$printTimeHuman = $this->getCmd(null, 'printTimeHuman');
		if (!is_object($printTimeHuman)) {
			$printTimeHuman = new octoprintcmd();
			$printTimeHuman->setLogicalId('printTimeHuman');
			$printTimeHuman->setIsVisible(1);
			$printTimeHuman->setTemplate('dashboard', 'line');
			$printTimeHuman->setTemplate('mobile', 'line');
			$printTimeHuman->setOrder(11);
			$printTimeHuman->setName(__('Durée d\'impression (humain)', __FILE__));
		}
		$printTimeHuman->setType('info');
		$printTimeHuman->setSubType('string');
		$printTimeHuman->setEqLogic_id($this->getId());
		$printTimeHuman->save();
		
		$completion = $this->getCmd(null, 'completion');
		if (!is_object($completion)) {
			$completion = new octoprintcmd();
			$completion->setLogicalId('completion');
			$completion->setIsVisible(1);
			$completion->setOrder(12);
			$completion->setName(__('Pourcentage d\'avancement', __FILE__));
		}
		$completion->setType('info');
		$completion->setSubType('numeric');
		$completion->setTemplate('dashboard', 'line');
		$completion->setTemplate('mobile', 'line');
		$completion->setUnite('%');
		$completion->setEqLogic_id($this->getId());
		$completion->save();
		
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new octoprintcmd();
			$refresh->setLogicalId('refresh');
			$refresh->setIsVisible(1);
			$refresh->setName(__('Rafraîchir', __FILE__));
		}
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->setEqLogic_id($this->getId());
		$refresh->save();
		
		$resume = $this->getCmd(null, 'resume');
		if (!is_object($resume)) {
			$resume = new octoprintcmd();
			$resume->setLogicalId(resume);
			$resume->setDisplay('icon','<i class="fa fa-play"></i>');
			$resume->setIsVisible(1);
			$resume->setOrder(30);
			$resume->setName(__('Reprendre limpression', __FILE__));
		}
		$resume->setType('action');
		$resume->setSubType('other');
		$resume->setEqLogic_id($this->getId());
		$resume->save();
		
		$pause = $this->getCmd(null, 'pause');
		if (!is_object($pause)) {
			$pause = new octoprintcmd();
			$pause->setLogicalId(pause);
			$pause->setDisplay('icon','<i class="fa fa-pause"></i>');
			$pause->setIsVisible(1);
			$pause->setOrder(31);
			$pause->setName(__('Mettre en pause', __FILE__));
		}
		$pause->setType('action');
		$pause->setSubType('other');
		$pause->setEqLogic_id($this->getId());
		$pause->save();
		
		$cancel = $this->getCmd(null, 'cancel');
		if (!is_object($cancel)) {
			$cancel = new octoprintcmd();
			$cancel->setLogicalId('cancel');
			$cancel->setDisplay('icon','<i class="fa fa-stop"></i>');
			$cancel->setIsVisible(1);
			$cancel->setOrder(32);
			$cancel->setName(__('Arrêter limpression', __FILE__));
		}
		$cancel->setType('action');
		$cancel->setSubType('other');
		$cancel->setEqLogic_id($this->getId());
		$cancel->save();
		
		$shutdown = $this->getCmd(null, 'shutdown');
		if (!is_object($shutdown)) {
			$shutdown = new octoprintcmd();
			$shutdown->setLogicalId('shutdown');
			$shutdown->setDisplay('icon','<i class="fa fa-power-off"></i>');
			$shutdown->setIsVisible(1);
			$shutdown->setOrder(33);
			$shutdown->setName(__('Eteindre le serveur', __FILE__));
		}
		$shutdown->setType('action');
		$shutdown->setSubType('other');
		$shutdown->setEqLogic_id($this->getId());
		$shutdown->save();
		
		$reboot = $this->getCmd(null, 'reboot');
		if (!is_object($reboot)) {
			$reboot = new octoprintcmd();
			$reboot->setLogicalId('reboot');
			$reboot->setDisplay('icon','<i class="fa fa-random"></i>');
			$reboot->setIsVisible(1);
			$reboot->setOrder(34);
			$reboot->setName(__('Reboot le serveur', __FILE__));
		}
		$reboot->setType('action');
		$reboot->setSubType('other');
		$reboot->setEqLogic_id($this->getId());
		$reboot->save();
		
		$restart = $this->getCmd(null, 'restart');
		if (!is_object($restart)) {
			$restart = new octoprintcmd();
			$restart->setLogicalId('restart');
			$restart->setDisplay('icon','<i class="fa fa-refresh"></i>');
			$restart->setIsVisible(1);
			$restart->setOrder(35);
			$restart->setName(__('Relancer Octoprint', __FILE__));
		}
		$restart->setType('action');
		$restart->setSubType('other');
		$restart->setEqLogic_id($this->getId());
		$restart->save();
	}
}

class octoprintCmd extends cmd {
	/***************************Attributs*******************************/


	/*************************Methode static****************************/

	/***********************Methode d'instance**************************/

	public function execute($_options = null) {
		if ($this->getType() == '') {
			return '';
		}
		$eqLogic = $this->getEqlogic();
		$servip = $eqLogic->getConfiguration('servip','');
		$apikey = $eqLogic->getConfiguration('apikey','');
		$logical = $this->getLogicalId();
		if ($logical != 'refresh'){
			$urlprinter = 'http://' . $servip . '/api/job';
			$request_http = new com_http($urlprinter);
			$request_http->setHeader(array('Content-Type: application/json','X-Api-Key: '.$apikey));
			switch ($logical) {
				case 'cancel':
					$request_http->setPost('{"command":"cancel"}');
				break;
				case 'pause':
					$request_http->setPost('{"command":"pause","action":"pause"}');
				break;
				case 'resume':
					$request_http->setPost('{"command":"pause","action":"resume"}');
				break;
			}
			$result=$request_http->exec();
			log::add('octoprint','debug',$result);
		}
		$eqLogic->getOctoprintInfo();
	}

	/************************Getteur Setteur****************************/
}
?>