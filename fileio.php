<?php
require "localconf.php";

function fileName($matchId){
    global $savePath;
    return $savePath.$matchId.".txt" ;
}

function chatfileName($matchId){
    global $savePath;
    return $savePath.$matchId."-chat.txt" ;
}

// games
if ( isset($_POST['gameioaction']) && isset($_POST['gameid'])){

    $fn = fileName($_POST['gameid']);
    
    if($_POST['gameioaction']=='save' && isset($_POST['gamedata'])){       
        echo("gameaction : ".$_POST['gameioaction']);
        echo("gameid : ".$_POST['gameid']);
        echo("data : ".$_POST['gamedata']);
        $fp = fopen($fn,"wt");
        fputs($fp,$_POST['gamedata']);
        fclose($fp);
    }
    if(($_POST['gameioaction']=='load')){
        $fp = fopen($fn,"rt");
        if ($fp){
            $gamedata = fgets($fp);
            fclose($fp);
            echo($gamedata);
        }
    }
}


// chat
if ( isset($_POST['chatioaction']) && isset($_POST['gameid'])){

    $fn = chatfileName($_POST['gameid']);
    
    if($_POST['chatioaction']=='save' && isset($_POST['chatmsg'])){       
        echo("chataction : ".$_POST['chatioaction']);
        echo("gameid : ".$_POST['gameid']);
        echo("data : ".$_POST['chatmsg']);
        $fp = fopen($fn,"at");
        fputs($fp,$_POST['chatmsg']."\n");
        fclose($fp);
        echo("chat saved : ok");
    }
    if(($_POST['chatioaction']=='load')){
        $fp = fopen($fn,"rt");
        if ($fp){
            while($chatdata = fgets($fp)){
                $chatdata = substr($chatdata, 0, -1); // to remove the \n
                $msgs[]=$chatdata;
            }
            fclose($fp);
            echo("{\"messages\":[".join(",",$msgs)."]}");        
        }else{
            echo("{}");
        }
    }
}



?>