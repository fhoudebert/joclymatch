<?php
// Test du relai de parties, execute par le php-cli : on simule des requetes
// en remplissant $_POST et en capturant la sortie, sans serveur web.
//
//   php tests/test-fileio.php
//
// CE QUE CE TEST COUVRE : la validation de l'identifiant, l'ecriture et la
// relecture d'une partie, les bornes de taille, la suppression explicite, le
// menage par anciennete, et les trois facons dont le fil de chat pouvait etre
// abime durablement. CE QU'IL NE COUVRE PAS : l'attente longue (elle dure 20 s
// et depend de l'horloge du systeme de fichiers), la concurrence reelle entre
// deux processus PHP, et les limites propres a l'hebergeur
// (max_execution_time, nombre de processus). A verifier en ligne.
//
// Ecrit sur le modele de tests/test-signal.php de mogichex, qui resout le meme
// probleme -- un relai PHP sans serveur pour le tester.

$failures = 0;
$tests = 0;

function check($label, $cond) {
    global $failures, $tests;
    $tests++;
    if ($cond) {
        echo "ok   $label\n";
    } else {
        $failures++;
        echo "FAIL $label\n";
    }
}

$tmp = sys_get_temp_dir() . '/joclymatch-fileio-' . getmypid() . '/';
@mkdir($tmp, 0775, true);

function request($post, $ttl = 2592000, $maxSave = 1048576, $maxChat = 262144, $trim = 1) {
    global $tmp;
    $runner = __DIR__ . '/fileio-runner.php';
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' '
        . escapeshellarg(json_encode($post)) . ' ' . escapeshellarg($tmp) . ' '
        . escapeshellarg($ttl) . ' ' . escapeshellarg($maxSave) . ' ' . escapeshellarg($maxChat) . ' '
        . escapeshellarg($trim);
    return shell_exec($cmd);
}

$id = 'test-' . getmypid();

// --- l'identifiant devient un nom de fichier ---------------------------------

// Une traversee de repertoire ecrirait n'importe ou sur l'hebergement.
$r = request(array('gameioaction' => 'save', 'gameid' => '../evil', 'gamedata' => '{"x":1}'));
check('identifiant hors motif : rien ne se passe', trim($r) === '');
check('et aucun fichier ne sort du repertoire', !file_exists(dirname(rtrim($tmp, '/')) . '/evil.txt'));

// --- une partie s'ecrit et se relit ------------------------------------------

request(array('gameioaction' => 'save', 'gameid' => $id, 'gamedata' => '{"nbTurns":3}'));
$r = json_decode(request(array('gameioaction' => 'load', 'gameid' => $id)), true);
check('une partie enregistree se relit', isset($r['nbTurns']) && $r['nbTurns'] === 3);

// Une partie jamais enregistree rend du JSON valide, pas un corps vide : le
// client fait JSON.parse sans condition.
$r = request(array('gameioaction' => 'load', 'gameid' => $id . 'zz'));
check('une partie absente rend {} et non du vide', trim($r) === '{}');

// --- les bornes de taille ----------------------------------------------------

// Rien ne bornait la charge : n'importe qui pouvait remplir le disque.
$big = str_repeat('a', 300);
$r = request(array('gameioaction' => 'save', 'gameid' => $id . 'big', 'gamedata' => $big), 2592000, 100);
check('une partie trop grosse est refusee', strpos($r, 'payload too large') !== false);
check('et rien n\'est ecrit', !file_exists($tmp . $id . 'big.txt'));

// --- la suppression explicite ------------------------------------------------

request(array('chatioaction' => 'save', 'gameid' => $id, 'chatmsg' => '{"m":"salut"}'));
check('le fil de chat existe avant la suppression', file_exists($tmp . $id . '-chat.txt'));
$r = json_decode(request(array('gameioaction' => 'drop', 'gameid' => $id)), true);
check('drop repond ok', isset($r['ok']));
check('la partie est effacee', !file_exists($tmp . $id . '.txt'));
// Le chat d'une partie effacee n'a plus de sens, et le laisser serait garder
// une conversation dont plus rien ne porte la trace.
check('et le fil de chat avec elle', !file_exists($tmp . $id . '-chat.txt'));

// --- le menage par anciennete ------------------------------------------------

// C'est le cas COURANT : la partie abandonnee en silence, que personne ne vient
// clore. Jusqu'ici rien ne l'effacait jamais.
$old = $tmp . 'stale-' . getmypid() . '.txt';
$oldChat = $tmp . 'stale-' . getmypid() . '-chat.txt';
file_put_contents($old, '{"nbTurns":1}');
file_put_contents($oldChat, "{\"m\":\"vieux\"}\n");
touch($old, time() - 7200);
touch($oldChat, time() - 7200);
$fresh = $tmp . 'fresh-' . getmypid() . '.txt';
file_put_contents($fresh, '{"nbTurns":1}');

