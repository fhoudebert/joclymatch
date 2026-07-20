
/*
 * Displays winner
 */
function NotifyWinner(winner) {
    var text = t("Draw");
    if(winner==Jocly.PLAYER_A)
        text = t("A wins");
    else if(winner==Jocly.PLAYER_B)
        text = t("B wins");    
    $("#game-status").text(text);


    var curId = matchDetails.matchId.split("-");
    var link = "index.php?game="+matchDetails.gameName+"&mid="+curId[0]+"-"+incId(curId[1])+"&player=";

    // propose new game
    var html = t("End of game")+" : "+"<div class='winner-message'>"+text+"</div>";
    html += t("Start a new game?")+"<br>";
    html += "<a href=\""+link+"a\">"+t("Play A")+"</a>"
    html += " • ";
    html += "<a href=\""+link+"b\">"+t("Play B")+"</a>"
    html += "<br>";
    html += t("Or go back to all games")+":<br>";
    html += "<a href=\"gamespanel.php\">"+t("Click here")+"</a>";

    informUserInChatroom(html);
}

/* 
 * Run the game
 */
var movePending = null;
var nextMoveCounter = 0 ;

var reloadCounter = 0 ;
function checkIfOtherUserPlayed(delay) {
    return new Promise(function(resolve) {
        setTimeout(resolve, delay);
    });
}

function RunMatch(match, progressBar) {
    var movePendingResolver;

    // Reference globale du match en cours -- lue uniquement par
    // js/push-client.js (composant optionnel, non charge par defaut, voir
    // push/README.md) pour declencher un reload immediat sur notification.
    // N'a aucun effet si ce script n'est pas charge.
    window.currentMatch = match;

    // first make sure there is no user input or machine search in progress
    var promise = match.abortUserTurn() // just in case one is running
        .then( () => {
            return match.abortMachineSearch(); // just in case one is running
        });

    function NextMove() {
        console.log("nextMoveCounter",nextMoveCounter++);
        if(movePending)
            return;
        movePending = new Promise((resolve,reject)=>{
            movePendingResolver = resolve;
        });
        // whose turn is it ?
        match.getTurn()
            .then((player) => {
                updateGameTitle();
                changeFavicon(player==iamPlayer);
                // display whose turn
                $("#game-status").text(player==Jocly.PLAYER_A?t("A playing"):t("B playing"));
                if (player == iamPlayer){
                    $("#replaylastmove").attr('disabled', false);
                    $("#game-status").addClass("iamPlaying");
                }
                else{                    
                    $("#replaylastmove").attr('disabled', true);
                    $("#game-status").removeClass("iamPlaying");
                }
                var promise = Promise.resolve();
                if(player==iamPlayer)
                        // user to play
                        promise = promise.then( () => {
                            // reques user input
                            return match.userTurn()
                        }).then( () => {
                            matchDetails.nbTurns ++;
                            saveGameIfNecessary(match);
                        })
                        else {
                            promise = promise.then( () => {                            
                                if(matchDetails.matchId.length>0) {
                                    if (typeof LONG_POLL_ENABLED !== 'undefined' && LONG_POLL_ENABLED) {
                                        // Le serveur retient la reponse jusqu'a
                                        // un changement (fileio.php, sinceMtime)
                                        // -- pas besoin du throttle reloadCounter
                                        // ni d'attendre 500ms cote client, juste
                                        // un court delai de securite avant de
                                        // relancer l'appel suivant.
                                        loadMatchFromID(matchDetails.matchId,match,true);
                                        return checkIfOtherUserPlayed(200);
                                    }
                                    if (reloadCounter == 0) loadMatchFromID(matchDetails.matchId,match);
                                    reloadCounter = (reloadCounter+1)%6;
                                }
                                return checkIfOtherUserPlayed(500);
                        });                        
                    }

                promise.then(() => {
                        // is game over ?
                        return match.getFinished()
                    })
                    .then((result) => {
                        movePending = null;
                        movePendingResolver();
                        if (result.finished)
                            NotifyWinner(result.winner);
                        else
                            NextMove();
                        })
                    .catch((e)=>{
                        movePending = null;
                        movePendingResolver();
                        console.warn("Turn aborted:",e);
                    })
                    .then(() => {
                        if (progressBar)
                            progressBar.style.display = "none";
                    });
            })
    }
    match.getFinished()
        .then( (result) => {
            // make sure the game is not finished to request next move
            if(!result.finished) {
                if(movePending) {
                    movePending.then(()=>{
                        NextMove();                
                    })
                } else
                    NextMove();
            }
        });
}


