$().ready(function() {
    Schedule.Init();
});


Schedule = {
    URL: "index.php?page=workschedule",

    Init: function(){

        $( "#dialogclose" ).click(function() {
            $(".sch_dialog").hide();
        });

        $(".sch_dialog").draggable();

    },
    ShowAddDoctorDialog: function(el) {

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "adddoctors=1&tipua=1",
            success: function(data)	{
                let position = $(el).offset();
                let left = position.left + 15;

                $(".sch_dialogcontent").html(data);
                $(".sch_dialog").show();

                let width = $(".sch_dialog").width();
                let winWidth = $(window).width();
                if (left + width > winWidth) {
                    left = winWidth - width;
                }

                $(".sch_dialog").css("top", position.top + 15);
                $(".sch_dialog").css("left", left);
            }
        });

    }
};

