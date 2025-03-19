(function($, window){
	var cols, dragSrcEl = null, dragSrcEnter = null, dragableColumns, _this;

	function insertAfter(elem, refElem) {
		return refElem.parentNode.insertBefore(elem, refElem.nextSibling);
	}

	function isIE () {
		var nav = navigator.userAgent.toLowerCase();
		return (nav.indexOf('msie') !== -1) ? parseInt(nav.split('msie')[1]) : false;
	}

	dragableColumns = (function(){
		var $table;
		function dragColumns (table, options) {
			_this = this;
			$table = table;
			_this.options = $.extend({}, _this.options, options);
			if (_this.options.drag) {
				if (isIE() === 9) {
					$table.find('thead tr th').each(function(){
						if ($(this).find('.drag-ie').length === 0) {
							$(this).html($('<a>').html($(this).html()).attr('href', '#').addClass('drag-ie'));
						}
					});
				}
				cols = $table.find('thead tr th');

				jQuery.event.addProp('dataTransfer');
				[].forEach.call(cols, function(col){
					col.setAttribute('draggable', true);
					if($(col).attr("class")=="nonsortable"){
						col.setAttribute('draggable', false);
					}

					if($(col).attr("draggable")=="true"){
						$(col).on('dragstart', _this.handleDragStart);
						$(col).on('dragenter', _this.handleDragEnter);
						$(col).on('dragover', _this.handleDragOver);
						$(col).on('dragleave', _this.handleDragLeave);
						$(col).on('drop', _this.handleDrop);
						$(col).on('dragend', _this.handleDragEnd);
					}
				});
			}
		}

		dragColumns.prototype = {
			options: {
				drag: true,
				dragClass: 'drag',
				overClass: 'over',
				movedContainerSelector: '.dnd-moved'
			},
			handleDragStart: function(e) {
				$(this).addClass(_this.options.dragClass);
				dragSrcEl = this;
				e.dataTransfer.effectAllowed = 'copy';
				e.dataTransfer.setData('text/html', this.id);
			},
			handleDragOver: function (e) {
				if (e.preventDefault) {
					e.preventDefault();
				}
				e.dataTransfer.dropEffect = 'copy';
				return;
			},
			handleDragEnter: function (e) {
				dragSrcEnter = this;
				[].forEach.call(cols, function (col) {
					$(col).removeClass(_this.options.overClass);
				});
				$(this).addClass(_this.options.overClass);
				return;
			},
			handleDragLeave: function (e) {
				if (dragSrcEnter !== e) {
					//this.classList.remove(_this.options.overClass);
				}
			},
			handleDrop: function (e) {
				if (e.stopPropagation) {
					e.stopPropagation();
				}
				if (dragSrcEl !== e) {
					_this.moveColumns($(dragSrcEl).index(), $(this).index());
				}
				return;
			},
			handleDragEnd: function (e) {
				var colPositions = {
						array: [],
						object: {}
					};
				[].forEach.call(cols, function (col) {
					var name = $(col).attr('data-name') || $(col).index();
					$(col).removeClass(_this.options.overClass);
					colPositions.object[name] = $(col).index();
					colPositions.array.push($(col).index());
				});
				if (typeof _this.options.onDragEnd === 'function') {
					_this.options.onDragEnd(colPositions);
				}
				$(dragSrcEl).removeClass(_this.options.dragClass);
				sortPatientDataRows();
				return;
			},
			moveColumns: function (fromIndex, toIndex) {
				var rows = $table.find(_this.options.movedContainerSelector);
				for (var i = 0; i < rows.length; i++) {
					if (toIndex > fromIndex) {
						insertAfter(rows[i].children[fromIndex], rows[i].children[toIndex]);
					} else if (toIndex < $table.find('thead tr th').length - 1) {
						rows[i].insertBefore(rows[i].children[fromIndex], rows[i].children[toIndex]);
					}
				}
			}
		};

		return dragColumns;

	})();

	return $.fn.extend({
		dragableColumns: function(){
			var option = (arguments[0]);
			return this.each(function() {
				var $table = $(this);
				new dragableColumns($table, option);
			});
		}
	});

})(window.jQuery, window);