// TTL d'une heure : le vieux passe, le recent reste.
request(array('gameioaction' => 'save', 'gameid' => $id . 'sweep', 'gamedata' => '{}'), 3600);
check('un fichier plus vieux que le TTL est efface', !file_exists($old));
check('son fil de chat aussi -- un -chat.txt est aussi un .txt', !file_exists($oldChat));
check('un fichier recent est laisse tranquille', file_exists($fresh));

// Un temporaire d'ecriture atomique interrompue ne porte pas l'extension .txt
// et n'etait donc efface par personne : une coupure au mauvais moment laissait
// un fichier a vie.
$stray = $tmp . 'stale-' . getmypid() . '.txt.tmp1234';
file_put_contents($stray, 'moitie ecrit');
touch($stray, time() - 7200);
request(array('gameioaction' => 'save', 'gameid' => $id . 'sweep2', 'gamedata' => '{}'), 3600);
check('un temporaire abandonne est efface aussi', !file_exists($stray));

// --- le fil de chat ----------------------------------------------------------

$cid = 'chat-' . getmypid();
request(array('chatioaction' => 'save', 'gameid' => $cid, 'chatmsg' => '{"m":"un"}'));
request(array('chatioaction' => 'save', 'gameid' => $cid, 'chatmsg' => '{"m":"deux"}'));
$r = json_decode(request(array('chatioaction' => 'load', 'gameid' => $cid)), true);
check('les messages s\'ajoutent dans l\'ordre',
    isset($r['messages']) && count($r['messages']) === 2 && $r['messages'][0]['m'] === 'un');

// UN MESSAGE EST UNE LIGNE. Le fichier est relu ligne par ligne puis recolle
// par join(",", ...) : un saut de ligne DANS un message couperait le JSON de
// son auteur en deux fragments invalides, et le fil deviendrait illisible pour
// les DEUX joueurs -- durablement, le fichier etant en ajout. Le client de
// joclymatch envoie du JSON.stringify, qui n'en emet jamais ; un autre client,
// si.
$r = request(array('chatioaction' => 'save', 'gameid' => $cid, 'chatmsg' => "{\"m\":\"a\nb\"}"));
check('un message multiligne est refuse', strpos($r, 'single line') !== false);
$r = json_decode(request(array('chatioaction' => 'load', 'gameid' => $cid)), true);
check('et le fil reste lisible', isset($r['messages']) && count($r['messages']) === 2);

/*
 * LE FIL PLEIN NE FERME PLUS LA CONVERSATION.
 *
 * Le fichier est en ajout et une partie par correspondance dure des semaines :
 * la borne finit par etre atteinte, et le fil restait alors fige au milieu
 * d'une partie qui continuait. On retire desormais les plus anciens messages
 * pour loger les nouveaux.
 */
$full = 'full-' . getmypid();
for ($i = 1; $i <= 6; $i++) {
    request(array('chatioaction' => 'save', 'gameid' => $full,
        'chatmsg' => '{"m":"' . $i . str_repeat('x', 40) . '"}'), 2592000, 1048576, 200);
}
$r = json_decode(request(array('chatioaction' => 'load', 'gameid' => $full), 2592000, 1048576, 200), true);
$msgs = isset($r['messages']) ? $r['messages'] : array();
check('le fil accepte encore des messages une fois plein', count($msgs) > 0);
check('et ce sont les DERNIERS qui restent',
    count($msgs) && substr($msgs[count($msgs) - 1]['m'], 0, 1) === '6');
check('les premiers ont laisse la place', count($msgs) && substr($msgs[0]['m'], 0, 1) !== '1');
check('le fichier reste sous la borne', filesize($tmp . $full . '-chat.txt') <= 200);
// Le serveur DIT combien il a retire : un client qui le lit peut l'annoncer,
// plutot que de laisser le debut du fil disparaitre sans explication.
$r = json_decode(request(array('chatioaction' => 'save', 'gameid' => $full,
    'chatmsg' => '{"m":"7' . str_repeat('x', 40) . '"}'), 2592000, 1048576, 200), true);
check('la reponse annonce ce qui a ete retire',
    isset($r['ok']) && isset($r['trimmed']) && $r['trimmed'] >= 1);

// Un message plus gros que le fil entier ne rentrera jamais : c'est le seul
// refus qui reste, et il porte sur CE message.
$r = request(array('chatioaction' => 'save', 'gameid' => $full,
    'chatmsg' => '{"m":"' . str_repeat('x', 400) . '"}'), 2592000, 1048576, 200);