/*
 * Panels handling
 */
function closePanel(){
    $("#controls").css("left","-300px");
}
function openPanel(){
    closeRules();
    closeChat();
    $("#controls").css("left","0px");
}
function closeRules(){
    $("#rulestab").css("left","-500px");
}
function openRules(){
    closePanel();
    closeChat();
    $("#rulestab").css("left","0px");
}
var bchatOpen = false ;
function closeChat(){
    bchatOpen = false ;
    $("#chattab").css("left","-"+$("#chattab").css("width"));
}
function openChat(){
    bchatOpen = true;
    closePanel();
    closeRules();
    $("#chattab").css("left","0px");
    $("#chat-menu").removeClass("new-messages");
}
/*
 * Rules
 */
var gameFullPath = null ;
function loadRules(rulesPath,fullPath){
    $("#rules").load(rulesPath, null, function(data, status, jqXGR){
        var re = /{GAME}/gi ;
        data = data.replace(re,gameFullPath);
        //console.log(data);
        $("#rules").html(data);
    });
}

/*
 * Chat
 */
class ChatMsg{
    constructor(txt="",player=0){
        var ps = $("#player-pseudo").val();
        this.data = {
            msg : txt,
            player : player,
            pseudo : ps,
            time : Date.now(),
            key : makeid(8)
        }
    }
    loadFromData(newdata){
        this.data = newdata.data;
    }
    setPseudo(pseudo){this.data.pseudo=pseudo}
    getId(){return ""+this.data.time+"-"+this.data.key}
    html(){
        var cn = "cm-player-" + ((this.data.player == Jocly.PLAYER_A) ? "a" : "b") ;
        var pn = (this.data.pseudo.length > 0)?this.data.pseudo:(this.data.player == Jocly.PLAYER_A)?t("Player A"):t("Player B");
        var d = new Date(this.data.time);
        var id = this.getId();
        // Le contenu du message et le pseudo viennent de l'AUTRE joueur via
        // le fichier de chat partage : sans echappement, un adversaire peut
        // executer du script chez nous (XSS stocke) en tapant du HTML dans le
        // chat ou comme pseudo. On echappe donc tout contenu utilisateur.
        // Les messages systeme (player == 0) sont generes localement par le
        // code (informUserInChatroom / NotifyWinner) et contiennent des liens
        // HTML voulus : eux seuls restent inseres tels quels, comme avant.
        var body = escapeHtml(this.data.msg);

        // system msgs
        if (this.data.player == 0){
            cn = "cm-system";
            pn = t("System message");
            body = this.data.msg;
        }

        return "<div id='"+escapeHtml(id)+"' class='cm-container "+cn+"'>\
            <div class='cm-time'>"+d.toLocaleString()+"</div>\
            <div class='cm-author'>"+escapeHtml(pn)+"</div>\
            <div class='cm-text'>"+body+"</div>\
        </div>" ;
    }
}
class ChatData{
    constructor(){
        this.msgs = new Array(); // array of ChatMsg objects
    }
    publish(msg,bSendToServeur=true){
        this.msgs.push(msg);
        var chatdata = JSON.stringify(msg);
        console.log("sending chatdata",chatdata);
        $("#bottom-container").append(msg.html());
        scrollDownChatRoom();
        if (bSendToServeur){
            if(matchDetails.matchId.length){
                $.post("./fileio.php", 
                {
                    chatmsg:chatdata,
                    chatioaction:"save",
                    gameid:matchDetails.matchId,            
                },
                function(results){
                    console.log(results);
                    console.log("chat msg saved...");
                });        
            }else{
                console.log("Error publish chat msg, no match id");
            }
        }
    }
    reload(){
        console.log("Chat reload requested...");
        if(matchDetails.matchId.length){
            $.post("./fileio.php", 
                {
                    chatioaction:"load",
                    gameid:matchDetails.matchId
                },
                function(json){
                    // fileio.php repond desormais avec l'en-tete
                    // Content-Type: application/json : jQuery a alors DEJA
                    // parse la reponse et nous passe un objet, pas une
                    // chaine. JSON.parse(objet) donnerait
                    // '"[object Object]" is not valid JSON'.
                    var data = (typeof json === 'string') ? JSON.parse(json) : json;
                    if (data && data.messages != undefined){
                        // reset 
                        this.msgs = [] ; 
                        var nbAdded = 0 ;                           
                        for (var i = 0 ; i < data.messages.length ; i++){
                            console.log("Nb chat msg received",data.messages.length);
                            var m = data.messages[i];
                            var msg = new ChatMsg();
                            msg.loadFromData(m);
                            this.msgs.push(msg);
                            // add it to display if not present
                            var id = "#"+msg.getId();
                            if($(id).length==0){
                                // not found, add it
                                $("#bottom-container").append(msg.html());
                                nbAdded ++;
                            }
                        }
                        if (nbAdded > 0){
                            scrollDownChatRoom();
                            if (!bchatOpen){
                                $("#chat-menu").addClass("new-messages");
                            }
                        }
                    }
                }
            );            
        }else{
            console.log("Error reload chat msgs, no match id");
        }
    }
    startRefreshPolling(delayms){
        this.chatTimerID = window.setInterval(this.reload,delayms);
    }
    stopRefreshPolling(delayms){
        if (this.chatTimerID != undefined) clearInterval(this.chatTimerID);
    }
}
var chatRoom = new ChatData();

