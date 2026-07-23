
// "Jouer seul" ouvre le lecteur Jocly ($joclyPlayerURL dans localconf.php,
// ex. .../control.html). En francais (lg == "fr"), on ouvre la variante
// localisee control_fr.html. Si l'URL configuree ne se termine pas par
// control.html (ex. un index.php maison), le remplacement ne matche pas
// et l'URL est utilisee telle quelle : aucun changement dans ce cas.
function playerURLForLanguage(){
    if (lg == "fr")
        return playerURL.replace(/control\.html$/i, "control_fr.html");
    return playerURL;
}

function startSelectedGame(){
    if(selectedGame == ""){
        alert(t("Please select a game first"));
    }else{
        window.open(playerURLForLanguage()+"?game="+selectedGame,'_blank');
    }
}

var bRulesOpen = false ;
function loadRules(rulesPath,fullPath){
    $("#rules").load(rulesPath, null, function(data, status, jqXGR){

        lastOpenedRules = selectedGame;

        var re = /{GAME}/gi ;
        data = data.replace(re,fullPath);
        var closeDiv = "<div class='close-rules-button'><a href='javascript:closeRules();'>Close rules</a></div>";
        data = closeDiv+data+closeDiv;
        //console.log(data);
        $("#rules").html(data);
        $("#rules-container").scrollTop(0);
    });
}
function closeRules(){
    bRulesOpen = false ;
    $("#rules").html("");
    $("#rules-container").css("height","10px");
}
var lastOpenedRules = "";
function openRules(){
    if(selectedGame == ""){
        alert("Please select a game first");
    }else if ((bRulesOpen) && (lastOpenedRules == selectedGame)){
            closeRules();
    }else{
        Jocly.getGameConfig(selectedGame).then((p)=>{
            console.log(p);
        lastOpenedRules = selectedGame;
            $("#rules-container").css("height","500px");
            bRulesOpen = true ;
            console.log();
            var rulesPath = "" ;
            // Meme regle de choix de langue que pour le resume, via le
            // helper commun (comportement inchange : fr si dispo et
            // interface en francais, anglais sinon).
            var rulesRelPath = localizedText(p.model.rules);
            if (rulesRelPath.length > 0){
                rulesPath = p.view.fullPath+"/"+rulesRelPath ;
            }
            if (rulesPath.length > 0){
                loadRules(rulesPath , p.view.fullPath);
            }else{
                $("#rules").html("<p>Sorry, no rules available for "+p.model["title-en"]+"</p>");
            }
        });
    }
}

function copy2Clipboard(player) {
    var check = "<span>&#10003;</span>";
    var inputID = 'link'+player;
    var copyText = document.getElementById(inputID);
    copyText.select();
    copyText.setSelectionRange(0, 99999)
    document.execCommand("copy");
    //copyText.setSelectionRange(0, 0)
    console.log("Copied the text: " + copyText.value);
}



function createMatch(){
    if(selectedGame == ""){
        alert(t("Please select a game first"));
    }else{        
        console.log("creating match");
        $("#match-area").show();
        var mid = Date.now()+"-"+makeid(14);
        var link=matchRootURL+"index.php?game="+selectedGame+"&mid="+mid;
        $("#linka").attr("value",link+"&player=a");
        $("#linkb").attr("value",link+"&player=b");
        $("#goplaya").attr("href",link+"&player=a");
        $("#goplayb").attr("href",link+"&player=b");
    }
}

function selectGame(name){
    // clear and close match area if open
    $("#match-area").hide();
    $("#linka").attr("value","");
    $("#linkb").attr("value","");

    $(".game-thumb").removeClass("selected-game");
    var divname = "#"+name ;
    $(divname).addClass("selected-game");
    Jocly.getGameConfig(name).then((p)=>{
        console.log(p);
        var linkToThisPage = matchRootURL+"gamespanel.php?game="+name;        
        $("#gd-game-name").html(p.model["title-en"]+" <a class=\"page-link\" href=\""+linkToThisPage+"\">link</a>");
        $("#gd-game-abstract").text(localizedText(p.model["summary"]));
        $("#gd-buttons-play").css("background","#2dbd2d");
        $(".gd-game-icon-img").attr("src", p.view.fullPath+"/"+p.model.thumbnail);
        if (p.model.rules !== undefined){
            $("#gd-buttons-rules").css("background","cornflowerblue");
        }else{
            $("#gd-buttons-rules").css("background","#888888");
        }
        if (bRulesOpen){
            openRules();
        }
        $("#gd-buttons-match").css("background","#2dbd2d");
    });
}

