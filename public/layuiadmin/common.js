document.addEventListener("keydown", function (e) {
    if (e.keyCode == 116) {
        e.preventDefault();
        //要做的其他事情
        window.location.reload();
        return false;
    }
}, false);