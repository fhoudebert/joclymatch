<?php
require "localconf.php";

// gameid sert a construire un nom de fichier (fileName/chatfileName) : on le
// valide avant tout usage pour empecher une traversee de repertoire
// (ex. gameid=../../ailleurs/quelquechose). Les identifiants legitimes
// (generes cote client, ex. Date.now()+"-"+makeid(14)) sont deja uniquement
// alphanumeriques/tirets, donc ca ne change rien pour l'usage normal.
if (isset($_POST['gameid']) && !preg_match('/^[A-Za-z0-9_-]+$/', $_POST['gameid'])) {
    exit;
}

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
        // Ecriture atomique (fichier temporaire puis rename()) : evite qu'un
        // load() concurrent ne lise un fichier a moitie ecrit.
        $tmp = $fn.".tmp".uniqid();
        $fp = fopen($tmp,"wt");
        fputs($fp,$_POST['gamedata']);
        fclose($fp);
        rename($tmp,$fn);
    }
    if(($_POST['gameioaction']=='load')){
        // file_exists() evite l'avertissement PHP de fopen() sur un match
        // jamais sauvegarde (ce warning, affiche avant meme le test de
        // reussite du fopen(), cassait le JSON.parse() cote client).
        // file_get_contents() (plutot que fopen+fgets, qui ne lit qu'UNE
        // ligne) evite aussi une troncature si gamedata contient un retour a
        // la ligne reel.
        if (file_exists($fn)){
            header('Content-Type: application/json');
            echo(file_get_contents($fn));
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
        header('Content-Type: application/json');
        // Meme cause que pour le load() de partie plus haut : fopen() sur un
        // fichier absent emettait un avertissement PHP AVANT que le test
        // ($fp) ne puisse s'en apercevoir, polluant la reponse malgre le
        // else ci-dessous qui gere pourtant deja correctement ce cas.
        if (file_exists($fn)){
            $fp = fopen($fn,"rt");
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