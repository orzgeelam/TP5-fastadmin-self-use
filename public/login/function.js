function cdnUrl(url) {
    return /^(?:[a-z]+:)?\/\//i.test(url) ? url : myConfig.upload.cdn_url + url;
}

function jsCopy(obj){
    var url = document.getElementById(obj);
    url.select();
    document.execCommand('Copy');
}