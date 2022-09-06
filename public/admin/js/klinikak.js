function toggleKlinikaEditor(id) {
    $("#datarow"+id).toggle();

    $.ajax({
        method: "POST",
        url: "index.php",
        data: {page:"klinikak", showklinikaeditor:id}
    }).done(function (msg) {
        $("#datarow"+id).html(msg);
    });
}

function saveKlinikaData(id) {
    let datas = "page=klinikak&saveklinikadata="+id+"&" + $("#klinikaform"+id).serialize();

    $.ajax({
        method: "POST",
        url: "index.php",
        data: datas
    }).done(function (msg) {
        $("#klinikalista").html(msg.html);
        $.toast({
            text: msg.message,
            icon: 'success',
            hideAfter: 3000
        });
    });
}

function deleteKlinika(id) {
    if (!confirm("Biztos törlöd a klinikát?")) {
        return;
    }

    let datas = "page=klinikak&deleteklinika="+id;

    $.ajax({
        method: "POST",
        url: "index.php",
        data: datas
    }).done(function (msg) {
        $("#klinikalista").html(msg.html);
        $.toast({
            text: msg.message,
            icon: 'success',
            hideAfter: 3000
        });
    });
}

function tipusFilterApply() {
    let datas = "page=klinikak&filterklinikak=1&" + $("#tipusfilterform").serialize();

    $.ajax({
        method: "POST",
        url: "index.php",
        data: datas
    }).done(function (msg) {
        $("#klinikalista").html(msg.html);
    });
}
