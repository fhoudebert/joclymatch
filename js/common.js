var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
// L'id de partie est le SEUL controle d'acces a un match : quelqu'un qui le
// devine peut lire la partie et le chat, et jouer. Math.random() n'est pas
// concu pour ca (graine observable/predictible selon le moteur) ; on tire
// donc les caracteres via crypto.getRandomValues (present dans tous les
// navigateurs qui font tourner Jocly), avec rejet des valeurs hautes pour
// une repartition uniforme. Meme alphabet, memes longueurs, meme format
// d'id qu'avant -- rien ne change pour le reste du code ni les liens
// existants. Math.random() reste en secours si crypto manquait.
function makeid(length) {
    var result           = '';
    var charactersLength = characters.length;
    var cryptoObj = (typeof crypto !== 'undefined') ? crypto : null;
    if (cryptoObj && cryptoObj.getRandomValues) {
        // plus grand multiple de charactersLength <= 256, pour rejeter sans biais
        var limit = Math.floor(256 / charactersLength) * charactersLength;
        var buf = new Uint8Array(1);
        for ( var i = 0; i < length; i++ ) {
            do { cryptoObj.getRandomValues(buf); } while (buf[0] >= limit);
            result += characters.charAt(buf[0] % charactersLength);
        }
    } else {
        for ( var i = 0; i < length; i++ ) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
        }
    }
    return result;
}

// Fonction locale plutot qu'une extension de String.prototype : etendre les
// prototypes natifs expose tout le reste de la page (dont la lib Jocly) a
// des collisions de noms. Meme logique qu'avant, appel replaceAt(id,...)
// au lieu de id.replaceAt(...).
function replaceAt(str, index, replacement) {
    return str.substr(0, index) + replacement + str.substr(index + replacement.length);
}
function incId(id){
    console.log("id before",id);
    var nbDigits = characters.length;
    var pos = id.length - 1 ;
    function incDigit(){
        var order = characters.indexOf(id[pos]);
        console.log(pos);
        if (order == (nbDigits-1)){
            id = replaceAt(id,pos,characters[0]);
            pos --;
            // >= 0 (et non > 0) : sinon une retenue arrivant sur le PREMIER
            // caractere n'etait jamais traitee et l'id revenait inchange
            // (rematch ecrasant la partie precedente). Si pos < 0, l'id
            // entier a deborde ("999...9") : cas quasi impossible avec des
            // ids tires au hasard, on garde le log d'erreur historique.
            if(pos >= 0) incDigit();
            else console.log ("error");
        }else{
            id = replaceAt(id,pos,characters[order+1]);
        }
    }
    incDigit(pos)
    console.log("id  after",id);
    return id;
}

// Echappe les 5 caracteres speciaux HTML. Utilise par ChatMsg.html()
// (js/control.js) pour neutraliser le contenu ecrit par l'autre joueur
// (message et pseudo) avant insertion dans le DOM.
function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}
