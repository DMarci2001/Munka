$(document).ready(function(){
	$('.option-box').on('submit',(function(e) {
		$(this).slideToggle();
	}));
});

function load_uj_lelet(){
	$('#leletform').load('index.php?open_new_lelet');
	//tinymce.get('uj-lelet-page').setContent(''); 
	//var iframe = document.getElementById(FrameId);
	//iframe.src = iframe.src;
}

function open_lelet(id){
	$('#uj-lelet').remove();
	$('#leletform').load('index.php?open_lelet=' + id);
	
	setTimeout(function() {
		$('#leletform').slideToggle();$('#add-lelet').css('display','none');
	}, 1200);
	
}
function add_lelet(id,textarea){
	
		var data = 'request_lelet=' + id;
		var footage = $('.medic-footage').text().replace(/\"/g, '');;
		var seal_number = $('#pecsetszam').val();
		var fracted_footage = footage.split('(');
		footage = fracted_footage[0] + '(' + seal_number + fracted_footage[1];
		
		request = $.ajax({
		url: 'index.php',
		type: 'post',
		data: data
	});
	request.done(function (res, textStatus, jqXHR){
		$('.currently-text-container').html(res);
		//$('#lelet-page').append('valami');
		var iframe = textarea+'_ifr';
		$('#' + iframe).contents().find('#tinymce').append(res);
		$('#' + iframe).contents().find('#tinymce').append(footage);
		//console.log(res);
		});
}
	
function send_iFrame(patient,medic,textarea){

	//var footage = $('.medic-footage').text();
	//$('#lelet-page_ifr').contents().find('#tinymce').append(footage);
	
	var mceContent = $('#' + textarea + '_ifr').contents().find('#tinymce').prop('outerHTML');
	//console.log(mceContent);
	
	if(textarea != 'uj-lelet-page'){
		idDumb = textarea.split('-');
		var data  = 'update_lelet=' + encodeURIComponent(mceContent); 
			data += '&lid=' + idDumb[2];
	}
	else{
		var data  = 'save_lelet=' + encodeURIComponent(mceContent);
			data += '&seal_numb=' + $('#pecsetszam').val();
	}
	/*var ed = tinyMCE.get(textarea).getContent();
	//console.log(ed);
	
	
	//var ed = tinyMCE.activeEditor.getContent();


		//ed.init();
		//ed.render();
		// Do you ajax call here, window.setTimeout fakes ajax call
		ed.setProgressState(1); // Show progress
		window.setTimeout(function() {
			ed.setProgressState(0); // Hide progress
			//alert(ed.getContent());
		}, 3000);*/
		//ed.getContent()
	
	
		
		//console.log($('#lelet-page').val());
	
	request = $.ajax({
	url: 'index.php',
	type: 'post',
	data: data
	});
	request.done(function (res, textStatus,jqXHR){
		console.log(res);
		$('.successful-message').css('display','block');
		$('.successful-message').find('span').text('Lelet elmentve!');
		setTimeout(function() {
			$('.successful-message').css({opacity: 1.0, visibility: 'visible'}).animate({opacity: 0}, 1000, function(){
				$('.successful-message').css('display','none');
			});
		}, 1000);
	});
	
	$('#' + textarea + '_ifr').get(0).contentWindow.focus();
	$('#' + textarea + '_ifr').get(0).contentWindow.print();
	//console.log($('#lelet-page').val());
}
function save_iFrame(patient,medic,textarea){
	
	var mceContent = $('#' + textarea + '_ifr').contents().find('#tinymce').prop('outerHTML');
	
	if(textarea != 'uj-lelet-page'){
		idDumb = textarea.split('-');
		var data  = 'update_lelet=' + encodeURIComponent(mceContent); 
			data += '&lid=' + idDumb[2];
	}
	else{
		var data  = 'save_lelet=' + encodeURIComponent(mceContent);
			data += '&seal_numb=' + $('#pecsetszam').val();
	}
	
	request = $.ajax({
	url: 'index.php',
	type: 'post',
	data: data
	});
	request.done(function (res, textStatus,jqXHR){
		console.log(res);
		$('#lelet-lista').load('index.php?reload_leletlista');
		$('.successful-message').css('display','block');
		$('.successful-message').find('span').text('Lelet elmentve!');
		setTimeout(function() {
			$('.successful-message').css({opacity: 1.0, visibility: 'visible'}).animate({opacity: 0}, 1000, function(){
				$('.successful-message').css('display','none');
			});
		}, 1000);
	});
}

