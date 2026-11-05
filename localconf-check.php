<?php
// localconf-check.php -- dire POURQUOI la configuration n'a pas ete lue.
//
// LA PANNE QUI L'A MOTIVE. Un localconf.php enregistre en UTF-16 (ce que
// certains editeurs font d'eux-memes, des qu'un caractere ne tient pas dans
// leur jeu par defaut) n'est PAS du PHP pour l'interpreteur : il ne reconnait
// pas `<?php` parmi des octets nuls, et recopie tout le fichier tel quel dans
// la page. Aucune variable n'est definie ; gamespanel.php ecrivait alors des
// avertissements PHP au milieu de son JavaScript, et la page ne chargeait
// rien. Le symptome ("Undefined variable $joclyDistPath") ne disait rien de
// la cause.
//
// Ce fichier ne CHANGE rien a une configuration saine : il n'intervient que
// si un reglage obligatoire manque, et c'est alors pour nommer la cause et la
// facon d'y remedier.
//
// Pas de balise fermante PHP, pour la meme raison que localconf.php : un
// octet emis apres elle se retrouverait devant le JSON de fileio.php.

/**
 * Le probleme de configuration, ou null s'il n'y en a pas.
 *
 * @param array  $missing noms des variables obligatoires qui n'ont pas ete
 *                        definies (sans le `$`)
 * @param string $file    le fichier qu'a lu PHP (par defaut, celui que
 *                        `require "localconf.php"` a trouve)
 */
function localconfProblem(array $missing, $file = null) {
    if (!count($missing)) return null;
    if ($file === null) $file = stream_resolve_include_path('localconf.php');
    if ($file === false || !is_file($file)) {
        return "localconf.php est introuvable : copier localconf.php.example en "
            . "localconf.php, a la racine du site, et l'adapter.";
    }
    $head = (string) @file_get_contents($file, false, null, 0, 64);
    if (strncmp($head, "\xFF\xFE", 2) === 0 || strncmp($head, "\xFE\xFF", 2) === 0
        || strpos($head, "\x00") !== false) {
        return "localconf.php est enregistre en UTF-16 : PHP ne le lit pas comme du code, "
            . "et aucun reglage n'est pris en compte. L'enregistrer en UTF-8 sans BOM "
            . "(ou en ASCII), par exemple : iconv -f UTF-16 -t UTF-8 localconf.php > localconf.tmp "
            . "&& mv localconf.tmp localconf.php";
    }
    return "localconf.php ne definit pas : $" . implode(", $", $missing)
        . ". Voir localconf.php.example.";
}

/** Les noms de variables obligatoires absentes, parmi celles passees. */
function localconfMissing(array $vars, array $names) {
    $missing = array();
    foreach ($names as $n) {
        if (!array_key_exists($n, $vars) || $vars[$n] === null || $vars[$n] === '') $missing[] = $n;
    }
    return $missing;
}

/** Arrete une page HTML sur un message lisible, au lieu d'une page cassee. */
function localconfFailPage($problem) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html lang=\"fr\"><head><meta charset=\"utf-8\">"
        . "<title>JoclyMatch : configuration</title></head><body style=\"font-family:sans-serif;max-width:40em;margin:3em auto\">"
        . "<h1>Configuration du site</h1><p>" . htmlspecialchars($problem, ENT_QUOTES, 'UTF-8') . "</p></body></html>";
    exit;
}
