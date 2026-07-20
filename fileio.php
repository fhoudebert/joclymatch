<?php
// fileio.php repond du JSON brut : le moindre octet parasite emis avant
// (BOM, espace ou ligne vide apres la balise fermante de localconf.php, notice PHP
// affichee...) se retrouve DEVANT le JSON et fait echouer JSON.parse()
// cote client ("unexpected end of data" si le corps etait vide,
// "unexpected character" sinon). On charge donc localconf.php sous
// tampon et on jette tout ce qu'il aurait pu ecrire : un localconf sain
// n'ecrit rien, donc aucun changement pour lui.
ob_start();
require "localconf.php";
ob_end_clean();

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
        // (echos de debug retires : les clients ignorent le corps de la
        // reponse de save, et renvoyer les donnees POST telles quelles
        // n'apportait rien)
        // Ecriture atomique (fichier temporaire puis rename()) : evite qu'un
        // load() concurrent ne lise un fichier a moitie ecrit.
        // Le repertoire de sauvegarde ($savePath, ex. "saves/") ne fait pas
        // partie du depot : sur un deploiement neuf il n'existe pas, et
        // fopen() echouait alors ("No such file or directory") suivi d'un
        // fatal error fputs(false, ...) -- la partie n'etait jamais ecrite.
        $dir = dirname($fn);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $fn.".tmp".uniqid();
        $fp = @fopen($tmp,"wt");
        if ($fp === false) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(array("error" => "cannot write save file"));
            exit;
        }
        fputs($fp,$_POST['gamedata']);
        fclose($fp);
        rename($tmp,$fn);
    }
    if(($_POST['gameioaction']=='load')){
        // Long-polling optionnel (voir js/control.js, LONG_POLL_ENABLED) :
        // si le client envoie sinceMtime, on attend jusqu'a ce que le fichier
        // change (ou soit cree) avant de repondre, plutot que de repondre
        // tout de suite -- borne a 20s, sous le max_execution_time habituel.
        // ABSENT DE LA REQUETE (comportement historique, tous les clients
        // actuels) -> ce bloc ne fait strictement rien, reponse immediate
        // comme avant.
        if (isset($_POST['sinceMtime']) && is_numeric($_POST['sinceMtime'])) {
            $since = (int)$_POST['sinceMtime'];
            $deadline = microtime(true) + 20;
            while (microtime(true) < $deadline) {
                clearstatcache(true, $fn);
                if (file_exists($fn) && filemtime($fn) > $since) break;
                usleep(300000);
            }
        }
        // file_exists() evite l'avertissement PHP de fopen() sur un match
        // jamais sauvegarde (ce warning, affiche avant meme le test de
        // reussite du fopen(), cassait le JSON.parse() cote client).
        // file_get_contents() (plutot que fopen+fgets, qui ne lit qu'UNE
        // ligne) evite aussi une troncature si gamedata contient un retour a
        // la ligne reel.
        if (file_exists($fn)){
            header('Content-Type: application/json');
            // Lu par le client en mode long-polling pour son prochain appel
            // (sinceMtime) -- ignore sans effet par un client qui ne
            // regarde pas cet en-tete (le corps de la reponse ne change pas).
            header('X-File-Mtime: ' . filemtime($fn));
            echo(file_get_contents($fn));
        } else {
            header('Content-Type: application/json');
            header('X-File-Mtime: 0');
            // Toujours du JSON valide, meme sans match : le client teste
            // deja data.matchDetails/data.matchdata avant usage, donc {}
            // est ignore comme l'etait le corps vide -- mais sans passer
            // par l'exception JSON.parse (console propre).
            echo("{}");
        }
    }
}


// chat
if ( isset($_POST['chatioaction']) && isset($_POST['gameid'])){

    $fn = chatfileName($_POST['gameid']);
    
    if($_POST['chatioaction']=='save' && isset($_POST['chatmsg'])){       
        // Meme protection que pour la sauvegarde de partie : creer le
        // repertoire s'il manque et ne pas ecrire sur un handle invalide.
        $dir = dirname($fn);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fp = @fopen($fn,"at");
        if ($fp === false) {
            http_response_code(500);
            echo("chat save failed");
        } else {
            fputs($fp,$_POST['chatmsg']."\n");
            fclose($fp);
            echo("chat saved : ok");
        }
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