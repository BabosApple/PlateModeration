<?php

namespace UltraTeam\Moderation;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->db = new \SQLite3($this->getDataFolder() . "data.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS banPlayer (player TEXT, banTime INT, reason TEXT, staff TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS mutePlayer (player TEXT, muteTime INT, reason TEXT, staff TEXT);");
		@mkdir($this->getDataFolder() . "warnings/");
		$this->form = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
		$this->prefixTitle = "§cMODERATION";
	}

	public $targetPlayer = [];

	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$this->config = new Config($this->getDataFolder() . "warnings/" . strtolower($player->getName()), Config::YAML, array(
			"warnings" => 0
		));
		$this->config;
	}

	public function onCommand(CommandSender $sender, Command $command, String $label, Array $args) : bool {

		if($command->getName() == "mban"){
			if($sender instanceof Player){
				$this->ban($sender);
			}
		}
		if($command->getName() == "munban"){
			if($sender instanceof Player){
				$this->unban($sender);
			}
		}
		if($command->getName() == "mute"){
			if($sender instanceof Player){
				$this->mute($sender);
			}
		}
		if($command->getName() == "unmute"){
			if($sender instanceof Player){
				$this->unmute($sender);
			}
		}
		if($command->getName() == "warn"){
			if($sender instanceof Player){
				$this->warn($sender);
			}
		}
		if($command->getName() == "delwarn"){
			if($sender instanceof Player){
				$this->delwarn($sender);
			}
		}
		if($command->getName() == "viewwarn"){
			if($sender instanceof Player){
				if(isset($args[0])){
					$p = $this->getServer()->getPlayer($args[0]);
					if($p instanceof Player){
						$this->configP = new Config($this->getDataFolder() . "warnings/" . strtolower($p->getName()), Config::YAML);
						$sender->sendMessage($this->prefixTitle . "§9 > §ePlayer " . $p->getName() . " Have " . $this->configP->get("warnings") . " Warning(s)");
					} else {
						$sender->sendMessage($this->prefixTitle . "§9 > §6Player not found!");
					}
				} else {
					$this->configS = new Config($this->getDataFolder() . "warnings/" . strtolower($sender->getName()), Config::YAML);
					$sender->sendMessage($this->prefixTitle . "§9 > §eYou have " . $this->configS->get("warnings") . " Warning(s)");
				}
			}
		}
	return true;
	}

	public function ban($player){
		$plf = $this->form->createCustomForm(function (Player $player, array $data = null){
			if($data === null){
				return true;
			}
			$p = $this->getServer()->getPlayer($data[0]);
			if($p instanceof Player){
				$time = time();
				$min = $data[3] * 60;
				$hour = $data[2] * 3600;
				$day = $data[1] * 86400;
				$banTime = $time + $day + $hour + $min;
				$banInfo = $this->db->prepare("INSERT OR REPLACE INTO banPlayer (player, banTime, reason, staff) VALUES (:player, :banTime, :reason, :staff);");
				$banInfo->bindValue(":player", $p->getName());
				$banInfo->bindValue(":banTime", $banTime);
				$banInfo->bindValue(":reason", $data[4]);
				$banInfo->bindValue(":staff", $player->getName());
				$banInfo->execute();
				$p->kick("§cYou've been banned by:§4 " . $player->getName() . " §cFor:§4 " . $data[4] . "\n§cYou will available to join again in: Day(s):" . $data[1] . " Hour(s):" . $data[2] . " Minutes:" . $data[3] . "\n Think your ban was a mistake? Contant us in discord!");
				$this->getServer()->broadcastMessage($this->prefixTitle . "§9 > §cPLAYER: §4" . $p->getName() . " §cHas been banned for: §4" . $data[4] . " §cBy: §4" . $player->getName());
			}
		});
		$plf->setTitle($this->prefixTitle);
		$plf->addInput("§eType the player name you want to ban!");
		$plf->addSlider("§eDays", 0, 30, 1);
		$plf->addSlider("§eHours", 0, 24, 1);
		$plf->addSlider("§eMinutes", 0, 60, 5);
		$plf->addInput("§ePlease type the reason");
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function unban($player){
		$plf = $this->form->createSimpleForm(function (Player $player, string $data = null){
			if($data === null){
				return true;
			}
			$this->targetPlayer[$player->getName()] = $data;
			$this->banned($player);
		});
		$plf->setTitle($this->prefixTitle);
		$plf->setContent("§eTap the player name to unban the player");
		$banInfo = $this->db->query("SELECT * FROM banPlayer;");
		$i = -1;
		while ($resultArr = $banInfo->fetchArray(SQLITE3_ASSOC)) {
			$j = $i + 1;
			$banPlayer = $resultArr['player'];
			$plf->addButton("§e$banPlayer", -1, "", $banPlayer);
			$i = $i + 1;
		}
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function banned($player){
		$plf = $this->form->createSimpleForm(function (Player $player, int $data = null){
			if($data === null){
				return true;
			}
			$banplayer = $this->targetPlayer[$player->getName()];
					$banInfo = $this->db->query("SELECT * FROM banPlayer WHERE player = '$banplayer';");
					$array = $banInfo->fetchArray(SQLITE3_ASSOC);
					if (!empty($array)) {
						$this->db->query("DELETE FROM banPlayer WHERE player = '$banplayer';");
						$player->sendMessage("You've unbanned the player!");
					}
					unset($this->targetPlayer[$player->getName()]);
		});
		$plf->setTitle($this->prefixTitle);
		$plf->setContent("§eAre you sure want to unban?");
		$plf->addButton("Unban!");
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function mute($player){
		$plf = $this->form->createCustomForm(function (Player $player, array $data = null){
			if($data === null){
				return true;
			}
			$p = $this->getServer()->getPlayer($data[0]);
			if($p instanceof Player){
				$time = time();
				$min = $data[3] * 60;
				$hour = $data[2] * 3600;
				$day = $data[1] * 86400;
				$muteTime = $time + $day + $hour + $min;
				$muteInfo = $this->db->prepare("INSERT OR REPLACE INTO mutePlayer (player, muteTime, reason, staff) VALUES (:player, :muteTime, :reason, :staff);");
				$muteInfo->bindValue(":player", $p->getName());
				$muteInfo->bindValue(":muteTime", $muteTime);
				$muteInfo->bindValue(":reason", $data[4]);
				$muteInfo->bindValue(":staff", $player->getName());
				$muteInfo->execute();
				$this->getServer()->broadcastMessage($this->prefixTitle . "§9 > §cPLAYER: §4" . $p->getName() . " §cHas been muted for: §4" . $data[4] . " §cBy: §4" . $player->getName());
			}
		});
		$plf->setTitle($this->prefixTitle);
		$plf->addInput("§eType the player name you want to mute!");
		$plf->addSlider("§eDays", 0, 30, 1);
		$plf->addSlider("§eHours", 0, 24, 1);
		$plf->addSlider("§eMinutes", 0, 60, 5);
		$plf->addInput("§ePlease type the reason");
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function unmute($player){
		$plf = $this->form->createSimpleForm(function (Player $player, string $data = null){
			if($data === null){
				return true;
			}
			$this->targetPlayer[$player->getName()] = $data;
			$this->muted($player);
		});
		$plf->setTitle($this->prefixTitle);
		$plf->setContent("§eTap the player name to unmute the player");
		$muteInfo = $this->db->query("SELECT * FROM mutePlayer;");
		$i = -1;
		while ($resultArr = $muteInfo->fetchArray(SQLITE3_ASSOC)) {
			$j = $i + 1;
			$mutePlayer = $resultArr['player'];
			$plf->addButton("§e$mutePlayer", -1, "", $mutePlayer);
			$i = $i + 1;
		}
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function muted($player){
		$plf = $this->form->createSimpleForm(function (Player $player, int $data = null){
			if($data === null){
				return true;
			}
			$banplayer = $this->targetPlayer[$player->getName()];
					$banInfo = $this->db->query("SELECT * FROM mutePlayer WHERE player = '$banplayer';");
					$array = $banInfo->fetchArray(SQLITE3_ASSOC);
					if (!empty($array)) {
						$this->db->query("DELETE FROM mutePlayer WHERE player = '$banplayer';");
						$player->sendMessage("You've unmuted the player!");
					}
					unset($this->targetPlayer[$player->getName()]);
		});
		$plf->setTitle($this->prefixTitle);
		$plf->setContent("§eAre you sure want to unmute?");
		$plf->addButton("Unmute!");
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function onLogin(PlayerPreLoginEvent $event){
		$player = $event->getPlayer();
		$playerName = $player->getName();
		$ban = $this->db->query("SELECT * FROM banPlayer WHERE player = '$playerName';");
		$array = $ban->fetchArray(SQLITE3_ASSOC);
		if(!empty($array)){
			$banTime = $array['banTime'];
			$reason = $array['reason'];
			$staff = $array['staff'];
			$time = time();
			if($banTime > $time){
				$remainingTime = $banTime - $time;
				$day = floor($remainingTime / 86400);
				$hourSeconds = $remainingTime % 86400;
				$hour = floor($hourSeconds / 3600);
				$minuteSec = floor($hourSeconds % 3600);
				$minute = floor($minuteSec / 60);
				$remainingSec = $minuteSec % 60;
				$second = ceil($remainingSec);
				$player->kick("§cYou've been banned by:§4 " . $staff . " §cFor:§4 " . $reason . "\n§cYou will available to join again in: Day(s): " . $day. " Hour(s): " . $hour . " Minutes: " . $minute . "\n Think your ban was a mistake? Contant us in discord!");
			} else {
				$this->db->query("DELETE FROM banPlayer WHERE player = '$playerName';");
			}
		}
	}

	public function onChat(PlayerChatEvent $event){
		$player = $event->getPlayer();
		$playerName = $player->getName();
		$ban = $this->db->query("SELECT * FROM mutePlayer WHERE player = '$playerName';");
		$array = $ban->fetchArray(SQLITE3_ASSOC);
		if(!empty($array)){
			$banTime = $array['muteTime'];
			$reason = $array['reason'];
			$staff = $array['staff'];
			$time = time();
			if($banTime > $time){
				$remainingTime = $banTime - $time;
				$day = floor($remainingTime / 86400);
				$hourSeconds = $remainingTime % 86400;
				$hour = floor($hourSeconds / 3600);
				$minuteSec = floor($hourSeconds % 3600);
				$minute = floor($minuteSec / 60);
				$remainingSec = $minuteSec % 60;
				$second = ceil($remainingSec);
				$event->setCancelled();
				$player->sendMessage("§cYou're still muted for: §4" . $reason . " §cUntil: §4" . $day . " Days" . $hour . " Hours" . $minute . " Minutes\n§cThink you're muted is a mistake? contant us in discord!");
			} else {
				$this->db->query("DELETE FROM mutePlayer WHERE player = '$playerName';");
			}
		}
	}

	public function warn($player){
		$plf = $this->form->createCustomForm(function (Player $player, array $data = null){
			if($data === null){
				return true;
			}
			$p = $this->getServer()->getPlayer($data[0]);
			if($p instanceof Player){
				$this->p = new Config($this->getDataFolder() . "warnings/" . strtolower($p->getName()), Config::YAML);
				$this->p->set("warnings", $this->p->get("warnings") + 1);
				$this->p->save();
				$this->getServer()->broadcastMessage($this->prefixTitle . " §cMODERATION §9> §cPlayer §4" . $p->getName() . " §cHas been warned because §4" . $data[1] . " §cAnd warned by §4" . $player->getName());
			}
		});
		$plf->setTitle($this->prefixTitle);
		$plf->addInput("§eType the player name you want to warn!");
		$plf->addInput("§eType the reason why this player is warned!");
		$plf->sendToPlayer($player);
		return $plf;
	}

	public function delwarn($player){
		$plf = $this->form->createCustomForm(function (Player $player, array $data = null){
			if($data === null){
				return true;
			}
			$p = $this->getServer()->getPlayer($data[0]);
			if($p instanceof Player){
				$this->p = new Config($this->getDataFolder() . "warnings/" . strtolower($p->getName()), Config::YAML);
				if($this->p->get("warnings") == 0){
					$player->sendMessage($this->prefixTitle . " §6This player has no warnings!");
				} else {
					$this->p->set("warnings", $this->p->get("warnings") - 1);
					$this->p->save();
					$p->sendMessage($this->prefixTitle . " §9> §eThank you for your apologize! your warning is now reduced!");
					$player->sendMessage($this->prefixTitle . " §9> §eYou just remove " . $p->getName() . " warning!");
				}
			}
		});
		$plf->setTitle($this->prefixTitle);
		$plf->addInput("§eType the player name you want to del his/her warn!");
		$plf->sendToPlayer($player);
		return $plf;
	}

}