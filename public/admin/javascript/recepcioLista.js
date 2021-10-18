$(document).ready(function () {
    initRecepcioLista();
});


function initRecepcioLista() {
    $('#recepcioadd').select2({
        placeholder: "Válassz, vagy írd be a paciens nevét",
        allowClear: true,
        tags: true
    });
}

function recepcioMezoToggle(mezo, id) {
    $.ajax({
        url: 'index.php?page=recepciolista&togglerecepciomezo&mezo='+encodeURIComponent(mezo)+"&id="+encodeURIComponent(id),
        type: 'GET',
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#recepciosor"+id).html(response);
        }
    });
}

function recepcioListaItemDelete(id) {
    if (!confirm("Biztos törlöd ezt a sort?")) {
        return;
    }
    $.ajax({
        url: 'index.php?page=recepciolista&recepcioListaItemDelete=1&id='+encodeURIComponent(id),
        type: 'GET',
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#recepciolista").html(response);
            initRecepcioLista();
        }
    });
}

function recepcioListaMoveDay(offset) {
    $.ajax({
        url: 'index.php?page=recepciolista&moveday='+offset,
        type: 'GET',
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#recepciolista").html(response);
            initRecepcioLista();
        }
    });
}

function addRecepcioListaItem(el) {
    $.ajax({
        url: 'index.php?page=recepciolista&addRecepcioListaItem='+encodeURIComponent(el.value),
        type: 'GET',
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#recepciolista").html(response);
            initRecepcioLista();
        }
    });
}
