<?php
date_default_timezone_set('Europe/Budapest');

include("Session.php");
include("Building.php");
include("Market.php");
include("Technology.php");
class Village {
	
	public $type;
	public $coor = array();
	public $awood,$aclay,$airon,$acrop,$pop,$maxstore,$maxcrop;
	public $wid,$vname,$capital;
	public $resarray = array();
	public $unitarray,$techarray,$unitall,$researching,$abarray = array();
	private $infoarray = array();
	private $production = array();
	private $oasisowned,$ocounter = array();
	
	function __construct() {
		global $session;
		if(isset($_SESSION['wid'])) {
			$this->wid = $_SESSION['wid'];
		}
		else {
			$this->wid = $session->villages[0];
		}
		$this->LoadTown();
		$this->calculateProduction();
		$this->processProduction();
		$this->ActionControl();
	}
	
	public function getProd($type) {
		return $this->production[$type];
	}
	
	public function getAllUnits($vid) {
		global $database,$technology;
		return $technology->getUnits($database->getUnit($vid),$database->getEnforceVillage($vid,0));
	}
		
	private function LoadTown() {
		global $database,$session,$logging,$technology;
		$this->infoarray = $database->getVillage($this->wid);
		if($this->infoarray['owner'] != $session->uid && !$session->isAdmin) {
			unset($_SESSION['wid']);
			$logging->addIllegal($session->uid,$this->wid,1);
			$this->wid = $session->villages[0];
			$this->infoarray = $database->getVillage($this->wid);
		}
		$this->resarray = $database->getResourceLevel($this->wid);
		$this->coor = $database->getCoor($this->wid);
		$this->type = $database->getVillageType($this->wid);
		$this->oasisowned = $database->getOasis($this->wid);
		$this->ocounter = $this->sortOasis();
		$this->unitarray = $database->getUnit($this->wid);
		$this->enforcetome = $database->getEnforceVillage($this->wid,0);
		$this->enforcetoyou = $database->getEnforceVillage($this->wid,1);
		$this->unitall =  $technology->getAllUnits($this->wid);
		$this->techarray = $database->getTech($this->wid);
		$this->abarray = $database->getABTech($this->wid);
		$this->researching = $database->getResearching($this->wid);
		$this->capital = $this->infoarray['capital'];
		$this->natar = $this->infoarray['natar'];
		$this->currentcel = $this->infoarray['celebration'];
		$this->wid = $this->infoarray['wref'];
		$this->vname = $this->infoarray['name'];
		$this->awood = $this->infoarray['wood'];
		$this->aclay = $this->infoarray['clay'];
		$this->airon = $this->infoarray['iron'];
		$this->acrop = $this->infoarray['crop'];
		$this->pop = $this->infoarray['pop'];
		$this->maxstore = $this->infoarray['maxstore'];
		$this->maxcrop = $this->infoarray['maxcrop'];
		$this->allcrop = $this->getCropProd();
		$this->loyalty = $this->infoarray['loyalty'];
		$this->master = count($database->getMasterJobs($this->wid));
		//de gs in town, zetten op max pakhuisinhoud
		if($this->awood>$this->maxstore){ $this->awood=$this->maxstore; $database->updateResource($this->wid,'wood',$this->maxstore); }
		if($this->aclay>$this->maxstore){ $this->aclay=$this->maxstore; $database->updateResource($this->wid,'clay',$this->maxstore); }
		if($this->airon>$this->maxstore){ $this->airon=$this->maxstore; $database->updateResource($this->wid,'iron',$this->maxstore); }
		if($this->acrop>$this->maxcrop){ $this->acrop=$this->maxcrop; $database->updateResource($this->wid,'crop',$this->maxcrop); }

	}
	
	private function calculateProduction() { 
		global $technology,$database,$session;
        $normalA = $database->getOwnArtefactInfoByType($_SESSION['wid'],4);  
		$largeA = $database->getOwnUniqueArtefactInfo($session->uid,4,2);
        $uniqueA = $database->getOwnUniqueArtefactInfo($session->uid,4,3);
        $upkeep = $technology->getUpkeep($this->unitall,0);
		$heroData = $database->getHeroData($session->uid);
		if($heroData['dead']==0 && $heroData['wref']==$this->wid){
			$hwood = $heroData['r1']*10*SPEED*$heroData['product'];
			$hclay = $heroData['r2']*10*SPEED*$heroData['product'];
			$hiron = $heroData['r3']*10*SPEED*$heroData['product'];
			$hcrop = $heroData['r4']*10*SPEED*$heroData['product'];
			$hproduct = $heroData['r0']*3*SPEED*$heroData['product'];
		}else{
			$hwood = 0;
			$hclay = 0;
			$hiron = 0;
			$hcrop = 0;
			$hproduct = 0;
		}
		
        $this->production['wood'] = $this->getWoodProd()+$hwood+$hproduct;
		$this->production['clay'] = $this->getClayProd()+$hclay+$hproduct;
		$this->production['iron'] = $this->getIronProd()+$hiron+$hproduct;
		
        if ($uniqueA['size']==3 && $uniqueA['owner']==$session->uid){
        $this->production['crop'] = $this->getCropProd()-$this->pop-(($upkeep)-round($upkeep*0.50))+$hcrop+$hproduct;  
        
        }else if ($normalA['type']==4 && $normalA['size']==1 && $normalA['owner']==$session->uid){
        $this->production['crop'] = $this->getCropProd()-$this->pop-(($upkeep)-round($upkeep*0.25))+$hcrop+$hproduct;
        
        }else if ($largeA['size']==2 && $largeA['owner']==$session->uid){
         $this->production['crop'] = $this->getCropProd()-$this->pop-(($upkeep)-round($upkeep*0.25))+$hcrop+$hproduct;   
       
        }else{
		$this->production['crop'] = $this->getCropProd()-$this->pop-$upkeep+$hcrop+$hproduct;  
	}
    }
	
	
	private function processProduction() {
		global $database;
		$timepast = time() - $this->infoarray['lastupdate'];
		$nwood = ($this->production['wood'] / 3600) * $timepast;
		$nclay = ($this->production['clay'] / 3600) * $timepast;
		$niron = ($this->production['iron'] / 3600) * $timepast;
		$ncrop = ($this->production['crop'] / 3600) * $timepast;

		$database->modifyResource($this->wid,$nwood,$nclay,$niron,$ncrop,1);
		$database->updateVillage($this->wid);
		$this->LoadTown();
	}
	
