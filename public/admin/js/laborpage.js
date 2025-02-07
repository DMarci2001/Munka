$(document).ready(function () {
    initLaborEditor();

    $('#futurefiltercheckbox').change(function() {
        let futureFilter = 0;
        if (this.checked) {
            futureFilter = 1;
        }

        $.ajax({
            type:"POST",
            url:"index.php?page=labrequests",
            data: {filterchange:1, futureFilter:futureFilter},
            success: function(response){
                $("#labrequestlist").html(response);
            }
        })
    });


});


function initLaborEditor() {
    $(document).on("change", ".laborcsomagpricetextbox", function(){
        let price = $(this).val();
        let cid = $(this).data("cid");
        let tid = $(this).data("tid");
        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {changeLaborCsomagPrice:price, cid:cid, tid:tid},
            success: function(response){
                $.toast({
                    text: "Csomag ár mentve",
                    icon: 'success'
                });
            }
        })
    });

    $(document).on("change", ".laboritempricetextbox", function(){
        let price = $(this).val();
        let tid = $(this).data("tid");
        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {changeLaborItemPrice:price, tid:tid},
            success: function(response){
                $.toast({
                    text: "Tétel ár mentve",
                    icon: 'success'
                });
            }
        })
    });

    $(document).on("change", ".laboritemelkeszulestextbox", function(){
        let value = $(this).val();
        let tid = $(this).data("tid");
        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {changeLaborElkeszules:value, tid:tid},
            success: function(response){
                $.toast({
                    text: "Elkészülés mentve",
                    icon: 'success'
                });
            }
        })
    });

    $(document).on("change", "#companycsomag", function(){
        let cid = $(this).val();
        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {changeLaborCsomagCompany:cid},
            success: function(response){
                $("#labortetelek-form").html(response);
            }
        })
    });


    $('.csitemcheckbox').change(function() {
        let csomagId = $(this).data("csomagid");
        let itemId = $(this).data("itemid");
        let checked = 0;
        let message = "eltávolítva";
        if (this.checked) {
            checked = 1;
            message = "hozzáadva";
        }

        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {selectItemForPackage:itemId, csomagId:csomagId, checked:checked},
            success: function(response){
                $.toast({
                    text: "item "+message,
                    icon: 'success'
                });
            }
        })
    });

    $(document).on("change", ".spektrumlabparositas", function(){
        let id = $(this).data("id");
        let spid = $(this).val();

        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {spektrumlabparositas:1, id:id, spid:spid},
            success: function(response){
                $.toast({
                    text: "Párosítás mentve",
                    icon: 'success'
                });
            }
        })
    });

    $(document).on("change", ".synlabcommcodeinput", function(){
        let id = $(this).data("id");
        let code = $(this).val();

        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {synlabcodeinput:1, id:id, code:code},
            success: function(response){
                $.toast({
                    text: "Kód mentve",
                    icon: 'success'
                });
            }
        })
    });

    initItemCheckboxes();
}


function initItemCheckboxes() {
    $(".csitemcheckbox2").click(function() {
        let csomagId = $(this).data("csomagid");
        let itemId = $(this).data("itemid");
        let checked = 0;
        let message = "eltávolítva";
        if (!$(this).hasClass("serviceselected")) {
            checked = 1;
            message = "hozzáadva";
        }

        $.ajax({
            type:"POST",
            url:"index.php?page=labortetelek",
            data: {selectItemForPackage2:itemId, csomagId:csomagId, checked:checked},
            success: function(response){
                $("#itemeditordiv").html(response);
                initItemCheckboxes();
                $.toast({
                    text: "Vizsgálat "+message,
                    icon: 'success'
                });
            }
        });

        return false;
    });

    $("#csomagVizsgalatFilterText").keyup(function () {
        let filter = $(this).val();

        if (filter.length >= 2) {
            $(".csitemcheckbox2").hide();
            $('.csitemcheckbox2').each(function (i, obj) {
                let vizsgalat = $(this).html();
                if (vizsgalat.toLowerCase().includes(filter.toLowerCase())) {
                    $(this).show();
                }
            });
        } else {
            $(".csitemcheckbox2").show();
        }
    });
}

