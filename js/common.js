var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
function makeid(length) {
    var result           = '';
    var charactersLength = characters.length;
    for ( var i = 0; i < length; i++ ) {
       result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
}

String.prototype.replaceAt=function(index, replacement) {
    return this.substr(0, index) + replacement+ this.substr(index + replacement.length);
}
function incId(id){
    console.log("id before",id);
    var nbDigits = characters.length;
    var pos = id.length - 1 ;
    function incDigit(){
        var order = characters.indexOf(id[pos]);
        console.log(pos);
        if (order == (nbDigits-1)){
            id = id.replaceAt(pos,characters[0]);
            pos --;
            if(pos > 0) incDigit();
            else console.log ("error");
        }else{
            id = id.replaceAt(pos,characters[order+1]);
        }
    }
    incDigit(pos)
    console.log("id  after",id);
    return id;
}