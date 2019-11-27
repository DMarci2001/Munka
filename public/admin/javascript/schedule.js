$().ready(function() {
    Schedule.Init();
});

let ctrlDown = false;
$(window).on("keydown", function(event) {
    if (event.which === 17) {
        ctrlDown = true;
    }
}).on("keyup", function(event) {
    ctrlDown = false;
});


Schedule = {
    URL: "index.php?page=workschedule",
    DialogCloseHTML: "<div id='dialogclose' style='width:20px;height:20px;float:right;'></div>",
    DialogId: "",
    CopySourceDate: "",

    Init: function(){
        $( "#dialogclose" ).click(function() {
            $(".sch_dialog").hide();
        });

        $(".sch_dialog").draggable();
        $(".workerlink").draggable({"revert":true, helper: 'clone'});
        $(".sch_oszlopdatacell" ).droppable({
            classes: {
                "ui-droppable-hover": "sch_oszlopdatacell_hover"
            },
            accept: function(d) {
                let sourceDate = $(d).find("a").data("datum");
                let targetDate = $(this).data("datum");
                let sourceRole = $(d).find("a").data("roleid");
                let targetRole = $(this).data("roleid");
                let sourceType = $(d).find("a").data("tipusid");
                let targetType = $(this).data("tipusid");
                Schedule.CopySourceDate = sourceDate;
                if(sourceRole == targetRole && (sourceType != targetType || sourceDate != targetDate)) {
                    return true;
                }
            },
            drop: function(event, ui) {
                let sourceId = $(ui.draggable).find("a").data("mapid");
                Schedule.CopyWorker(sourceId, this);
            }
        });

    },
    ShowAddWorkerDialog: function(el) {
        Schedule.DialogId = el;
        let mapid   = $(el).data("mapid");
        let roleid  = $(el).data("roleid");
        let tipusid = $(el).data("tipusid");
        let napszak = $(el).data("napszak");
        let datum   = $(el).data("datum");
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworkerdialog=1&mapid="+mapid+"&roleid="+roleid+"&tipusid="+tipusid+"&napszak="+napszak+"&datum="+datum,
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
    AddWorker: function () {
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
                Schedule.Init();
            }
        });
    },
    DeleteWorker: function () {
        let params = $("#dialogform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "deleteworker=1&"+params,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+$(Schedule.DialogId).data("datum")).html(data.message);
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    CopyWorker: function (sourceId, targetId) {
        let roleid    = $(targetId).data("roleid");
        let tipusid   = $(targetId).data("tipusid");
        let napszak   = $(targetId).data("napszak");
        let datum     = $(targetId).data("datum");
        let operation = ctrlDown ? "copy":"move";

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "copyworker=1&sourceid="+sourceId+"&roleid="+roleid+"&tipusid="+tipusid+"&napszak="+napszak+"&datum="+datum+"&operation="+operation,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+datum).html(data.message);
                if (data.messageSource != "") {
                    $("#daycontainer" + Schedule.CopySourceDate).html(data.messageSource);
                }
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    }
};

