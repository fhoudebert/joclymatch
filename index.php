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
		<div id="overhead-menu"><a href="<?php echo($joclyMatchURL."gamespanel.php"); ?>"><span class="t">All games panel</span></a> • <button id="playa-button" ><span class="t">Play A</span></button> • <button id="playb-button"><span class="t">Play B</span></button> • <a href='javascript:openPanel();'><span class="t">Controls</span></a> (C) • <a href='javascript:openRules();'><span class="t">Rules</span></a> (R) • <a href='javascript:openChat();'><span id="chat-menu" class="t">Chat</span></a> (T) • <a href="https://github.com/mi-g/jocly" target="_blank"><span class="t">Jocly on Github</span></a></div>


    </div>

	<?php require "localconf.php" ?>
    <script src="<?php echo($joclyDistPath);?>"></script>
    <script src="js/jquery-3.2.1.min.js"></script>
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
	if(isset($_GET["mid"])){
		echo("matchDetails.matchId = \"".$_GET["mid"]."\"; ");
	}
	if(isset($_GET["game"])){
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
	if (isset($_GET["lg"])){
		echo("lg = \"".$_GET["lg"]."\";");
		echo("window.localStorage[\"lg\"] = \"".$_GET["lg"]."\" ;");
	}    
	?>


	</script>
		
	<script src="js/common.js"></script>
    <script src="js/control.js"></script>
</body>

</html>