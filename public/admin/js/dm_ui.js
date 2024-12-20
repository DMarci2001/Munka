function insertNewDMList(){
    (async () => {
        const { value: insert } = await Swal.fire({
            title: "Új DM lista létrehozása",
            input: "text",
            inputLabel: "Lista megnevezése",
            confirmButtonColor: "#0a0",
            confirmButtonText: "Létrehozás",
            showCancelButton: true,
            cancelButtonText: "Mégse",
            inputValidator: (value) => {
                if (!value) {
                  return "Adj meg egy listanevet!";
                }

            }
          });
          if (insert) {
            $.ajax({
                url:"index.php?page=direktmarketing",
                type:"POST",
                async: true,
                dataType:'json',
                data:{insertNewDMList:true,record:insert},
                success: function(response){
                    if(response.error){
                        $.toast({
                            text: response.error,
                            icon: "error"
                        });
                    }
                    if(response.success){
                        $("#dm-list-container").html(response.html);
                        $.toast({
                            text: "Sikeres rögzítés!",
                            icon: "success"
                        });
                    }
                }
            })
          }
        }
    )()
}

$(document).on('click', '#dm-list tr', function() {
    window.location.replace("?page=direktmarketing&szerk="+this.dataset.dmId);
});

$(document).on("click",".subscribe-switch", function(){
    var info = $(this).closest("tr").data();
    var date = $(this).closest("tr").find(".unsubscribed-date");
    $.ajax({
        url:"index.php?page=direktmarketing",
        type:"POST",
        dataType:"json",
        data:{setRecipientSubscribe:true,recipient:info.dmRecipientId},
        success: function(response){
            console.log(response);
            if(response.error){
                $.toast({
                    text: response.error,
                    icon: "error"
                });
            }else{
                date.html(response.date);
                $.toast({
                    text: "Sikeres rögzítés!",
                    icon: "success"
                });
            }
        }
    })
});

$(document).on("click",".unsub-dm, .resub-dm", function(){
    var info = $(this).closest("tr").data();
    var date = $(this).closest("tr").find(".unsubscribed-date");
    var button = $(this);
    var status = "";

    if(button.hasClass("unsub-dm")){
        status = "unsub";
    }

    if(button.hasClass("resub-dm")){
        status = "resub";
    }

    $.ajax({
        url:"index.php?page=direktmarketing",
        type:"POST",
        dataType:"json",
        data:{setRecipientSubscribe:true,recipient:info.dmRecipientId},
        success: function(response){
            if(response.error){
                $.toast({
                    text: response.error,
                    icon: "error"
                });
            }else{
                date.html(response.date);
                if(status=="unsub"){
                    button.removeClass("unsub-db").removeClass("btn-danger").addClass("resub-dm").addClass("btn-success");
                    button.find("i").removeClass("fa-bell-slash").addClass("fa-bell");
                }
                if(status=="resub"){
                    button.removeClass("resub-db").removeClass("btn-success").addClass("unsub-dm").addClass("btn-danger");
                    button.find("i").removeClass("fa-bell").addClass("fa-bell-slash");
                }
                $.toast({
                    text: "Sikeres rögzítés!",
                    icon: "success"
                });
            }
        }
    })

});