function gameClicked(g){
    if (selectedGame !== g.id){
        selectedGame = g.id;
        selectGame(selectedGame);
    }
        
    console.log(selectedGame);
}

function addGame(gameName){
    console.log(gameName);
    Jocly.getGameConfig(gameName).then((p)=>{
        //console.log(p); 
        //$("#games-panel").append("<div>"+p.model["title-en"]+"</div>");
        if (selectedModule.length > 0 && selectedModule != p.model.module)
            return false ;            
        var d = $('<div/>', {
            title : p.model["title-en"],
            module : p.model.module,
            id : gameName,
            class : 'game-thumb'        
        }).click(function(){
            gameClicked(this)
        });
        $('<img/>',{
            src : p.view.fullPath+"/"+p.model.thumbnail,
            
        }).appendTo(d);
        $("#games-panel").append(d);
        gameFullPath = p.view.fullPath ;
        if (p.model.rules !== undefined){
            var rulesPath = p.view.fullPath+"/"+p.model.rules.en ;
        }else{
            console.log("no rules for " + gameName );
        }
        if ((selectedGame.length > 0) && (selectedGame == gameName)){
            console.log("INIT select",selectedGame);
            selectGame(selectedGame);
        }
    })
}


Jocly.listGames()
  .then(function(games) {
    for (g in games){
        addGame(g);
    }
}).then(function(){
    console.log("done");
});


function setLanguage(newlg){

    if (["en","fr"].includes(newlg)){
        window.localStorage["lg"] = newlg;
        lg = newlg;
        
        if (lg=="en"){
            $("#flagicon").attr("src","i/flags/fr.svg");
            $("#lg-flag").click(function(){
                setLanguage("fr");
            })
        }
        if (lg=="fr"){
            $("#flagicon").attr("src","i/flags/en.svg");
            $("#lg-flag").click(function(){
                setLanguage("en");
            })
        }
        $(".t").each(function(){
            this.innerText = t($(this).attr("en-txt"));
        });
        // La notice existe en deux langues : on suit la locale active.
        $("#info-link").attr("href",
            lg=="fr" ? "doc/html/readthis_fr.html" : "doc/html/readthis.html");
    }
}

$(document).ready(function () {
    if (selectedModule.length > 0){
        var msg = "<div id=\"module-info\">Games list restricted to '"+selectedModule+"' module : <a href=\"?\">see all games</a></div>";
        $("#game-details").append(msg);
    }
    // save enlglish original texts
    $(".t").each(function(){
        $(this).attr("en-txt",this.innerText);
    });
    // update language
    if (window.localStorage["lg"]) 
        setLanguage(window.localStorage["lg"]);
    else 
        setLanguage("en");
});

// translations
var translations = {
    "Play Game" : {fr : "Jouer seul"},
    "Rules" : {fr : "Règles"},
    "Create match" : {fr : "Créer match"},
    "Link for player A : " : {fr : "Lien pour joueur A : "},
    "Link for player B : " : {fr : "Lien pour joueur B : "},
    "Copy" : {fr : "Copier"},
    "Open" : {fr : "Ouvrir"},
    "Jocly on Github" : {fr : "Jocly sur Github"},
    "About this site" : {fr: "À propos de ce site"},
    "Please select a game first" : {fr : "Merci de sélectionner un jeu"}
}

function t(txt){
    if (translations[txt] && translations[txt][lg]){
        return translations[txt][lg];
    }
    return txt;
}