<?php
// localconf.php est charge ICI, AVANT toute sortie.
//
// IL L'ETAIT APRES : le menu de navigation employait $joclyMatchURL cinq
// lignes AVANT son require, donc la variable y etait toujours vide et le lien
// « Panneau des jeux » valait « gamespanel.php » tout court. Il ne marchait
// que par resolution relative -- c'est-a-dire seulement quand la page est
// servie depuis le meme repertoire, et pas du tout des qu'une reecriture
// d'URL ou un chemin different s'en mele.
//
// Meme precaution que fileio.php : un localconf.php qui emet un BOM, une
// ligne vide ou un avertissement n'a pas a l'inserer avant le doctype.
ob_start();
require "localconf.php";
ob_end_clean();

// Base absolue, avec ou sans barre finale dans localconf.php. Le README la
// demande ; s'en remettre a la bonne volonte du fichier de configuration pour
// une concatenation, c'est produire « .../joclymatchgamespanel.php » au
// premier oubli.
$joclyMatchBase = rtrim(isset($joclyMatchURL) ? $joclyMatchURL : '', '/');
if ($joclyMatchBase === '') $joclyMatchBase = '.';
?>
<!doctype html>

<html lang="en">

<head>
    <meta charset="utf-8">

    <title>Play Jocly Game</title>
    <meta name="description" content="Jocly Game Player">
    <meta name="author" content="Jocly">

    <link rel="stylesheet" href="css/control-styles.css">


	<link rel="apple-touch-icon" sizes="180x180" href="i/favicons/normal/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="i/favicons/normal/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="i/favicons/normal/favicon-16x16.png">
	<link rel="manifest" href="i/favicons/normal/site.webmanifest">
	<meta name="msapplication-TileColor" content="#da532c">
	<meta name="theme-color" content="#ffffff">	


</head>