function chatPush(msg){
    if (msg.length > 0){
        var m = new ChatMsg(msg,iamPlayer);
        chatRoom.publish(m)
        //$("#chat-input-field")
    }
}
function scrollDownChatRoom(){
    var s=$("#bottom-container");
    if (s.prop("scrollHeight") > s.height()){
        s.scrollTop(s.prop("scrollHeight") - s.height());
    }
}
function informUserInChatroom(text){
    var msg = new ChatMsg(text,0); // 0 = system
    chatRoom.publish(msg,false);
    openChat();
}



/*
 * Keyboard
 */
function handleKey(e){
    e.stopPropagation();
    console.log( "Handler for .keyup() called." , e); 
    if(e.key =="Enter" && e.target.id == "chat-input-field"){
        console.log("got it",e.target.value);
        chatPush(e.target.value);
        //e.target.value = "";
    }
    if(e.target.id == "player-pseudo"){
        if(e.key =="Enter"){
            console.log("My pseudo is "+e.target.value);
        }
        return;
    }
    if (e.target.id != "chat-input-field"){
        if (e.key == "Escape"){
            closePanel();
            closeRules();
            closeChat();
        }
        if (e.keyCode == 82){ // R or r, => rules
            openRules();
        }
        if (e.keyCode == 67){ // C or c, => controls
            openPanel();
        }
        if (e.keyCode == 84){ // T or t, => chat
            openChat();
        }
    }
}



/*
 * Match I/O
 */

function saveGameIfNecessary(match){    
    if (matchDetails.matchId.length > 0){
        match.save().then((matchdata)=>{
            saveData(matchDetails.matchId,JSON.stringify({
                matchDetails : matchDetails,
                matchdata : matchdata,
                time : Date.now(),
                key : "myverypreciouskey"
                },
                null,0));         
        });
    }
}
// Long-polling optionnel (voir push/README.md pour le pendant WebSocket) :
// n'existe QUE si index.php definit LONG_POLL_ENABLED = true, lui-meme
// controle par $enableLongPolling dans localconf.php -- absent par defaut,
// donc lastKnownMtime reste inutilise et le comportement est identique a
// avant l'ajout de ce mode.
var lastKnownMtime = 0;

