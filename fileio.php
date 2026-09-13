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

// Reglages, tous surchargeables depuis localconf.php.
//
// UNE PARTIE ABANDONNEE NE DOIT PAS RESTER INDEFINIMENT. Jusqu'ici rien
// n'effacait rien : les fichiers de partie ET de chat s'accumulaient sans
// limite sur l'hebergement, et le seul moyen d'en reprendre le controle etait
// d'aller les supprimer a la main. 30 jours laissent le temps d'une partie par
// correspondance ; c'est la valeur retenue par match.php de mogichex, qui
// resout le meme probleme.
if (!isset($saveTTL)) {
    $saveTTL = 30 * 24 * 3600;
}
// Un etat de partie complet reste petit, mais rien ne le bornait : n'importe
// qui pouvait remplir le disque en un POST. La meme limite que match.php.
if (!isset($saveMaxBytes)) {
    $saveMaxBytes = 1048576;
}
// Le fichier de chat est en AJOUT : sans borne il grossit tant que la partie
// dure. Celle-ci porte sur le fichier entier, pas sur un message.
if (!isset($chatMaxBytes)) {
    $chatMaxBytes = 262144;
}

// DIALECTE match.php (mogichex) : action / mid / data.
//
// Trois applications parlent a ce relai -- joclymatch, Tabulon et mogichex --
// et les deux premieres emploient gameioaction/gameid/gamedata quand la
// troisieme emploie action/mid/data. Ce sont les MEMES operations : deposer un
// texte sous un identifiant, le relire, l'effacer. Les traduire ici, en un
// seul endroit et avant tout usage, evite d'ajouter une branche a chacune des
// vingt lectures de $_POST qui suivent -- et de devoir s'en souvenir a la
// prochaine.
//
// Le dialecte d'origine reste prioritaire : si gameioaction ou chatioaction
// est present, on ne touche a rien. Un client qui enverrait les deux obtient
// donc exactement ce qu'il obtenait avant.
$mogichexDialect = false;
if (!isset($_POST['gameioaction']) && !isset($_POST['chatioaction']) && isset($_POST['action'])) {
    $mogichexDialect = true;
    $_POST['gameioaction'] = $_POST['action'];
    if (isset($_POST['mid']))   $_POST['gameid']     = $_POST['mid'];
    if (isset($_POST['data']))  $_POST['gamedata']   = $_POST['data'];
    // L'attente longue porte le meme sens des deux cotes ; elle reste soumise
    // a $enableLongPolling, donc un serveur qui ne l'active pas repond tout de
    // suite -- ce qui est exactement le repli que ces clients savent deja
    // traiter.
    if (isset($_POST['since'])) $_POST['sinceMtime'] = $_POST['since'];
}

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

// Menage opportuniste, BORNE : il n'y a pas de cron sur un hebergement
// mutualise, donc le seul moment ou l'on peut faire le tri est une requete
// ordinaire. On en profite pour regarder quelques fichiers -- pas tout le
// repertoire, sous peine de faire payer a un joueur le menage de mille
// parties. Sur un serveur actif, quarante par requete suffisent largement a
// suivre le rythme des creations.
//
// Les deux familles de fichiers sont balayees ensemble : un "-chat.txt" est
// aussi un ".txt", et le laisser derriere serait garder la conversation d'une
// partie qui n'existe plus -- exactement ce qu'on cherche a eviter.
function sweepOldFiles(){
    global $savePath, $saveTTL;
    if (!is_dir($savePath)) {
        return;
    }
    $dh = @opendir($savePath);
    if (!$dh) {
        return;
    }
    $seen = 0;
    $cutoff = time() - $saveTTL;
    while (($f = readdir($dh)) !== false && $seen < 40) {
        // Les temporaires d'une ecriture atomique interrompue (le fichier
        // s'appelle <partie>.txt.tmp<unique>) ne portent pas l'extension et
        // n'etaient donc effaces par personne : une coupure au mauvais moment
        // laissait un fichier a vie. La borne de temps est la meme, largement
        // au-dela de la duree d'un rename().
        if (strpos($f, '.tmp') !== false) {
            $seen++;
            if (@filemtime($savePath . $f) < $cutoff) {
                @unlink($savePath . $f);
            }
            continue;
        }
        if (substr($f, -4) !== '.txt') {
            continue;
        }
        $seen++;
        $p = $savePath . $f;
        if (@filemtime($p) < $cutoff) {
            @unlink($p);
        }
    }
    closedir($dh);
}

