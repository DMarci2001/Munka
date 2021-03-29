$(document).ready(function() {

});


function elsosegelyFormSubmit() {

    let params = $("#vizsgaform").serialize();

    $.ajax({
        type: 'POST',
        url: 'index.php?page=elsosegelyvizsga&vizsgaformsavedata',
        data: params,
        success: function (result) {
            if (result.error !== "") {
                myAlert(result.error);
                return;
            }
            $("#vizsgaformdiv").html(result.html);
        }
    });


}
