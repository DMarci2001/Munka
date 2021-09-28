function generateDailyStat(el) {

    let day = $(el).parent().data("day");
    let dayBox = $("#daybox"+day);

    $("#daytext"+day).html("dokirex query <img style='height: 11px;' src='/images/loading_transparent.svg' alt='' /><br/>beosztás query <img style='height: 11px;' src='/images/loading_transparent.svg' alt='' />");


    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat",
        data: "day=" + encodeURIComponent(day) + "&generatedailystat=1",
        success: function (response) {
            $(dayBox).html(response.html);
        }
    });


}