function loadMatchFromID(gameid,match,waitMode){
    var postData = { gameioaction:"load", gameid:gameid };
    if (waitMode && typeof LONG_POLL_ENABLED !== 'undefined' && LONG_POLL_ENABLED) {
        postData.sinceMtime = lastKnownMtime;
    }
    $.post("./fileio.php", 
        postData,
        function(json, textStatus, jqXHR){
            var mtimeHeader = jqXHR && jqXHR.getResponseHeader ? jqXHR.getResponseHeader('X-File-Mtime') : null;
            if (mtimeHeader !== null) {
                var parsed = parseInt(mtimeHeader, 10);
                if (!isNaN(parsed)) lastKnownMtime = parsed;
            }
        // the output of the response is now handled via a variable call 'results'
            // Rien a charger pour l'instant (ex. l'autre joueur n'a pas
            // encore fait son premier coup, le match n'existe pas encore
            // cote serveur) : json est vide, ou son contenu n'a pas la forme
            // attendue -- on ignore silencieusement plutot que de planter,
            // le prochain sondage reessaiera.
            var data;
            // Comme fileio.php envoie Content-Type: application/json,
            // jQuery parse deja la reponse : json est alors un objet.
            try { data = (typeof json === 'string') ? JSON.parse(json) : json; }
            catch (e) {
                // Serveur dont localconf.php (ou une notice PHP) ecrit du
                // texte AVANT le JSON : on retente a partir de la premiere
                // accolade plutot que de jeter un vrai etat de partie.
                var start = (typeof json === 'string') ? json.indexOf('{') : -1;
                if (start > 0) {
                    try { data = JSON.parse(json.slice(start)); }
                    catch (e2) { console.log("loadMatchFromID: reponse non-JSON ignoree", e2); return; }
                } else {
                    if (json && String(json).trim().length)
                        console.log("loadMatchFromID: reponse non-JSON ignoree", e);
                    return;
                }
            }
            if (!data || !data.matchDetails || !data.matchdata) { return; }

            if (matchDetails.nbTurns != data.matchDetails.nbTurns){
                matchDetails.nbTurns = data.matchDetails.nbTurns;
                
                console.log("HE HAS PLAYED!!");

                var lastOpponentMove = data.matchdata.playedMoves.pop();
                match.load(data.matchdata).then( () => {
                    return match.playMove(lastOpponentMove);
                }) ;
            }else{
                // load match 
                match.load(data.matchdata) ;
            }
            console.log("loading data : ",json);
        }
    );
}

function saveData(gameid,gamedata){
    console.log("gamedata",gamedata);
    $.post("./fileio.php", 
        {
            gamedata:gamedata,
            gameioaction:"save",
            gameid:gameid,            
        },
        function(results){
            console.log(results);
            console.log("game saved...");
        }
    );
}


/*
 * Window updates
 */

var gameTitle = "";
function updateGameTitle(){
    var playerTxt =  (iamPlayer == Jocly.PLAYER_A) ? t("Player A") : t("Player B");
    $("#game-title").show().text(gameTitle + " • " + playerTxt); 
    var ps = $("#player-pseudo").val();
    if (ps.length==0) $("#player-pseudo").val((iamPlayer == Jocly.PLAYER_A) ? t("Player A") : t("Player B"));
 
}
function recomputeChatRoom(){
    var bottomOffset = $("#overhead-menu").height()+10;
    $("#chat-input").css("bottom",bottomOffset);
    $("#prev-msgs").css("height",$("#panel-chat").height() - $("#chat-input").height() - bottomOffset - 37)
}
function changeFavicon(myTurn) {
    var dir = myTurn?"green":"normal";
    $('head').find('link[rel$="icon"]').each(function(idx){
        var sizes = $(this).attr("sizes");
        if (sizes=="32x32") $(this).attr("href","i/favicons/"+dir+"/favicon-32x32.png");
        if (sizes=="13x13") $(this).attr("href","i/favicons/"+dir+"/favicon-13x13.png");
        if (sizes=="180x180") $(this).attr("href","i/favicons/"+dir+"/apple-touch-icon.png");
    })
    $('link[rel="manifest"]').attr('href', "i/favicons/"+dir+"/site.webmanifest");
}


/*
 * Init
 */

