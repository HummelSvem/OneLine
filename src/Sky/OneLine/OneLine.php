<?php

namespace Sky\OneLine;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\scheduler\PluginTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\item\enchantment\Enchantment;

class OneLine extends PluginBase implements Listener{
    public $cfg;
    public $mode = 0;
    public $prefix;
    public $arenaname;
    public $player;
    public $arenafile;
    public $particle;
    
    public function onEnable() {
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder().'/games');
        @mkdir($this->getDataFolder().'/players');
        if(!file_exists($this->getDataFolder().'config.yml')){
            $this->initConfig();
        }
        $this->cfg = new Config($this->getDataFolder().'config.yml', Config::YAML);
        
        $this->prefix = $this->cfg->get('prefix');
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new OLTask($this), 20);
        
        foreach($this->getServer()->getLevels() as $level){
            if($level instanceof Level){
                $level->setTime(0);
                $level->stopTime;
            }
        }
        
        $x = $this->cfg->get('x');
        $y = $this->cfg->get('y');
        $z = $this->cfg->get('z');
        $this->particle = new FloatingTextParticle(new Position($x, $y, $z, $this->getServer()->getDefaultLevel()), '');
        $this->getLogger()->info($this->prefix.TextFormat::WHITE.' by zGodSky enabled!');
    }
    
    public function initConfig(){
        $this->cfg = new Config($this->getDataFolder().'config.yml', Config::YAML);
        $this->cfg->set('prefix', '§7[§bOneLine§7]');
        $this->cfg->set('text', FALSE);
        $this->cfg->set('x', 0);
        $this->cfg->set('y', 0);
        $this->cfg->set('z', 0);
        $this->cfg->save();
    }
    
    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
        if($command == 'ol'){
            if(!$sender instanceof Player){
                return FALSE;
            }
            if(empty($args['0'])){
                $sender->sendMessage($this->prefix.TextFormat::WHITE.' /ol create <world>');
                return FALSE;
            }
            if($args['0'] == 'create'){
                if(empty($args['1'])){
                    $sender->sendMessage($this->prefix.TextFormat::WHITE.' /ol create <world>');
                    return FALSE;
                }
                if(!$this->getServer()->getLevelByName($args['1'])){
                    $sender->sendMessage($this->prefix.TextFormat::WHITE.' That world doesnt exist!');
                    return FALSE;
                }
                $this->mode = 1;
                $this->arenaname = $args['1'];
                $this->player = $sender->getName();
                $this->arenafile = new Config($this->getDataFolder().'/games/'.$args['1'].'.yml', Config::YAML);
                $this->arenafile->set('name', $args['1']);
                $this->arenafile->save();
                $x = $this->getServer()->getLevelByName($args['1'])->getSafeSpawn()->x;
                $y = $this->getServer()->getLevelByName($args['1'])->getSafeSpawn()->y;
                $z = $this->getServer()->getLevelByName($args['1'])->getSafeSpawn()->z;
                $level = $this->getServer()->getLevelByName($args['1']);
                $sender->teleport(new Position($x, $y, $z, $level));
                $sender->sendMessage($this->prefix.TextFormat::WHITE.' Please touch the spawn of the first player!');
				return TRUE;
            }
        }elseif($command == 'leave'){
            if(!$sender instanceof Player){
                return FALSE;
            }
            $player = $sender;
            if($this->isPlaying($player)){
                $arenaname = $this->getArena($player);
                $arenafile = new Config($this->getDataFolder().'/games/'.$arenaname.'.yml', Config::YAML);
                $mode = $arenafile->get('mode');
                if($mode == 'waiting'){
                    $arenafile->set('playercount', 0);
                    $arenafile->save();
                    $sender->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    $sender->getLevel()->addSound(new EndermanTeleportSound($sender));
                    $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                    foreach($tiles as $tile){
                        if($tile instanceof Sign){
                            $text = $tile->getText();
                            if($text['0'] == $this->prefix){
                                if(TextFormat::clean($text['1']) == $arenaname){
                                    $tile->setText(
                                            $this->prefix,
                                            $arenaname,
                                            TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                            TextFormat::GREEN.'JOIN'
                                        );
                                }
                            }
                        }
                    }
                }else{
                    $sender->sendMessage($this->prefix.TextFormat::WHITE.' Please dont do that ingame!');
                    return FALSE;
                }
                return TRUE;
            }else{
                $sender->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                $sender->getLevel()->addSound(new EndermanTeleportSound($sender));
                return TRUE;
            }
        }elseif($command == 'stats'){
            if(!$sender instanceof Player){
                return FALSE;
            }
            if(empty($args['0'])){
                $sender->sendMessage($this->prefix);
                $sender->sendMessage(TextFormat::WHITE.'/stats show <player>');
                $sender->sendMessage(TextFormat::WHITE.'/stats my');
                $sender->sendMessage(TextFormat::WHITE.'/stats reset');
                return TRUE;
            }
            if($args['0'] == 'show'){
                if(empty($args['1'])){
                    $sender->sendMessage($this->prefix.TextFormat::WHITE.' /stats show <player>');
                    return TRUE;
                }
                if(!file_exists($this->getDataFolder().'/players/'. strtolower($args['1']).'.yml')){
                    $sender->sendMessage($this->prefix.TextFormat::WHITE.' /stats show <player>');
                    return TRUE;
                }
                $file = new Config($this->getDataFolder().'/players/'. strtolower($args['1']).'.yml', Config::YAML);
                $kills = $file->get('kills');
                $deaths = $file->get('deaths');
                $kd = $kills / $deaths;
                $sender->sendMessage(TextFormat::RED.'Stats of '.$args['1']);
                $sender->sendMessage(TextFormat::GREEN.'Kills: '.TextFormat::GRAY.$kills);
                $sender->sendMessage(TextFormat::GREEN.'Deaths: '.TextFormat::GRAY.$deaths);
                $sender->sendMessage(TextFormat::GREEN.'KD: '.TextFormat::GRAY. round($kd, 2));
				return TRUE;
            }elseif($args['0'] == 'my'){
                $file = new Config($this->getDataFolder().'/players/'. strtolower($sender->getName()).'.yml', Config::YAML);
                $kills = $file->get('kills');
                $deaths = $file->get('deaths');
                $kd = $kills / $deaths;
                $sender->sendMessage(TextFormat::RED.'Stats of you');
                $sender->sendMessage(TextFormat::GREEN.'Kills: '.TextFormat::GRAY.$kills);
                $sender->sendMessage(TextFormat::GREEN.'Deaths: '.TextFormat::GRAY.$deaths);
                $sender->sendMessage(TextFormat::GREEN.'KD: '.TextFormat::GRAY. round($kd, 2));
				return TRUE;
            }elseif($args['0'] == 'reset'){
                $file = new Config($this->getDataFolder().'/players/'. strtolower($sender->getName()).'.yml', Config::YAML);
                $count = $file->get('resetter');
                if($count > 0){
                    if(!empty($args['1'])){
                        $file->set('resetter', $count - 1);
                        $file->set('kills', 1);
                        $file->set('deaths', 1);
                        $file->save();
                        $sender->sendMessage($this->prefix.TextFormat::WHITE.' Stats resetted successfully!');
						return TRUE;
                    }else{
                        $sender->sendMessage($this->prefix.TextFormat::WHITE.' Confirm: /stats reset yes');
						return FALSE;
                    }
                }else{
                    $sender->sendMessage($this->prefix.TextFormat::WHITE.' Please buy resetters in our shop!');
					return FALSE;
                }
            }
        }elseif($command == 'addtickets'){
            if(empty($args['0'])){
                $sender->sendMessage(TextFormat::WHITE.'/addtickets <player> <count>');
                return TRUE;
            }
            if(empty($args['1'])){
                $sender->sendMessage(TextFormat::WHITE.'/addtickets <player> <count>');
                return TRUE;
            }
            $file = new Config($this->getDataFolder().'/players/'.strtolower($args['0']).'.yml');
            $ticks = $file->get('resetter');
            $file->set('resetter', $ticks + $args['1']);
            $file->save();
            $sender->sendMessage('SUCCESS!');
			return TRUE;
        }elseif($command == 'hub'){
            if(!$sender instanceof Player){
				return true;
			}
			$sender->transfer('EnjoyTheView.tk', 19132);
			return true;
        }
    }
    
    public function onInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $playername = $player->getName();
        $block = $event->getBlock();
        $tile = $player->getLevel()->getTile($block);
        
        if($this->mode > 0){
            $mode = $this->mode;
            if(!$this->player == $playername){
                return;
            }
            if($mode == 1){
                if($block->getId() == 0){
                    return;
                }
                $x = $block->x + 0.3;
                $y = $block->y + 1.2;
                $z = $block->z + 0.5;
                $this->arenafile->set('y', $y);
                $this->arenafile->set('1x', $x);
                $this->arenafile->set('1z', $z);
                $this->arenafile->save();
                $this->mode = 2;
                $player->sendMessage($this->prefix.TextFormat::WHITE.' Please touch the spawn of the second player!');
            }elseif($mode == 2){
                if($block->getId() == 0){
                    return;
                }
                $x = $block->x + 0.5;
                $z = $block->z + 0.5;
                $this->arenafile->set('2x', $x);
                $this->arenafile->set('2z', $z);
                $this->arenafile->set('playercount', 0);
                $this->arenafile->set('winner', NULL);
                $this->arenafile->set('counter', 0);
                $this->arenafile->set('mode', 'waiting');
                $this->arenafile->save();
                $this->mode = 3;
                $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                $player->sendMessage($this->prefix.TextFormat::WHITE.' Please touch the sign of the arena!');
            }elseif($mode == 3){
                if(!$tile instanceof Sign){
                    return;
                }
                $tile->setText(
                        $this->prefix,
                        $this->arenaname,
                        TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                        TextFormat::GREEN.'JOIN'
                        );
                $this->mode = 0;
                $this->player = NULL;
                $this->arenafile = NULL;
                $player->sendMessage($this->prefix.TextFormat::WHITE.' Successfully created '.$this->arenaname.'!');
                $this->arenaname = NULL;
            }
        }else{
            if($tile instanceof Sign){
                $text = $tile->getText();
                if($text['0'] == $this->prefix){
                    if(TextFormat::clean($text['1']) == 'JOIN'){
                        $atj = $this->getArenaToJoin();
                        if($atj == 'no'){
                            $player->sendMessage($this->prefix.TextFormat::WHITE.' All arenas are full!');
                        }else{
                            $arenafile = new Config($this->getDataFolder().'/games/'.$atj.'.yml', Config::YAML);
                            $playercount = $arenafile->get('playercount');
                            if($playercount == 0){
                                $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                                foreach($tiles as $t){
                                    if($t instanceof Sign){
                                        $txt = $t->getText();
                                        if($txt['0'] == $this->prefix){
                                            if(TextFormat::clean($txt['1']) == $atj){
                                                $t->setText(
                                                        $this->prefix,
                                                        $atj,
                                                        TextFormat::GRAY.'1'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                                        TextFormat::GREEN.'JOIN'
                                                    );
                                            }
                                        }
                                    }
                                }
                                
                                $x = $arenafile->get('1x');
                                $y = $arenafile->get('y');
                                $z = $arenafile->get('1z');
                                $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($atj)));
                                $player->getLevel()->addSound(new EndermanTeleportSound($player));
                                $arenafile->set('playercount', 1);
                                $arenafile->save();
                            }elseif($playercount == 1){
                                $x = $arenafile->get('2x');
                                $y = $arenafile->get('y');
                                $z = $arenafile->get('2z');
                                $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($atj)));
                                $player->getLevel()->addSound(new EndermanTeleportSound($player));
                                $arenafile->set('playercount', 2);
                                $arenafile->set('counter', 3);
                                $arenafile->set('mode', 'starting');
                                $arenafile->save();
                                
                                $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                                foreach($tiles as $t){
                                    if($t instanceof Sign){
                                        $txt = $t->getText();
                                        if($txt['0'] == $this->prefix){
                                            if(TextFormat::clean($txt['1']) == $atj){
                                                $t->setText(
                                                        $this->prefix,
                                                        $atj,
                                                        TextFormat::GRAY.'2'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                                        TextFormat::RED.'INGAME'
                                                    );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        return;
                    }
                    $arenaname = TextFormat::clean($text['1']);
                    $arenafile = new Config($this->getDataFolder().'/games/'.$arenaname.'.yml');
                    $playercount = $arenafile->get('playercount');
                    if($playercount == 0){
                        $x = $arenafile->get('1x');
                        $y = $arenafile->get('y');
                        $z = $arenafile->get('1z');
                        $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($arenaname)));
                        $player->getLevel()->addSound(new EndermanTeleportSound($player));
                        $arenafile->set('playercount', $playercount + 1);
                        $arenafile->save();
                        
                        $tile->setText(
                                $this->prefix,
                                $arenaname,
                                TextFormat::GRAY.'1'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                TextFormat::GREEN.'JOIN'
                            );
                        
                    }elseif($playercount == 1){
                        $x = $arenafile->get('2x');
                        $y = $arenafile->get('y');
                        $z = $arenafile->get('2z');
                        $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($arenaname)));
                        $player->getLevel()->addSound(new EndermanTeleportSound($player));
                        $arenafile->set('playercount', $playercount + 1);
                        $arenafile->set('counter', 3);
                        $arenafile->set('mode', 'starting');
                        $arenafile->save();
                        
                        $tile->setText(
                                $this->prefix,
                                $arenaname,
                                TextFormat::GRAY.'2'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                TextFormat::RED.'INGAME'
                            );
                        
                    }else{
                        $player->sendMessage($this->prefix.TextFormat::WHITE.' That arena is full!');
                        return;
                    }
                }
            }
        }
    }
    
    public function getArenaToJoin(){
        $dir = $this->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        $config = new Config($this->getDataFolder().'config.yml', Config::YAML);
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            $arenafile = new Config($this->getDataFolder().'/games/'.$gamename.'.yml');
            $playercount = $arenafile->get('playercount');
            if($playercount < 2){
                return $gamename;
            }
        }
        return 'no';
    }
    
    public function isPlaying(Player $player){
        $dir = $this->getDataFolder() . "/games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $worldname = pathinfo($g, PATHINFO_FILENAME);
            if($player->getLevel()->getName() == $worldname){
                return TRUE;
            }
        }
    }
    
    public function getArena(Player $player){
        $dir = $this->getDataFolder() . "/games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $worldname = pathinfo($g, PATHINFO_FILENAME);
            if($player->getLevel()->getName() == $worldname){
                return $worldname;
            }
        }
    }
    
    public function getWinner(Player $loser){
        $players = $loser->getLevel()->getPlayers();
        $losername = $loser->getName();
        foreach($players as $player){
            if(!$player instanceof Player){
                return;
            }
            if($losername !== $player->getName()){
                return $player->getName();
            }
        }
    }
    
    public function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if($this->isPlaying($player)){
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder().'/games/'.$arenaname.'.yml', Config::YAML);
            $playercount = $arenafile->get('playercount');
            $mode = $arenafile->get('mode');
            if($playercount > 0){
                if($mode == 'waiting' or $mode == 'starting'){
                    if($event->getFrom()->x !== $event->getTo()->x){
						$event->setCancelled(TRUE);
					}
					if($event->getFrom()->z !== $event->getTo()->z){
						$event->setCancelled(TRUE);
					}
                }elseif($mode == 'ingame'){
                    $ay = $arenafile->get('y') - 0.5;
                    $py = $player->y;
                    if($py < $ay){
                        if($arenafile->get('end') == false){
                            return;
                        }
                        $arenafile->set('winner', $this->getWinner($player));
                        $arenafile->set('counter', 0);
                        $arenafile->set('end', false);
                        $arenafile->save();
                    }
                }
            }
        }
    }
    
    public function onEntityDamage(EntityDamageEvent $event){
        if($event->getCause() == EntityDamageEvent::CAUSE_FALL){
            $event->setCancelled(TRUE);
        }elseif($event instanceof EntityDamageByEntityEvent){
            $damager = $event->getDamager();
            $entity = $event->getEntity();
            if($damager instanceof Player && $entity instanceof Player){
                if(!$this->isPlaying($damager)){
                    $event->setCancelled(TRUE);
                    return;
                }
                if(!$this->isPlaying($entity)){
                    $event->setCancelled(TRUE);
                    return;
                }
                $damagerinv = $damager->getInventory();
                $iteminhand = $damagerinv->getItemInHand()->getId();
                if($iteminhand == 280){
                    $event->setKnockBack(0.6);
                    $event->setDamage(0);
                }
            }
        }
    }
    
    public function onDisable(){
        $dir = $this->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        $config = new Config($this->getDataFolder().'config.yml', Config::YAML);
        $prefix = $config->get('prefix');
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            $arenafile = new Config($this->getDataFolder().'/games/'.$gamename.'.yml', Config::YAML);
            
            $arenafile->set('playercount', 0);
            $arenafile->set('winner', NULL);
            $arenafile->set('counter', 0);
            $arenafile->set('mode', 'waiting');
            $arenafile->set('end', FALSE);
            $arenafile->save();
            
            $tiles = $this->getServer()->getDefaultLevel()->getTiles();
            foreach($tiles as $tile){
                if($tile instanceof Sign){
                    $text = $tile->getText();
                    if($text['0'] == $prefix){
                        if(TextFormat::clean($text['1']) == $gamename){
                            $tile->setText(
                                    $prefix,
                                    $gamename,
                                    TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                    TextFormat::GREEN.'GREEN'
                                    );
                        }
                    }
                }
            }
        }
    }
    
    public function preLogin(PlayerLoginEvent $event){
        $player = $event->getPlayer();
        $player->teleport(new Position($this->getServer()->getDefaultLevel()->getSafeSpawn()->x, $this->getServer()->getDefaultLevel()->getSafeSpawn()->y, $this->getServer()->getDefaultLevel()->getSafeSpawn()->z, $this->getServer()->getDefaultLevel()));
        $player->setGamemode(2);
        $player->getInventory()->clearAll();
    }
    
    public function onQuit(PlayerQuitEvent $event){
        $event->setQuitMessage('');
        $player = $event->getPlayer();
        $playername = $player->getName();
        if($this->isPlaying($player)){
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder().'/games/'.$arenaname.'.yml', Config::YAML);
            $playercount = $arenafile->get('playercount');
            $mode = $arenafile->get('mode');
            if($mode == 'waiting'){
                $arenafile->set('playercount', 0);
                $arenafile->save();
                $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                foreach($tiles as $tile){
                    if($tile instanceof Sign){
                        $text = $tile->getText();
                        if($text['0'] == $this->prefix){
                            if(TextFormat::clean($text['1']) == $arenaname){
                                $tile->setText(
                                        $this->prefix,
                                        $arenaname,
                                        TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                        TextFormat::GREEN.'JOIN'
                                        );
                            }
                        }
                    }
                }
            }elseif($mode == 'starting'){
                $arenafile->set('playercount', 0);
                $arenafile->set('mode', 'waiting');
                $arenafile->set('counter', 0);
                $arenafile->set('end', FALSE);
                $arenafile->save();
                
                $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                foreach($tiles as $tile){
                    if($tile instanceof Sign){
                        $text = $tile->getText();
                        if($text['0'] == $this->prefix){
                            if(TextFormat::clean($text['1']) == $arenaname){
                                $tile->setText(
                                        $this->prefix,
                                        $arenaname,
                                        TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                        TextFormat::GREEN.'JOIN'
                                        );
                            }
                        }
                    }
                }
                
                $players = $player->getLevel()->getPlayers();
                foreach($players as $p){
                    if(!$p instanceof Player){
                        return;
                    }
                    $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    $p->sendMessage($this->prefix.TextFormat::WHITE.' '.$player->getName().' left the game!');
                }
            }elseif($mode == 'ingame'){
                $arenafile->set('playercount', 0);
                $arenafile->set('mode', 'waiting');
                $arenafile->set('winner', NULL);
                $arenafile->set('counter', 0);
                $arenafile->set('end', FALSE);
                $arenafile->save();
                
                $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                foreach($tiles as $tile){
                    if($tile instanceof Sign){
                        $text = $tile->getText();
                        if($text['0'] == $this->prefix){
                            if(TextFormat::clean($text['1']) == $arenaname){
                                $tile->setText(
                                        $this->prefix,
                                        $arenaname,
                                        TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                        TextFormat::GREEN.'JOIN'
                                        );
                            }
                        }
                    }
                }
                
                $players = $player->getLevel()->getPlayers();
                foreach($players as $p){
                    if(!$p instanceof Player){
                        return;
                    }
                    $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    $p->sendMessage($this->prefix.TextFormat::WHITE.' '.$player->getName().' left the game!');
                    $p->sendMessage(TextFormat::RED.'Nobody wins!');
                }
            }
        }
    }
    
    public function initPlayer(Player $player){
        $playerfile = new Config($this->getDataFolder().'/players/'. strtolower($player->getName()).'.yml', Config::YAML);
        $playerfile->set('kills', 1);
        $playerfile->set('deaths', 1);
        $playerfile->set('resetter', 1);
        $playerfile->save();
    }
    
    public function onJoin(PlayerJoinEvent $event){
        $event->setJoinMessage('');
        $player = $event->getPlayer();
        $playername = $player->getName();
        if(!file_exists($this->getDataFolder().'/players/'. strtolower($playername).'.yml')){
            $this->initPlayer($player);
        }
        if($this->cfg->get('text') == TRUE){
            $playerfile = new Config($this->getDataFolder().'/players/'.strtolower($playername).'.yml', Config::YAML);
            $x = $this->cfg->get('x');
            $y = $this->cfg->get('y');
            $z = $this->cfg->get('z');
            $dir = $this->getDataFolder() . "players/";
            $players = array_slice(scandir($dir), 2);
            $kd1 = 0;
            $bp1 = NULL;
            $kd2 = 0;
            $bp2 = NULL;
            $kd3 = 0;
            $bp3 = NULL;
            $kd4 = 0;
            $bp4 = NULL;
            $kd5 = 0;
            $bp5 = NULL;
            foreach($players as $p){
                $pname = pathinfo($p, PATHINFO_FILENAME);
                $pfile = new Config($this->getDataFolder().'/players/'. strtolower($pname).'.yml', Config::YAML);
                $k = $pfile->get('kills');
                $d = $pfile->get('deaths');
                $kd = $k / $d;
                if($kd > $kd1){
                    $kd1 = $kd;
                    $bp1 = $pname;
                }elseif($kd > $kd2){
                    $kd2 = $kd;
                    $bp2 = $pname;
                }elseif($kd > $kd3){
                    $kd3 = $kd;
                    $bp3 = $pname;
                }elseif($kd > $kd4){
                    $kd4 = $kd;
                    $bp4 = $pname;
                }elseif($kd > $kd5){
                    $kd5 = $kd;
                    $bp5 = $pname;
                }
            }
            $kd = $playerfile->get('kills') / $playerfile->get('deaths');
            $particle = new FloatingTextParticle(new Position($x, $y, $z, $this->getServer()->getDefaultLevel()), TextFormat::GREEN."TOP 5 PLAYERS - KD:\n".
                    TextFormat::GOLD."1. ".TextFormat::WHITE.$bp1." - ". round($kd1, 2)."\n".
                    TextFormat::GRAY."2. ".TextFormat::WHITE.$bp2." - ".round($kd2, 2)."\n".
                    TextFormat::GRAY."3. ".TextFormat::WHITE.$bp3." - ".round($kd3, 2)."\n".
                    TextFormat::GRAY."4. ".TextFormat::WHITE.$bp4." - ".round($kd4, 2)."\n".
                    TextFormat::GRAY."5. ".TextFormat::WHITE.$bp5." - ".round($kd5, 2)."\n".
                    TextFormat::AQUA."Your KD: ".TextFormat::ITALIC.TextFormat::YELLOW.round($kd, 2));
            $player->getLevel()->addParticle($particle, array($player));
            
        }
    }
    
    public function onSignChange(SignChangeEvent $event){
        $lines = $event->getLines();
        if($lines['0'] == '[oljoin]'){
            $event->setLine(0, $this->prefix);
            $event->setLine(1, TextFormat::GREEN.'JOIN');
            $event->setLine(2, TextFormat::WHITE.'Finds an');
            $event->setLine(3, TextFormat::WHITE.'empty arena.');
        }
    }
}

