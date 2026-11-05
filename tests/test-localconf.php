<?php
// Test de la verification de localconf.php (localconf-check.php), execute par
// le php-cli : on fait tourner les VRAIES pages dans un repertoire temporaire,
// avec un localconf.php sain, en UTF-16, absent ou incomplet.
//
//   php tests/test-localconf.php
//
// LA PANNE REPRODUITE : un localconf.php enregistre en UTF-16 n'est pas lu par
// PHP. gamespanel.php deversait alors son contenu et des avertissements au
// milieu de son JavaScript (« Undefined variable $joclyDistPath »), et la page
// ne chargeait rien, sans rien dire de la cause.

$failures = 0;
$tests = 0;
function check($label, $cond) {
    global $failures, $tests;
    $tests++;
    echo ($cond ? "ok   " : "FAIL ") . $label . "\n";
    if (!$cond) $failures++;
}

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/joclymatch-localconf-' . getmypid() . '/';
@mkdir($tmp, 0775, true);
foreach (array('gamespanel.php', 'index.php', 'fileio.php', 'localconf-check.php') as $f) {
    copy($root . '/' . $f, $tmp . $f);
}

$good = "<?php\n"
    . "\$joclyMatchURL  = \"https://example.org/joclymatch/\";\n"
    . "\$joclyPlayerURL = \"https://example.org/joclymatch/index.php\";\n"
    . "\$joclyDistPath  = \"dist/browser/jocly.js\";\n"
    . "\$savePath       = " . var_export($tmp . 'saves/', true) . ";\n";

// Une page, executee depuis le repertoire du site (comme le ferait le serveur :
// `require "localconf.php"` y trouve le fichier par le chemin d'inclusion).
function run($page, $query = '') {
    global $tmp;
    $cmd = 'cd ' . escapeshellarg($tmp) . ' && ' . escapeshellcmd(PHP_BINARY)
        . ' -d display_errors=1 -d error_reporting=-1 -r '
        . escapeshellarg('parse_str(' . var_export($query, true) . ', $_GET); include ' . var_export($page, true) . ';')
        . ' 2>&1';
    return (string) shell_exec($cmd);
}
function setConf($bytes) {
    global $tmp;
    if ($bytes === null) { @unlink($tmp . 'localconf.php'); return; }
    file_put_contents($tmp . 'localconf.php', $bytes);
}

// --- configuration saine : rien ne change --------------------------------------
setConf($good);
$out = run('gamespanel.php');
check('sain : gamespanel.php charge jocly.js depuis le chemin configure',
    strpos($out, '<script src="dist/browser/jocly.js"></script>') !== false);
// Les messages de PHP eux-memes (« la notice » apparait dans un commentaire HTML).
$phpMsg = '/\b(Warning|Notice|Deprecated|Fatal error)(<\/b>)?: /';
check('sain : aucun avertissement PHP dans la page', !preg_match($phpMsg, $out));
check('sain : pas de page d’erreur de configuration', strpos($out, 'Configuration du site') === false);
check('sain : index.php non plus', strpos(run('index.php', 'mid=1-abc&game=classic-chess&player=a'), 'Configuration du site') === false);
check('sain : fileio.php ne refuse rien', strpos(run('fileio.php'), 'server configuration') === false);

// --- la panne reelle : UTF-16 avec BOM ------------------------------------------
setConf("\xFF\xFE" . mb_convert_encoding($good, 'UTF-16LE', 'UTF-8'));
$out = run('gamespanel.php');
check('UTF-16 : gamespanel.php nomme la cause', strpos($out, 'UTF-16') !== false && strpos($out, 'Configuration du site') !== false);
check('UTF-16 : et dit comment convertir', strpos($out, 'iconv -f UTF-16 -t UTF-8') !== false);
check('UTF-16 : plus aucun avertissement PHP ni octet UTF-16 dans la page',
    stripos($out, 'Undefined variable') === false && strpos($out, "\x00") === false);
check('UTF-16 : la page envoyee est de l’UTF-8 valide', mb_check_encoding($out, 'UTF-8'));
check('UTF-16 : index.php le dit aussi', strpos(run('index.php', 'mid=1-abc'), 'UTF-16') !== false);
$json = json_decode(run('fileio.php'), true);
check('UTF-16 : fileio.php refuse, en JSON lisible par les clients',
    is_array($json) && $json['error'] === 'server configuration' && strpos($json['detail'], 'UTF-16') !== false);

// Sans BOM (UTF-16 « nu ») : reconnu aussi, aux octets nuls.
setConf(mb_convert_encoding($good, 'UTF-16LE', 'UTF-8'));
check('UTF-16 sans BOM : reconnu aussi', strpos(run('gamespanel.php'), 'UTF-16') !== false);

// --- fichier absent ---------------------------------------------------------------
setConf(null);
$out = run('gamespanel.php');
check('absent : la page le dit et renvoie au modele',
    strpos($out, 'introuvable') !== false && strpos($out, 'localconf.php.example') !== false);
check('absent : sans erreur ni avertissement PHP', !preg_match($phpMsg, $out));

// --- fichier incomplet -------------------------------------------------------------
setConf("<?php\n\$joclyMatchURL = \"https://example.org/joclymatch/\";\n");
$out = run('gamespanel.php');
check('incomplet : la page nomme les reglages manquants',
    strpos($out, '$joclyDistPath') !== false && strpos($out, '$joclyPlayerURL') !== false);
$json = json_decode(run('fileio.php'), true);
check('incomplet : fileio.php refuse sans $savePath (il ecrirait sinon sous la racine web)',
    is_array($json) && strpos($json['detail'], '$savePath') !== false);

// --- le modele lui-meme ---------------------------------------------------------------
$example = file_get_contents($root . '/localconf.php.example');
check('le modele est en ASCII pur (rien qui pousse un editeur vers l’UTF-16)', !preg_match('/[\x80-\xFF]/', $example));
check('le modele n’a pas de balise fermante', strpos($example, '?' . '>') === false);
setConf($example);
check('le modele, copie tel quel, est une configuration complete', strpos(run('gamespanel.php'), 'Configuration du site') === false);

array_map('unlink', glob($tmp . '*'));
@rmdir($tmp . 'saves');
@rmdir($tmp);
echo "\n$tests verifications, $failures echec(s)\n";
exit($failures === 0 ? 0 : 1);
