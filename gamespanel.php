<!doctype html>

<html lang="en">

<head>
    <meta charset="utf-8">

    <title>Jocly Games Panel</title>
    <meta name="description" content="Jocly Games Panel">
    <meta name="author" content="Jocly">

    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/gamespanel.css">
    
    <link rel="apple-touch-icon" sizes="180x180" href="i/favicons/normal/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="i/favicons/normal/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="i/favicons/normal/favicon-16x16.png">
	<link rel="manifest" href="i/favicons/normal/site.webmanifest">
	<meta name="msapplication-TileColor" content="#da532c">
	<meta name="theme-color" content="#ffffff">	

</head>


<body>
    <div id="info-div"><a id="info-link" href="doc/html/readthis.html">&#9432; <span class='t'>About this site</span></a></div>
    <div id="game-details">
        <div id="lg-flag"><img id="flagicon" src="i/flags/en.svg"></div>
        <div id="gd-game-icon"><img class="gd-game-icon-img" src="i/jocly-logo.png"></div>
        <div id="gd-game-name"></div>
        <div id="gd-game-abstract"></div>
        <div id="gd-buttons">
            <div id="gd-buttons-play" class="gd-button" onClick="javascript:startSelectedGame();"><img class="button-svg-pic" src="i/user-f2.svg"> <span class='t'>Play Game</span></div>
            <div id="gd-buttons-rules" class="gd-button" onClick="javascript:openRules();"><span class='t'>Rules</span></div>
            <div id="gd-buttons-match" class="gd-button" onClick="javascript:createMatch();"><span class='t'>Create match</span> <img class="button-svg-pic" src="i/users-connected.svg"></div>
        </div>
        <div id="jocly-github"><a href="https://github.com/mi-g/jocly"><span class='t'>Jocly on Github</span></a></div>
        <div id="match-area">
            <div><span class='t'>Link for player A : </span></a><input class="player-link" id="linka" size="70" spellcheck="false" readonly="" type="text" value="link for player a"> <a id="blinka" href="javascript:copy2Clipboard('a');"><span class='t'>Copy</span></a> • <a id="goplaya" target="_blank" href=""><span class='t'>Open</span></a></div>
            <div><span class='t'>Link for player B : </span></a><input class="player-link" id="linkb" size="70" spellcheck="false" readonly="" type="text" value="link for player b"> <a id="blinkb" href="javascript:copy2Clipboard('b');"><span class='t'>Copy</span></a> • <a id="goplayb" target="_blank" href=""><span class='t'>Open</span></a></div>
        </div>
    </div>
    <div id="rules-container">
        <div id="rules"></div>
    </div>
    <div id="games-panel"></div>

    <?php require "localconf.php" ?>
    <script src="<?php echo($joclyDistPath);?>"></script>
    <script src="js/jquery-3.7.1.min.js"></script>
    <script>
    var selectedGame = "";
    var selectedModule = "";
    var lg = "en";
    var playerURL="<?php echo $joclyPlayerURL; ?>";
    var matchRootURL="<?php echo $joclyMatchURL; ?>"
    <?php
        // Meme protection que dans index.php : ces valeurs sont echos dans
        // du JS inline, on n'accepte que [A-Za-z0-9_-] (ce que sont deja
        // tous les noms de jeux/modules Jocly et codes langue legitimes).
        function safeParam($name){
            return isset($_GET[$name]) && preg_match('/^[A-Za-z0-9_-]+$/', $_GET[$name]);
        }
        if (safeParam("game")){
            echo("selectedGame = \"".$_GET["game"]."\";");
        }        
        if (safeParam("module")){
            echo("selectedModule = \"".$_GET["module"]."\";");
        }        
        if (safeParam("lg")){
            echo("lg = \"".$_GET["lg"]."\";");
            echo("window.localStorage[\"lg\"] = \"".$_GET["lg"]."\" ;");
        }        
    ?>
    </script>
    <script src="js/common.js"></script>
    <script src="js/gamespanel.js"></script>
</body>
</html>