class OLTask extends PluginTask{
    public function __construct(\pocketmine\plugin\Plugin $owner) {
        $this->plugin = $owner;
        parent::__construct($owner);
    }
    
    public function onRun(int $currentTick){
        foreach ($this->getOwner()->getServer()->getOnlinePlayers() as $player){
            if(!$player instanceof Player){
                return;
            }
            $player->setHealth(20);
            $player->setFood(20);
        }
        
        $dir = $this->plugin->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        $config = new Config($this->getOwner()->getDataFolder().'config.yml', Config::YAML);
        $prefix = $config->get('prefix');
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            if (!$this->getOwner()->getServer()->getLevelByName($gamename) instanceof Level) {
                $this->getOwner()->getServer()->loadLevel($gamename);
            }
            $arenafile = new Config($this->getOwner()->getDataFolder().'/games/'.$gamename.'.yml', Config::YAML);
            $defaultlevel = $this->getOwner()->getServer()->getDefaultLevel();
            
            $mode = $arenafile->get('mode');
            $playercount = $arenafile->get('playercount');
            if($mode == 'waiting'){
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter + 1);
                $arenafile->save();
                
                if($counter == 30){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->getLevel()->addSound(new ClickSound($player));
                        $player->sendMessage($prefix.TextFormat::WHITE.' Waiting for 2 players!');
                    }
                    
                    $arenafile->set('counter', 0);
                    $arenafile->save();
                }
            }elseif($mode == 'starting'){
                if($playercount == 1){
                    $arenafile->set('mode', 'waiting');
                    $arenafile->save();
                    
                    $tiles = $defaultlevel->getTiles();
                    foreach($tiles as $tile){
                        if(!$tile instanceof Sign){
                            return;
                        }
                        $text = $tile->getText();
                        if($text['0'] == 'prefix'){
                            if(TextFormat::clean($text['1']) == $gamename){
                                $tile->setText(
                                        $prefix,
                                        $gamename,
                                        TextFormat::GRAY.'1'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                        TextFormat::GREEN.'JOIN'
                                    );
                            }
                        }
                    }
                }elseif($playercount == 0){
                    $arenafile->set('mode', 'waiting');
                    $arenafile->save();
                    
                    $tiles = $defaultlevel->getTiles();
                    foreach($tiles as $tile){
                        if(!$tile instanceof Sign){
                            return;
                        }
                        $text = $tile->getText();
                        if($text['0'] == 'prefix'){
                            if(TextFormat::clean($text['1']) == $gamename){
                                $tile->setText(
                                        $prefix,
                                        $gamename,
                                        TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                        TextFormat::GREEN.'JOIN'
                                    );
                            }
                        }
                    }
                }
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();
                
                $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                foreach($players as $player){
                    if(!$player instanceof Player){
                        return;
                    }
                    if(!$counter == 0){
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                    $player->sendPopup(TextFormat::AQUA.$counter);
                }
                
                if($counter == 0){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->getInventory()->clearAll();
                        $item = Item::get(280, 0, 1);
                        $item->setCustomName(TextFormat::GOLD.TextFormat::ITALIC.'Stick');
                        $player->getInventory()->setItem(0, $item, TRUE);
                        $player->sendPopup(TextFormat::GREEN.'GO!');
                        $player->sendMessage($prefix.TextFormat::WHITE.' You have 2 minutes to kill your opponent!');
                        $player->getLevel()->addSound(new \pocketmine\level\sound\EndermanTeleportSound($player));
                    }
                    $arenafile->set('mode', 'ingame');
                    $arenafile->set('counter', 120);
                    $arenafile->save();
                }
            }elseif($mode == 'ingame'){
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();
                
                if($counter == 60){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 1 minute');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 15){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 15 seconds');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 5){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 5 seconds');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 4){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 4 seconds');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 3){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 3 seconds');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 2){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 2 seconds');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 1){
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        $player->sendMessage($prefix.TextFormat::WHITE.' Remaining time: 1 seconds');
                        $player->getLevel()->addSound(new ClickSound($player));
                    }
                }
                if($counter == 0){    
                    //RESET SIGNS
                    $tiles = $this->getOwner()->getServer()->getDefaultLevel()->getTiles();
                    foreach($tiles as $tile){
                        if($tile instanceof Sign){
                            $text = $tile->getText();
                            if($text['0'] == $prefix){
                                if(TextFormat::clean($text['1']) == $gamename){
                                    $tile->setText(
                                            $prefix,
                                            $gamename,
                                            TextFormat::GRAY.'0'.TextFormat::BLACK.' / '.TextFormat::RED.'2',
                                            TextFormat::GREEN.'JOIN'
                                        );
                                }
                            }
                        }
                    }
                    
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach($players as $player){
                        if(!$player instanceof Player){
                            return;
                        }
                        
                        $player->teleport($this->getOwner()->getServer()->getDefaultLevel()->getSafeSpawn());
                        $player->getInventory()->clearAll();
                        $playerfile = new Config($this->getOwner()->getDataFolder().'/players/'. strtolower($player->getName()).'.yml', Config::YAML);
                        if(empty($arenafile->get('winner'))){
                            $player->sendMessage($prefix.TextFormat::WHITE.' Nobody wins!');
                        }else{
                            $winner = $arenafile->get('winner');
                            $playername = $player->getName();
                            $playerfile = new Config($this->getOwner()->getDataFolder().'/players/'. strtolower($playername).'.yml', Config::YAML);
                            $kills = $playerfile->get('kills');
                            $deaths = $playerfile->get('deaths');
                            if($winner == $playername){
                                $playerfile->set('kills', $kills + 1);
                                $playerfile->save();
                                $k = $kills + 1;
                                $kd = $k / $deaths;
                                $player->sendMessage($prefix.TextFormat::WHITE.' K/D: '. round($kd, 2));
                                $player->addTitle(TextFormat::GREEN.'You won!');
                            }else{
                                $playerfile->set('deaths', $deaths + 1);
                                $playerfile->save();
                                $d = $deaths + 1;
                                $kd = $kills / $d;
                                $player->sendMessage($prefix.TextFormat::WHITE.' K/D: '.round($kd, 2));
                                $player->addTitle(TextFormat::RED.' You lost!');
                            }
                        }
                    }
                    $arenafile->set('mode', 'waiting');
                    $arenafile->set('playercount', 0);
                    $arenafile->set('winner', NULL);
                    $arenafile->set('counter', 0);
                    $arenafile->set('end', false);
                    $arenafile->save();
                }
            }
        }
    }
}