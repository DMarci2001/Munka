$().ready(function() {
    Schedule.Init();
});


Schedule = {
    URL: "index.php?page=workschedule",
    DialogCloseHTML: "<div id='dialogclose' style='width:20px;height:20px;float:right;'></div>",
    DialogId: "",

    Init: function(){

        $( "#dialogclose" ).click(function() {
            $(".sch_dialog").hide();
        });

        $(".sch_dialog").draggable();

    },
    ShowAddWorkerDialog: function(el) {
        Schedule.DialogId = el;
        let roleid  = $(el).data("roleid");
        let tipusid = $(el).data("tipusid");
        let napszak = $(el).data("napszak");
        let datum   = $(el).data("datum");
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworkerdialog=1&roleid="+roleid+"&tipusid="+tipusid+"&napszak="+napszak+"&datum="+datum,
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

    },
    Addworker: function () {
        let params = $("#dialogform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworker=1&"+params,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+$(Schedule.DialogId).data("datum")).html(data.message);
                $(".sch_dialog").hide();
            }
        });

    }
};

