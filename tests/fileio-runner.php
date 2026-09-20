<?php
// Execute UNE requete contre fileio.php et ecrit sa reponse sur la sortie.
//
//   php tests/fileio-runner.php '<json POST>' '<repertoire>' [ttl] [maxSave] [maxChat] [trim]
//
// fileio.php appelle exit() : on ne peut pas l'inclure deux fois dans le meme
// processus. Chaque requete passe donc par un sous-processus, comme le fait
// mogichex pour signal.php.
//
// localconf.php ne fait pas partie du depot (il porte $savePath, propre a
// chaque hebergement). On en fabrique un dans le repertoire temporaire et on
// s'y place, puisque fileio.php le charge par un chemin relatif.

$_POST = json_decode($argv[1], true);
$_SERVER['REQUEST_METHOD'] = 'POST';

$dir = rtrim($argv[2], '/') . '/';
@mkdir($dir, 0775, true);

// Reecrit a CHAQUE appel : les reglages sont passes en arguments et changent
// d'une verification a l'autre. Un fichier conserve du premier appel ferait
// silencieusement tourner tout le reste de la suite avec les mauvaises bornes.
$conf = $dir . 'localconf.php';
{
    file_put_contents($conf,
        "<?php\n"
        . '$savePath = ' . var_export($dir, true) . ";\n"
        . '$saveTTL = ' . (isset($argv[3]) ? (int) $argv[3] : 2592000) . ";\n"
        . '$saveMaxBytes = ' . (isset($argv[4]) ? (int) $argv[4] : 1048576) . ";\n"
        . '$chatMaxBytes = ' . (isset($argv[5]) ? (int) $argv[5] : 262144) . ";\n"
        // Retrait des plus anciens messages quand le fil est plein : c'est le
        // comportement par defaut, et une verification le desactive.
        . '$chatTrimOldest = ' . (isset($argv[6]) && $argv[6] === '0' ? 'false' : 'true') . ";\n");
}

chdir($dir);
require __DIR__ . '/../fileio.php';