$(document).ready(function () {

    var progressBar = document.getElementById("progress-bar");
    var gameName = matchDetails.gameName ;
    var elementId = "applet";
    var area = document.getElementById(elementId);

    console.log("matchId", matchDetails.matchId);
    console.log("gameName", gameName);

    if (matchDetails.matchId.length == 0){ // no match !
        window.location.href = "gamespanel.php";
    }

    closePanel();
    closeRules();
    closeChat();
    recomputeChatRoom();

    $( window ).resize(function() {
        recomputeChatRoom();
    });
    $( "#chat-input-field" ).on("keyup",function(e) {
        if (e.originalEvent.key == "Enter")
            $( this ).val("");
    });

    document.addEventListener('keydown', handleKey, false);
    
    chatRoom.reload();
    chatRoom.startRefreshPolling(3000);
    
    Jocly.getGameConfig(gameName).then((p)=>{
        gameFullPath = p.view.fullPath ;
        var rulesPath = p.view.fullPath+"/"+p.model.rules.en ;
        console.log(rulesPath);
        loadRules(rulesPath,p.view.fullPath);
    })

    Jocly.createMatch(gameName).then((match) => {
        console.log("iamPlayer",iamPlayer);

        if(matchDetails.matchId.length){
            loadMatchFromID(matchDetails.matchId,match);
        }
        // get game configuration to setup control UI
        match.getConfig()
            .then( (config) => {
                gameTitle = config.model["title-en"];
                updateGameTitle();
                $("#close-games span").show();
                $("#game-status").show();

                var welcomeMsg = t("Hi!")+"<br>"+t("You are")+" : "+(iamPlayer == Jocly.PLAYER_A ? t("Player A") : t("Player B"));
                informUserInChatroom(welcomeMsg);

                var viewOptions = config.view;
                // fills Skins dropdown with available skins
                viewOptions.skins.forEach(function(skin) {
                    $("<option/>").attr("value",skin.name).text(skin.title).appendTo($("#options-skin"));
                });
                $("#options").show();

                // get saved view options if any
                var viewOptions = window.localStorage && window.localStorage[gameName+".options"] && 
                    JSON.parse(window.localStorage[gameName+".options"]) || undefined;

                // the match need to be attached to a DOM element for displaying the board
                match.attachElement(area, { viewOptions: viewOptions })
                    .then( () => {
                            return match.getViewOptions();
                        })
                    // get options for the game view
                    .then( (options) => {

                            $("#options-skin").show().val(options.skin);
                            if(options.sounds!==undefined)
                                $("#options-sounds").show().children("input").prop("checked",options.sounds);
                            $("#options-notation").hide();
                            if(options.notation!==undefined)
                                $("#options-notation").show().children("input").prop("checked",options.notation);
                            $("#options-moves").hide();
                            if(options.showMoves!==undefined)
                                $("#options-moves").show().children("input").prop("checked",options.showMoves);
                            $("#options-autocomplete").hide();
                            if(options.autoComplete!==undefined)
                                $("#options-autocomplete").show().children("input").prop("checked",options.autoComplete);

                            $("#view-options").on("change",function() {
                                var opts={};
                                if($("#options-skin").is(":visible")) 
                                    opts.skin=$("#options-skin").val();
                                if($("#options-notation").is(":visible"))
                                    opts.notation=$("#options-notation-input").prop("checked");
                                if($("#options-moves").is(":visible"))
                                    opts.showMoves=$("#options-moves-input").prop("checked");
                                if($("#options-autocomplete").is(":visible"))
                                    opts.autoComplete=$("#options-autocomplete-input").prop("checked");
                                if($("#options-sounds").is(":visible"))
                                    opts.sounds=$("#options-sounds-input").prop("checked");
                                // changed options, tell Jocly about it
                                match.setViewOptions(opts)
                                    .then( () => {
                                        RunMatch(match,progressBar);                                
                                    })
                                if(window.localStorage)
                                    window.localStorage.setItem(gameName+".options",JSON.stringify(opts));
                            });

                            $("#anaglyph-input").on("change",function() {
                                if($(this).is(":checked"))
                                    match.viewControl("enterAnaglyph");
                                else
                                    match.viewControl("exitAnaglyph");
                            });

                            if(config.view.switchable) {

                                $("#playa-button").click(function(){
                                    iamPlayer = Jocly.PLAYER_A ;
                                    player = iamPlayer;
                                    console.log("player set to a")
                                    match.setViewOptions({
                                        viewAs: player
                                    })
                                    .then( () => {
                                        RunMatch(match,progressBar);                                
                                    });
                                });
                                $("#playb-button").click(function(){
                                    iamPlayer = Jocly.PLAYER_B ;
                                    player = iamPlayer;
                                    match.setViewOptions({
                                        viewAs: player
                                    })
                                    .then( () => {
                                        RunMatch(match,progressBar);                                
                                    });
                                });
                            }
                            player = iamPlayer;
                            match.setViewOptions({
                                viewAs: iamPlayer
                            }).then( () => {
                                RunMatch(match,progressBar);                                
                            })
                        })
                    .then( () => {
                        RunMatch(match,progressBar);
                    });

                /* $("#browsegame").show().on("click",function(){
                    promise = match.abortUserTurn() // just in case one is running
                        .then( () => {
                            return match.abortMachineSearch(); // just in case one is running
                        });
                });
                $("#backtogame").show().on("click",function(){
                    RunMatch(match,progressBar);
                });*/


                $("#replaylastmove").show().on("click",function(){
                    match.getPlayedMoves()
                    .then( (playedMoves) => {
                        if (playedMoves.length > 1){
                            lastMove = playedMoves[playedMoves.length-1];
                            console.log("last move", lastMove);
                            match.rollback(playedMoves.length-1)
                            .then( () => {
                                return match.playMove(lastMove);
                            });
                        }
                    });
                });
                $("#restart").on("click",function() {
                    // restart match from the beginning
                    match.rollback(0)
                        .then( () => {
                            RunMatch(match,progressBar);
                        });
                });

                $("#save").on("click",function() {
                    // save match to the file system
                    match.save()
                        .then( (data) => {
                            var json = JSON.stringify(data,null,2);
                            var a = document.createElement("a");
                            var uriContent = "data:application/octet-stream," + encodeURIComponent(json);
                            a.setAttribute("href",uriContent);
                            a.setAttribute("download",gameName+".json");
                            a.click();
                        });
                });

				$("#snapshot").on("click",function() {
					match.viewControl("takeSnapshot",{
						format: "jpeg"
					})
						.then((snapshot)=>{
							var a = document.createElement("a");
							a.href = snapshot;
							a.setAttribute("download",gameName+".jpg");
							a.click();
						})
						.catch((error)=>{
							console.warn("failed:",error);
						})
				});

                // reading file locally
                var fileElem = $("#fileElem").on("change",function() {
                    var fileReader = new FileReader();
                    fileReader.readAsText(fileElem[0].files[0]);
                    fileReader.onload = function(event) {
                        var json = event.target.result;
                        var data = JSON.parse(json);
                        // load match 
                        match.load(data)
                            .catch((e)=>{
                                console.info("Failed to load",e);
                            });
                        RunMatch(match,progressBar);
                    }
                })
                $("#load").on("click",function() {
                    fileElem[0].click();
                });

                // reading file locally
                var file360Elem = $("#file360Elem").on("change",function() {
                    var fileReader = new FileReader();
                    fileReader.readAsDataURL(file360Elem[0].files[0]);
                    fileReader.onload = function(event) {
						match.viewControl("setPanorama",{
							pictureData: fileReader.result
						})
                    }
                })
                $("#panorama-button").on("click",function() {
                    file360Elem[0].click();
                });
                $("#panorama-select").on("change",function() {
					var options = {};
					var which = $(this).val();
					if(which)
						options.pictureUrl = "../../panorama/"+which+".jpg";
					match.viewControl("setPanorama",options);
                });

                $("#takeback").on("click",function() {
                    match.getPlayedMoves()
                        .then( (playedMoves) => {
                            // we want to go back to the last user move
                            var mode = $("#mode").val();
                            var lastUserMove = -1;
                            if( 
                                ((playedMoves.length % 2 == 1) && (mode=="self-self" || mode=="self-comp")) ||
                                ((playedMoves.length % 2 == 0) && (mode=="self-self" || mode=="comp-self"))
                                )
                                    lastUserMove = playedMoves.length - 1;
                            else if( 
                                ((playedMoves.length % 2 == 1) && (mode=="self-self" || mode=="comp-self")) ||
                                ((playedMoves.length % 2 == 0) && (mode=="self-self" || mode=="self-comp"))
                                )
                                    lastUserMove = playedMoves.length - 2;
                            if(lastUserMove>=0)
                                match.rollback(lastUserMove)
                                    .then( () => {
                                        RunMatch(match,progressBar);
                                    });
                            
                        });
                });

                // yeah, using the fullscreen API is not as easy as it should be
                var requestFullscreen = area.requestFullscreen || area.webkitRequestFullscreen || 
                    area.webkitRequestFullScreen || area.mozRequestFullScreen;
                if(requestFullscreen) {
                    $(document).on("webkitfullscreenchange mozfullscreenchange fullscreenchange",()=>{
                        var isFullscreen = document.webkitFullscreenElement || document.webkitFullScreenElement || 
                            document.mozFullScreenElement || document.fullscreenElement;
                        if(isFullscreen)
                            area.style.display = "block";
                        else
                            area.style.display = "table-cell";
                        RunMatch(match,progressBar);    
                    });
                    $("#fullscreen").show().on("click",function() {
                        requestFullscreen.call(area);
                    });
                }

                $("#links").on("click",()=>{
                    $("#controls").hide();
                    $("#games").show();
                });

                $("#close-games span").on("click",()=>{
                    $("#controls").show();
                    $("#games").hide();
                });

                $("#mode-panel").show();
            });
    });

    // save enlglish original texts
    $(".t").each(function(){
        $(this).attr("en-txt",this.innerText);
    });
    // update language
    if (window.localStorage["lg"]) setLanguage(window.localStorage["lg"]);
});