function changeLaborCsomagCompanyShow(id){
    let tid = $(id).data("tid");
    let companyid = $(id).data("companyid");
    $.ajax({
        type:"POST",
        url:"index.php?page=labortetelek",
        data: {changeLaborCsomagCompanyShow:true,tid:tid,companyid:companyid},
        success: function(response){
            $.toast({
                text: response,
                icon: 'success'
            });
        }
    })
}

function importCsomapPublicPrice(cid, tid) {
    $.ajax({
        type:"POST",
        url:"index.php?page=labortetelek",
        data: {importCsomagPublicPrice:tid, cid:cid},
        success: function(response){
            $("#csomagprice"+tid).val(response);
            $.toast({
                text: "Publikus ár beemelve",
                icon: 'success'
            });
        }
    })
}


function showLaborPaciensEditor(rid) {
    $.ajax({
        type:"POST",
        url:"index.php?page=labrequests",
        data: {showlaborpacienseditor:rid},
        success: function(response){
            if (response.error != "") {
                alert(response.error);
                return;
            }
            showGeneralPopup(response.html);
        }
    })
}

function sendLeletWindow(el) {
    $.ajax({
        type:"POST",
        url:"index.php?page=labrequests",
        data: {showSendLeletWindow:$(el).data("id")},
        success: function(response){
            showGeneralPopup(response);
        }
    })
}

function storeLabKiertekeles(el) {
    let rid = $(el).data("id");

    $.ajax({
        type:"POST",
        url:"index.php?page=labrequests",
        data: { storeLabKiertekeles:rid },
        success: function(response){
            if (response.error != "") {
                alert(response.error);
                return;
            }
            $("#requestrow"+rid).html(response.html);
            $.toast({
                text: response.message,
                icon: 'success'
            });
        }
    })

}


function saveLaborPaciensData(hide) {
    let rid = $("#laborrequestid").val();
    let nev = $("#laborpaciensnev").val();
    let taj = $("#laborpacienstaj").val();
    let szuldatum = $("#laborpaciensszuldatum").val();
    let email = $("#laborpaciensemail").val();
    let labormegjtext = $("#labormegjtext").val();
    let laboremailtext = "-";
    if($("#laboremailtext").val()) {
        laboremailtext = $("#laboremailtext").val();
    }

    if (hide === 1) {
        hideGeneralPopup();
    }

    $.ajax({
        type:"POST",
        url:"index.php?page=labrequests",
        data: {savelaborpaciensdata:rid, nev:nev, taj:taj, szuldatum:szuldatum, email:email, laboremailtext:laboremailtext, labormegjtext: labormegjtext},
        success: function(response){
            if (response.error != "") {
                alert(response.error);
                return;
            }
            $("#requestrow"+rid).html(response.html);
            $.toast({
                text: "Paciens adatok mentve: "+nev,
                icon: 'success'
            });
        }
    })
}

function sendLeletEmail() {
    let rid = $("#laborrequestid").val();
    let nev = $("#laborpaciensnev").val();
    let taj = $("#laborpacienstaj").val();
    let szuldatum = $("#laborpaciensszuldatum").val();
    let email = $("#laborpaciensemail").val();
    let laboremailtext = $("#laboremailtext").val();
    let labormegjtext = $("#labormegjtext").val();

    $.ajax({
        type:"POST",
        url:"index.php?page=labrequests",
        data: {savelaborpaciensdata:rid, nev:nev, taj:taj, szuldatum:szuldatum, email:email, laboremailtext:laboremailtext, labormegjtext:labormegjtext},
        success: function(response){
            if (response.error != "") {
                alert(response.error);
                return;
            }
            $("#requestrow"+rid).html(response.html);

            if (confirm("Kiküldöd a leletet erre az email címre? ("+email+")")) {
                $("#ertesitesform"+rid).html("");
                hideGeneralPopup();

                $.ajax({
                    type:"POST",
                    url:"index.php?page=labrequests",
                    data: {sendleletemail:1, id:rid},
                    success: function(response){
                        if (response.error != "") {
                            alert(response.error);
                            return;
                        }
                        $("#ertesitesform"+rid).html(response.html);
                        $.toast({
                            text: "Lelet kiküldve: "+email,
                            icon: 'success'
                        });
                    }
                });
            }
        }
    })
}


function loadLaborEmailTemplate(id) {
    $.ajax({
        type:"POST",
        url:"index.php?page=labrequests",
        data: {getlaboremailtemplate:id},
        success: function(response){
            $("#laboremailtext").val(response);
        }
    })
    return false;
}