<body>
	
	<div id="container">
		<div id="game-status" style="display: none;" class="game-status-base"><span class="t">Status</span></div>
		<div id="applet">
			<div id="progress-bar"></div>
		</div>
		<div id="rulestab">	
			<div id="panel-rules">
				<div id="close-rules"><a class="rules-panel-button" href="javascript:closeRules()"><span class="t">Close rules</span></a></div>
			</div>
			<div id="rules"></div>
		</div>
		<div id="chattab">
			<div id="panel-chat">
				<div id="prev-msgs"><div id="bottom-container"></div></div>
				<div id="pseudo-div"><span class="t">My name</span> : <input id="player-pseudo" value=""></input></div>
				<div id="chat-input"><textarea rows="3" id="chat-input-field"></textarea></div>
			</div>
		</div>
		<div id="controls">
			<div id="panel-control">
				<div id="close-panel"><a class="ctrl-panel-button" href="javascript:closePanel()"><span class='t'>Close options panel</span></a></div>
			</div>
			
            <div id="game-title" style="display: none;" class="box"><span class="t">Jocly Game</span></div>
			<div id="mode-panel" style="display: none;" class="box">
				<div id="lg-flag"><img id="flagicon" src="i/flags/en.svg"></div>
				<h3><span class="t">Controls</span></h3>
				<!-- Ce bouton existait DANS control.js mais pas dans la page : code
				     mort. Il reste cache tant qu'il n'y a pas deux coups a reprendre.
				     Pas de « Recommencer » : un match a distance ne se remet pas a
				     zero d'un clic (meme choix que mogichex). -->
				<button id="takeback" style="display: none;"><span class="t">Take back my last move</span></button>
				<!-- Un bouton qui disparait sans motif se lit comme une panne : quand
				     la partie interdit la reprise, on le dit. -->
				<p id="takeback-forbidden" class="takeback-forbidden" style="display: none;"><span class="t">This match does not allow taking back moves.</span></p>
				<button id="replaylastmove" style="display: none;"><span class="t">Replay last move</span></button>
				<button id="fullscreen" style="display: none;"><span class="t">Full screen</span></button>
				<button id="save"><span class="t">Save</span></button>
				<input type="file" id="fileElem" accept="application/json" style="display:none"/>
				<button id="snapshot"><span class="t">Snapshot</span></button>
                <br/><br/>
			</div>
			<div id="options" style="display: none;"  class="box">
				<h3><span class="t">Options</span></h3>
				<div id="view-options">
					<select id="options-skin"></select>
					<select id="view-as" style="display: none;">
						<option value="player-a"><span class="t">View as player A</span></option>
						<option value="player-b"><span class="t">View as player B</span></option>
					</select>
					<br/><br/>
					<label id="options-notation" for="options-notation-input">
						<input id="options-notation-input" type="checkbox"/> <span class="t">Notation</span><br/>
					</label>
					<label id="options-moves" for="options-moves-input">
						<input id="options-moves-input" type="checkbox"/> <span class="t">Show possible moves</span><br/>
					</label>
					<label id="options-autocomplete" for="options-autocomplete-input">
						<input id="options-autocomplete-input" type="checkbox"/> <span class="t">Auto-complete moves</span><br/>
					</label>
					<label id="options-sounds" for="options-sounds-input">
						<input id="options-sounds-input" type="checkbox"/> <span class="t">Sounds</span><br/>
					</label>
				</div>
	       	</div>
		</div>
        <div id="games" style="display:none">
			<div>
				<div>
					<div id="close-games">
						<span>&laquo; <span class="t">Back</span></span>
					</div>
					<div id="game-list"></div>
				</div>
			</div>
        </div>
		<!-- La notice s'ouvre dans un NOUVEL ONGLET : elle remplacait la page de
		     match, et le retour du navigateur renvoyait au panneau des jeux --
		     le match etait perdu. C'est la seule facon d'atteindre la notice
		     depuis une partie, donc le bloc reste ; c'est la navigation qui
		     etait fautive. L'ancien lien « Jocly on Github » portait deja
		     target="_blank", pour exactement cette raison. -->
		<div id="overhead-menu"><a href="<?php echo(htmlspecialchars($joclyMatchBase."/gamespanel.php", ENT_QUOTES)); ?>"><span class="t">All games panel</span></a> • <button id="playa-button" ><span class="t">Play A</span></button> • <button id="playb-button"><span class="t">Play B</span></button> • <a href='javascript:openPanel();'><span class="t">Controls</span></a> (C) • <a href='javascript:openRules();'><span class="t">Rules</span></a> (R) • <a href='javascript:openChat();'><span id="chat-menu" class="t">Chat</span></a> (T) • <a id="info-link" href="doc/html/readthis.html" target="_blank" rel="noopener"><span class="t">About this site</span></a></div>


    </div>

    <script src="<?php echo($joclyDistPath);?>"></script>
    <script src="js/jquery-3.7.1.min.js"></script>
	<script>
	// io functions 
	var matchDetails = {
		matchId : "",
		gameName : "classic-chess",
		nbTurns : 0,
		a : { pseudo : "" },
		b : { pseudo : "" }
	}
	var iamPlayer = Jocly.PLAYER_A;
    var lg = "en";


	<?php
	// Ces parametres GET sont echos tels quels dans le <script> ci-dessus :
	// sans validation, ?mid="; ...code... //  permet d'injecter du script
	// dans la page (XSS reflechi). Meme approche que fileio.php : les
	// valeurs legitimes (ids generes par gamespanel.js, noms de jeux Jocly,
	// codes langue) sont deja uniquement [A-Za-z0-9_-], donc une valeur
	// valide passe exactement comme avant et une valeur forgee est ignoree
	// (la variable garde son defaut).
	function safeParam($name){
		return isset($_GET[$name]) && preg_match('/^[A-Za-z0-9_-]+$/', $_GET[$name]);
	}
	if(safeParam("mid")){
		echo("matchDetails.matchId = \"".$_GET["mid"]."\"; ");
	}
	if(safeParam("game")){
		echo("matchDetails.gameName = \"".$_GET["game"]."\"; ");
	}
	if(isset($_GET["player"])){
		if ($_GET["player"] == "a"){
			echo("iamPlayer = Jocly.PLAYER_A;");
		}
		if ($_GET["player"] == "b"){
			echo("iamPlayer = Jocly.PLAYER_B;");
		}
	}
	// Reprise de coup, telle que l'hote l'a reglee a la creation du match
	// (tb=1 / tb=0). Seules ces deux valeurs sont reconnues ; absente, la
	// variable reste indefinie et control.js applique la regle par defaut.
	// Le FICHIER de la partie, s'il porte le reglage, l'emporte ensuite.
	if (isset($_GET["tb"]) && ($_GET["tb"] === "0" || $_GET["tb"] === "1")){
		echo("matchDetails.allowTakeback = ".($_GET["tb"] === "1" ? "true" : "false")."; ");
	}
	if (safeParam("lg")){
		echo("lg = \"".$_GET["lg"]."\";");
		echo("window.localStorage[\"lg\"] = \"".$_GET["lg"]."\" ;");
	}    
	?>


	</script>
		
	<script src="js/common.js"></script>
    <script src="js/control.js"></script>
	<?php
	// Long-polling optionnel (voir ANALYSE-PUSH-JOCLYSIMPLEMATCH.md §2/§5) :
	// absent par defaut ($enableLongPolling non defini dans localconf.php),
	// donc aucun changement de comportement sur un hebergement mutualise
	// classique -- a activer seulement si on sait que le pool de workers
	// PHP-FPM/mod_php de l'hebergement peut tenir la charge (voir le detail
	// des compromis dans l'analyse).
	if (!empty($enableLongPolling)) {
		echo("<script>var LONG_POLL_ENABLED = true;</script>\n");
	}
	// Notification "push" optionnelle (VPS avec Node qui tourne à côté --
	// voir push/README.md ; absente par défaut, donc aucun changement de
	// comportement sur un hébergement mutualisé classique qui n'a pas cette
	// variable dans localconf.php). Ne fait QUE déclencher un reload
	// immédiat en plus du polling existant, jamais à sa place.
	if (!empty($pushWsUrl)) {
		echo("<script>var PUSH_WS_URL = ".json_encode($pushWsUrl).";</script>\n");
		echo("<script src=\"js/push-client.js\"></script>\n");
	}
	?>
</body>

</html>