/*
 * Translations
 */
var translations = {
    "Close options panel" : {fr : "Fermer le panneau"},
    "Play Jocly Game" : {fr : "Jouer Jeu Jocly"},
    "Controls" : {fr : "Contrôles"},
    "Full screen" : {fr : "Plein écran"},
    "Save" : {fr : "Sauver"},
    "Snapshot" : {fr : "Copie d'écran"},
    "Options" : {fr : "Options"},
    "Notation" : {fr : "Notations"},
    "Show possible moves" : {fr : "Montrer les coups possibles"},
    "Sounds" : {fr : "Sons"},
    "All games panel" : {fr : "Panneau des jeux"},
    "Play A" : {fr : "Jouer A"},
    "Play B" : {fr : "Jouer B"},
    "Rules" : {fr : "Règles"},
    "Jocly on Github" : {fr : "Jocly sur Github"},
    "A wins" : {fr : "A gagne"},
    "B wins" : {fr : "B gagne"},
    "Draw" : {fr : "Egalité"},
    "A playing" : {fr: "A joue"},
    "B playing" : {fr: "B joue"},
    "Player A" : {fr: "Joueur A"},
    "Player B" : {fr: "Joueur B"},
    "Replay last move" : {fr: "Rejouer dernier coup"},
    "My name" : {fr: "Mon nom"},
    "Chat" : {fr: "Clavardage"},
    "End of game" : {fr : "Fin de partie"},
    "System message" : {fr : "Message systême"},
    "Hi!" : {fr : "Bonjour!"},
    "You are" : {fr : "Vous êtes"},
    "Start a new game?" : {fr:"Démarrer un nouvelle partie?"},
    "Or go back to all games" : {fr : "Ou retourner aux autres jeux"},
    "Click here": {fr : "Cliquer ici"},
    "Browse game": {fr : "Inspecter le jeu"},
    "Back to game": {fr : "Retour au jeu"}
}

function t(txt){
    if (translations[txt] && translations[txt][lg]){
        return translations[txt][lg];
    }
    return txt;
}
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
    }
}