	private function getWoodProd() {
		global $bid1,$bid5,$session;
		$wood = $sawmill = 0;
		$woodholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 1) {
				array_push($woodholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 5) {
				$sawmill = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($woodholder)-1;$i++) { $wood+= $bid1[$this->resarray[$woodholder[$i]]]['prod']; }
		if($sawmill >= 1) {
			$wood += $wood /100 * $bid5[$sawmill]['attri'];
		}
		if($this->ocounter[0] != 0) {
			$wood += $wood*0.25*$this->ocounter[0];
		}
		if($session->bonus1 == 1) {
			$wood *= 1.25;
		}
		$wood += $wood*$this->ocounter[0]*0.25;
		$wood *= SPEED;
		return round($wood);
	}
	
	private function getClayProd() {
		global $bid2,$bid6,$session;
		$clay = $brick = 0;
		$clayholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 2) {
				array_push($clayholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 6) {
				$brick = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($clayholder)-1;$i++) { $clay+= $bid2[$this->resarray[$clayholder[$i]]]['prod']; }
		if($brick >= 1) {
			$clay += $clay /100 * $bid6[$brick]['attri'];
		}
		if($this->ocounter[1] != 0) {
			$clay += $clay*0.25*$this->ocounter[1];
		}
		if($session->bonus2 == 1) {
			$clay *= 1.25;
		}
		$clay += $clay*$this->ocounter[1]*0.25;
		$clay *= SPEED;
		return round($clay);
	}
	
	private function getIronProd() {
		global $bid3,$bid7,$session;
		$iron = $foundry = 0;
		$ironholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 3) {
				array_push($ironholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 7) {
				$foundry = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($ironholder)-1;$i++) { $iron+= $bid3[$this->resarray[$ironholder[$i]]]['prod']; }
		if($foundry >= 1) {
			$iron += $iron /100 * $bid7[$foundry]['attri'];
		}
		if($this->ocounter[2] != 0) {
			$iron += $iron*0.25*$this->ocounter[2];
		}
		if($session->bonus3 == 1) {
			$iron *= 1.25;
		}
		$iron += $iron*$this->ocounter[2]*0.25;
		$iron *= SPEED;
		return round($iron);
	}
	
	private function getCropProd() {
		global $bid4,$bid8,$bid9,$session;
		$crop = $grainmill = $bakery = 0;
		$cropholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 4) {
				array_push($cropholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 8) {
				$grainmill = $this->resarray['f'.$i];
			}
			if($this->resarray['f'.$i.'t'] == 9) {
				$bakery = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($cropholder)-1;$i++) { $crop+= $bid4[$this->resarray[$cropholder[$i]]]['prod']; }
		if($grainmill >= 1 || $bakery >= 1) {
			$crop += $crop /100 * ($bid8[$grainmill]['attri'] + $bid9[$bakery]['attri']);
		}
		if($this->ocounter[3] != 0) {
			$crop += $crop*0.25*$this->ocounter[3];
		}
		if($session->bonus4 == 1) {
			$crop *= 1.25;
		}
		$crop += $crop*$this->ocounter[3]*0.25;
		$crop *= SPEED;
		return round($crop);
	}
	
	private function sortOasis() {
		$crop = $clay = $wood = $iron = 0;
		if (!empty($this->oasisowned)) {
			foreach ($this->oasisowned as $oasis) {
			switch($oasis['type']) {
					case 1:
					$wood += 1;
					break;
					case 2:
					$wood += 2;
					break;
					case 3:
					$wood += 1;
					$crop += 1;
					break;
					case 4:
					$clay += 1;
					break;
					case 5:
					$clay += 2;
					break;
					case 6:
					$clay += 1;
					$crop += 1;
					break;
					case 7:
					$iron += 1;
					break;
					case 8:
					$iron += 2;
					break;
					case 9:
					$iron += 1;
					$crop += 1;
					break;
					case 10:
					case 11:
					$crop += 1;
					break;
					case 12:
					$crop += 2;
					break;
				}
			}
		}
		return array($wood,$clay,$iron,$crop);
	}
	
	private function ActionControl() {
		global $session;
		if(SERVER_WEB_ROOT) {
			$page = $_SERVER['SCRIPT_NAME'];
		}
		else {
			$explode = explode("/",$_SERVER['SCRIPT_NAME']);
			$i = count($explode)-1;
			$page = $explode[$i];
		}
		if($page == "build.php" && $session->uid != $this->infoarray['owner']) {
			unset($_SESSION['wid']);
			header("Location: dorf1.php");
		}
	}
	
};
$village = new Village;
$building = new Building;

?>