// games
if ( isset($_POST['gameioaction']) && isset($_POST['gameid'])){

    $fn = fileName($_POST['gameid']);
    
    if($_POST['gameioaction']=='save' && isset($_POST['gamedata'])){
        if (strlen($_POST['gamedata']) > $saveMaxBytes) {
            http_response_code(413);
            header('Content-Type: application/json');
            echo json_encode(array("error" => "payload too large"));
            exit;
        }
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
        sweepOldFiles();
    }

    // Suppression explicite, pour un client qui sait que la partie est finie.
    // Complement du menage par anciennete, pas remplacement : le cas courant
    // reste la partie abandonnee en silence, que personne ne vient clore --
    // c'est le TTL qui s'en occupe. Les deux fichiers partent ensemble : le
    // chat d'une partie effacee n'a plus de sens.
    if($_POST['gameioaction']=='drop'){
        @unlink($fn);
        @unlink(chatfileName($_POST['gameid']));
        header('Content-Type: application/json');
        echo json_encode(array("ok" => true));
        exit;
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
            // Meme valeur sous le nom que lit un client mogichex. Emis pour
            // tout le monde plutot que sous condition : un en-tete de plus ne
            // gene personne, et une condition de plus se serait oubliee.
            header('X-Match-Mtime: ' . filemtime($fn));
            echo(file_get_contents($fn));
        } else {
            header('Content-Type: application/json');
            header('X-File-Mtime: 0');
            header('X-Match-Mtime: 0');
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
        // UN MESSAGE EST UNE LIGNE, et le fichier est relu ligne par ligne
        // avant d'etre recolle en JSON par join(",", ...). Un saut de ligne
        // DANS le message couperait donc le JSON de l'auteur en deux
        // fragments invalides, et la reponse du chat deviendrait illisible
        // POUR LES DEUX JOUEURS -- durablement, puisque le fichier est en
        // ajout. Le client de joclymatch envoie du JSON.stringify, qui n'en
        // emet jamais ; un autre client, si. On refuse plutot que d'abimer le
        // fil de facon irreversible.
        if (strpos($_POST['chatmsg'], "\n") !== false || strpos($_POST['chatmsg'], "\r") !== false) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(array("error" => "chat message must be a single line"));
            exit;
        }
        // Le fichier est en ajout : sans borne il grossit tant que la partie
        // dure. On refuse le message plutot que de tronquer le fil.
        clearstatcache(true, $fn);
        $current = file_exists($fn) ? filesize($fn) : 0;
        if ($current + strlen($_POST['chatmsg']) + 1 > $chatMaxBytes) {
            http_response_code(413);
            header('Content-Type: application/json');
            echo json_encode(array("error" => "chat log full"));
            exit;
        }
        $fp = @fopen($fn,"at");
        if ($fp === false) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(array("error" => "chat save failed"));
        } else {
            fputs($fp,$_POST['chatmsg']."\n");
            fclose($fp);
            header('Content-Type: application/json');
            echo json_encode(array("ok" => true));
        }
    }
    if(($_POST['chatioaction']=='load')){
        header('Content-Type: application/json');
        // Meme cause que pour le load() de partie plus haut : fopen() sur un
        // fichier absent emettait un avertissement PHP AVANT que le test
        // ($fp) ne puisse s'en apercevoir, polluant la reponse malgre le
        // else ci-dessous qui gere pourtant deja correctement ce cas.
        if (file_exists($fn)){
            // Initialise AVANT la boucle : sur un fichier existant mais vide
            // -- un premier message refuse, une ecriture interrompue -- la
            // boucle ne tournait pas, $msgs restait indefini, et le join()
            // emettait un avertissement PHP juste devant le JSON. C'est la
            // panne que tout le reste de ce fichier s'emploie deja a eviter.
            $msgs = array();
            $fp = fopen($fn,"rt");
            while($chatdata = fgets($fp)){
                $chatdata = rtrim($chatdata, "\r\n");
                if ($chatdata === '') {
                    continue;
                }
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