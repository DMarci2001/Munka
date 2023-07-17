$(document).ready(function () {
    initLaborEditor();
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