check('un message plus gros que le fil est refuse', strpos($r, 'chat message too large') !== false);
$r = json_decode(request(array('chatioaction' => 'load', 'gameid' => $full), 2592000, 1048576, 200), true);
check('et le fil reste lisible apres ce refus', isset($r['messages']) && count($r['messages']) > 0);

// L'ancien comportement reste disponible pour un hebergement qui prefere
// archiver : $chatTrimOldest = false dans localconf.php.
$keep = 'keep-' . getmypid();
$r = '';
for ($i = 1; $i <= 3; $i++) {
    $r = request(array('chatioaction' => 'save', 'gameid' => $keep,
        'chatmsg' => '{"m":"' . $i . str_repeat('x', 80) . '"}'), 2592000, 1048576, 200, 0);
}
check('sans retrait, un fil plein refuse toujours', strpos($r, 'chat log full') !== false);
$r = json_decode(request(array('chatioaction' => 'load', 'gameid' => $keep), 2592000, 1048576, 200, 0), true);
check('et garde ses premiers messages',
    isset($r['messages']) && count($r['messages']) === 2 && substr($r['messages'][0]['m'], 0, 1) === '1');

// Un fichier existant mais VIDE -- premier message refuse, ecriture
// interrompue : la boucle de lecture ne tournait pas, $msgs restait indefini,
// et l'avertissement PHP du join() se retrouvait DEVANT le JSON. C'est la
// panne que tout le reste de ce fichier s'emploie deja a eviter.
$empty = 'empty-' . getmypid();
file_put_contents($tmp . $empty . '-chat.txt', '');
$raw = request(array('chatioaction' => 'load', 'gameid' => $empty));
check('un fil vide rend du JSON propre, sans avertissement devant',
    json_decode($raw, true) !== null && strpos($raw, 'Warning') === false);

// --- dialecte match.php (action / mid / data) --------------------------------
//
// mogichex parle action/mid/data la ou joclymatch et Tabulon parlent
// gameioaction/gameid/gamedata. Les deux doivent aboutir au MEME fichier :
// c'est toute la raison d'etre de la traduction, et ce que ces verifications
// tiennent.

$dia = 'dialecte-' . getmypid();
request(array('action' => 'save', 'mid' => $dia, 'data' => '{"venu":"de mogichex"}'));
check('le dialecte match.php ecrit bien la partie',
    file_get_contents($tmp . $dia . '.txt') === '{"venu":"de mogichex"}');

$r = request(array('gameioaction' => 'load', 'gameid' => $dia));
check('et le dialecte joclymatch la relit', strpos($r, 'de mogichex') !== false);

$r = request(array('action' => 'load', 'mid' => $dia));
check('le dialecte match.php relit aussi', strpos($r, 'de mogichex') !== false);

// Un identifiant invalide doit etre refuse quel que soit le dialecte : la
// traduction a lieu AVANT la validation, sinon elle ouvrirait une porte que
// l'autre chemin ferme.
request(array('action' => 'save', 'mid' => '../evade', 'data' => 'x'));
check('le dialecte match.php ne contourne pas la validation d identifiant',
    !file_exists($tmp . '../evade.txt') && !file_exists(dirname($tmp) . '/evade.txt'));

// Le dialecte d'origine reste prioritaire : un client qui enverrait les deux
// obtient ce qu'il obtenait avant.
$deux = 'deux-' . getmypid();
request(array('gameioaction' => 'save', 'gameid' => $deux, 'gamedata' => 'joclymatch',
    'action' => 'save', 'mid' => 'autre-' . getmypid(), 'data' => 'mogichex'));
check('gameioaction l emporte quand les deux sont presents',
    file_get_contents($tmp . $deux . '.txt') === 'joclymatch'
    && !file_exists($tmp . 'autre-' . getmypid() . '.txt'));

// Le chat de mogichex passe par des cles de partie ordinaires (<mid>-ca), donc
// par le meme chemin : rien de special a prevoir.
request(array('action' => 'save', 'mid' => $dia . '-ca', 'data' => '{"msgs":[]}'));
check('une cle de fil mogichex est acceptee telle quelle',
    file_exists($tmp . $dia . '-ca.txt'));

$r = request(array('action' => 'drop', 'mid' => $dia));
check('le dialecte match.php sait aussi effacer', !file_exists($tmp . $dia . '.txt'));

// --- menage ------------------------------------------------------------------

array_map('unlink', glob($tmp . '*'));
@rmdir($tmp);

echo "\n$tests verifications, $failures echec(s)\n";
exit($failures === 0 ? 0 : 1);