$(document).ready(function() {
    //Helper function to keep table row from collapsing when being sorted
	var fixHelperModified = function(e, tr) {
		var $originals = tr.children();
		var $helper = tr.clone();

		$helper.children().each(function(index)
		{
		  $(this).width($originals.eq(index).width())
		});
		return $helper;
	};

    //Oszlop mozgatása
    $('#diagnosis_list').dragableColumns({
        drag: true,
        dragClass: 'drag',
        overClass: 'over',
        movedContainerSelector: '.sortable-col'
     });

    //$('#diagnosis_list').dragableColumns();

	//Make diagnosis table sortable
		$("#diagnosis_list tbody").sortable({
			helper: fixHelperModified,
			stop: function(event,ui) {renumber_table('#diagnosis_list');sortPatientDataRows()}
		}).disableSelection();
	

    /*$.ajax({
        url:"index.php?page=patientdata",
        type:"POST",
        data:{sortPatientDataRows:true,},
        success: function(response){
            $.toast({
                text: "Oszlopnév kiválasztva",
                icon: "success"
            });
        }
    })*/

	//Delete button in table rows
	/*$('table').on('click','.btn-delete',function() {
		tableID = '#' + $(this).closest('table').attr('id');
		r = confirm('Delete this item?');
		if(r) {
			$(this).closest('tr').remove();
			renumber_table(tableID);
			}
	});*/

});

//Renumber table rows
function renumber_table(tableID) {
	$(tableID + " tr").each(function() {
		count = $(this).parent().children().index($(this)) + 1;
		$(this).find('.priority').html(count);
	});
}

function sortPatientDataRows(){
    var array = [];
	var columns = [];
    var table = document.querySelector("#diagnosis_list tbody");
    var rows = table.children;
    for (var i = 0; i < rows.length; i++) {
        var fields = rows[i].children;
        var rowArray = [];
        //2-ről indulok, mert ki akarom hagyni a sorszámot és a checkboxot
        for (var j = 3; j < fields.length; j++) {
            rowArray.push(fields[j].innerHTML);
        }
        array.push(rowArray);
    }
	var thead = document.querySelector("#diagnosis_list thead tr");
	var cols = thead.children;
	for (var i = 0; i < cols.length; i++) {
		var col = $(cols[i]).find("select");
		if(col.length>0){
			columns.push(col.val());
		}
		//var titles = cols[i].children;
	}

	//console.table(array);

    $.ajax({
        url:"index.php?page=patientdata",
        type:"POST",
		dataType:'json',
        data:{sortPatientDataRows:true,cols:columns,data:array},
        success: function(response){
            console.log(response);
            $.toast({
                text: "Változtatás rögzítve",
                icon: "success"
            });
        }
    })
}

$(document).on('click', '#diagnosis_list tr .selectable', function() {

	console.log(this);
	if($(this).attr("class")=="custom-option"){
		return;
	}
    checkbox = $(this).closest("tr").find("[type=checkbox]");
    if(checkbox.prop('checked')==true){
        checkbox.prop('checked',false);
    }else{
        checkbox.prop('checked',true);
    }
    
});

function showTablecolDelButtons(){
	var buttons = $("#diagnosis_list").find(".column-delete-button");
	for(i=0;i<buttons.length;i++){
		if($(buttons[i]).css("display")=="none"){
			$(buttons[i]).css("display","inline-block");
		}else{
			$(buttons[i]).css("display","none");
		}
	}
}

function editDataRow(index){
	var trId = "#data-row-"+index;
	$("#diagnosis_list tbody").sortable({disabled:true}).enableSelection();
	$("#diagnosis_list thead th").each(function(){
		/*if($(this).hasClass("nonsortable")){
			return true;
		}*/
		$(this).attr("draggable",false);
	});

	$(trId + " td").each(function() {
		var editorButton = $(this).find("i").closest(".edit-row");
		var removeButton = $(this).find("i").closest(".remove-row");

		if($(this).hasClass("selectable")){
			$(this).attr("class","editable");
			var value = $(this).html();
			$(this).html("<input class='form-control form-control-sm custom-option' data-og-value='"+value+"' type='text' value='"+value+"'>");
		}

		if(editorButton.length>0){
			editorButton.removeClass("fa-pen");
			editorButton.addClass("fa-floppy-disk").attr({title:"Mentés",onClick:"saveEditDataRow("+index+")"});
		}
		if(removeButton.length>0){
			removeButton.removeClass("fa-trash-can");
			removeButton.addClass("fa-circle-xmark").attr({title:"Bezárás",onClick:"cancelEditDataRow("+index+")"});
		}
	})
}

function saveEditDataRow(index){
	var trId = "#data-row-"+index;

	$(trId + " td").each(function(i,val) {
		var editorButton = $(this).find("i").closest(".edit-row");
		var removeButton = $(this).find("i").closest(".remove-row");
		if(editorButton.length>0){
			editorButton.addClass("fa-pen").attr("title","Mentés");
			editorButton.removeClass("fa-floppy-disk").attr("title","Szerkesztés");
		}
		if(removeButton.length>0){
			removeButton.addClass("fa-trash-can").attr({title:"Törés",onClick:"deleteDataRow("+index+")"});
			removeButton.removeClass("fa-circle-xmark");
		}
		if($(this).hasClass("editable")){
			$(this).attr("class","selectable");
			var value = $(this).find("input").val();
			$(this).html(value);
		}
	})
	$("#diagnosis_list tbody").sortable({disabled:false}).disableSelection();
	$("#diagnosis_list thead th").each(function(){
		if($(this).hasClass("nonsortable")){
			return true;
		}
		$(this).attr("draggable",true);
	});
	sortPatientDataRows();
}

