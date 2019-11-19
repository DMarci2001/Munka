$().ready(function() {
    Schedule.Init();
});


Schedule = {
    URL: "index.php?page=workschedule",
    DialogCloseHTML: "<div id='dialogclose' style='width:20px;height:20px;float:right;'></div>",

    Init: function(){

        $( "#dialogclose" ).click(function() {
            $(".sch_dialog").hide();
        });

        $(".sch_dialog").draggable();

    },
    ShowAddWorkerDialog: function(el) {
        let roleid = $(el).data("roleid");
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworker=1&tipus="+roleid,
            success: function(data)	{
                let position = $(el).offset();
                let left = position.left + 15;

                $(".sch_dialogcontent").html(data);
                $(".sch_dialogtop").html($(el).data("tipusnev")+Schedule.DialogCloseHTML);
                Schedule.Init();
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

