$(document).ready(function() {
    $(".oltaselement").change(function() {
        checkOltasForm();
    });
    $("#oltas-submit-button").click(function() {
        let message = checkOltasForm();
        if (message != "") {
            myAlert(message);
            return;
        }

        let params = $("#oltasform").serialize();
        params["g-recaptcha-response"] = $("#g-recaptcha-response").val();

        $.ajax({
            type: 'POST',
            url: 'index.php?page=oltasigenyfelmeres&oltasformsavedata',
            data: params,
            success: function (result) {
                if (result.error !== "") {
                    myAlert(result.error);
                    grecaptcha.reset();
                    return;
                }
                $("#oltasformdiv").html(result.html);
            }
        });

    });

    checkOltasForm();
});

function checkOltasForm() {
    let formMessage = "";

    let csoport = $('input[name=csoport]:checked', '#oltasform').val();
    let allergia = $('input[name=allergia]:checked', '#oltasform').val();
    let anafilaxia = $('input[name=anafilaxia]:checked', '#oltasform').val();
    let betegseg = $('input[name=betegseg]:checked', '#oltasform').val();

    let lazas = $('input[name=lazas]:checked', '#oltasform').val();
    let atesett = $('input[name=atesett]:checked', '#oltasform').val();
    let veralvadas = $('input[name=veralvadas]:checked', '#oltasform').val();
    let terhes = $('input[name=terhes]:checked', '#oltasform').val();
    let fogamzasgatlas = $('input[name=fogamzasgatlas]:checked', '#oltasform').val();
    let vedooltas = $('input[name=vedooltas]:checked', '#oltasform').val();
    let oltasregisztralt = $('input[name=oltasregisztralt]:checked', '#oltasform').val();
    let oltasmegkapta = $('input[name=oltasmegkapta]:checked', '#oltasform').val();

    if (csoport === "egyeb") {
        $("#csoporttextdiv").slideDown();
    } else {
        $("#csoporttextdiv").slideUp();
    }

    if (allergia === "1") {
        $("#allergiatextdiv").slideDown();
    } else {
        $("#allergiatextdiv").slideUp();
    }

    if (anafilaxia === "1") {
        $("#anafilaxiatextdiv").slideDown();
    } else {
        $("#anafilaxiatextdiv").slideUp();
    }

    if (betegseg === "1") {
        $("#betegsegtextdiv").slideDown();
    } else {
        $("#betegsegtextdiv").slideUp();
    }

    if (formMessage == "") {
        if (atesett === undefined || veralvadas === undefined || allergia === undefined || anafilaxia === undefined || betegseg === undefined || lazas === undefined || terhes === undefined || fogamzasgatlas === undefined || vedooltas === undefined || oltasregisztralt === undefined || oltasmegkapta === undefined) {
            formMessage = "Kérjük válaszoljon az összes kérdésre!";
        }
    }

    if (formMessage == "" && $("#gdpr").prop("checked") !== true) {
        formMessage = "Kérjük fogadja el az adatvédelmi nyilatkozatot!";
    }

    if (formMessage == "" && $("#responsiblity-confirmed").prop("checked") !== true) {
        formMessage = "Kérjük fogadja el a nyilatkozatot, hogy a megadott adatok a valóságnak megfelelnek!";
    }

    return formMessage;
}

