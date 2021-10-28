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

            window.location.href='index.php?page=elsosegelyvizsga&subpage=done';

            //$("#vizsgaformdiv").html(result.html);
        }
    });


}