function deleteDataRow(index){
	var trId = "#data-row-"+index;
	$(trId).remove();
	sortPatientDataRows();
}

function cancelEditDataRow(index){
	var trId = "#data-row-"+index;

	$(trId + " td").each(function(i,val) {
		var editorButton = $(this).find("i").closest(".edit-row");
		var removeButton = $(this).find("i").closest(".remove-row");
		if(editorButton.length>0){
			editorButton.addClass("fa-pen").attr("title","Mentés");
			editorButton.removeClass("fa-floppy-disk").attr("title","Szerkesztés");
		}
		if(removeButton.length>0){
			removeButton.addClass("fa-trash-can").attr({title:"Törés",onClick:"deleteDataRow("+index+")"});
			removeButton.removeClass("fa-circle-xmark");
		}
		if($(this).hasClass("editable")){
			$(this).attr("class","selectable");
			var value = $(this).find("input").attr("data-og-value");
			$(this).html(value);
		}
	})
	$("#diagnosis_list tbody").sortable({disabled:false}).disableSelection();
	$("#diagnosis_list thead th").each(function(){
		if($(this).hasClass("nonsortable")){
			return true;
		}
		$(this).attr("draggable",true);
	});
}

function removeCol(index){
	$("#diagnosis_list thead").find("th:eq("+index+")").remove();
	$("#diagnosis_list tbody tr").each(function(){
		$(this).find("td:eq("+(index)+")").remove();
	})
	$("#diagnosis_list thead th").each(function(i,val){
		$(this).find("button").attr("onClick","removeCol("+i+")");
	})
	sortPatientDataRows();
}

function createDMRecipientList(){

	var queryString = window.location.search;
	var urlParams = new URLSearchParams(queryString);

	Swal.fire({
		title: "Biztosan el akarod menteni a DM címzett listát?",
		showCancelButton: true,
		icon: "question",
		confirmButtonText: "Mentés",
		cancelButtonText: "Bezárás",
		//denyButtonText: ``
	  }).then((result) => {
		/* Read more about isConfirmed, isDenied below */
		if (result.isConfirmed) {
		  //Swal.fire("Sikeres mentés!", "", "success");
		  Swal.fire({
			title: "Sikeres mentés!",
			text: "Ha vissza szeretnél lépni a DM listához kattints a vissza gombra",
			icon: "success",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			confirmButtonText: "Vissza",
			cancelButtonColor: "#d33",
			cancelButtonText: "Bezárás"
		  }).then((result) => {
			if (result.isConfirmed) {
				window.location.replace("?page=direktmarketing&szerk="+urlParams.get('dmid'));
			}
		  });
		  $.ajax({
			url:"index.php?page=patientdata",
			type:"POST",
			//dataType:'json',
			data:{createDMRecipientList:true},
			success: function(response){
				console.log(response);
			}
		})
		} else if (result.isDenied) {
		  Swal.fire("Changes are not saved", "", "info");
		}
	  });
}

function createReferalList(){

	var queryString = window.location.search;
	var urlParams = new URLSearchParams(queryString);

	Swal.fire({
		title: "Biztosan el akarod menteni a beutaló listát?",
		showCancelButton: true,
		icon: "question",
		confirmButtonText: "Mentés",
		cancelButtonText: "Bezárás",
		//denyButtonText: ``
	  }).then((result) => {
		/* Read more about isConfirmed, isDenied below */
		if (result.isConfirmed) {
		  //Swal.fire("Sikeres mentés!", "", "success");
		  Swal.fire({
			title: "Sikeres mentés!",
			text: "Ha vissza szeretnél lépni a beutalók kezeléséhez, kattints a vissza gombra",
			icon: "success",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			confirmButtonText: "Vissza",
			cancelButtonColor: "#d33",
			cancelButtonText: "Bezárás"
		  }).then((result) => {
			if (result.isConfirmed) {
				window.location.replace("?page=beutalokkezelese");
			}
		  });
		  $.ajax({
			url:"index.php?page=patientdata",
			type:"POST",
			//dataType:'json',
			data:{createReferalList:true,bmid:urlParams.get('bmid')},
			success: function(response){
				console.log(response);
			}
		})
		} else if (result.isDenied) {
		  Swal.fire("Changes are not saved", "", "info");
		}
	  });
}




