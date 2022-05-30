$(document).ready(function() {
    $("#suzukiform-submit-button").click(function() {
        let message = checkSuzukiForm();
        if (message != "") {
            myAlert(message);
            return;
        }

        let params = $("#suzukiform").serialize();
        params["g-recaptcha-response"] = $("#g-recaptcha-response").val();

        $.ajax({
            type: 'POST',
            url: 'index.php?page=suzukiform&formsavedata',
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

});

function checkSuzukiForm() {
    let formMessage = "";

    return formMessage;
}

