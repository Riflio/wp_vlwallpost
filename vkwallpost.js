jQuery(document).ready(function($) {


$('#startexport').bind('click', function() {
	exportAction({'step':0, 'total': vkmeta.total});
});

//-- отмечаем или снимаем отметку со всех чекбоксов разом.
$('#cb_exportall').bind('click', function() {
	$('.exportitem').prop('checked', $(this).prop('checked'));
});


function exportAction(exp) {

	$.ajax({
			url: ajaxurl,
			data: ({action : 'exportaction', export: encodeURIComponent(JSON.stringify(exp))}),
			success: function(data) {
				
				try {
  					var data=$.parseJSON(data); 
				}
				catch (err) {
					$("body").append(data);
					//TODO: При ошибке отключать кнопку экспорт или задавать новые параметры... а лучше нахуй обновить страницу
					return;
				}			
			
				
				
				$("#vkwp").find('.percent').html(Math.round(data.step/data.total*100)+'%'+' '+data.step+' of '+data.total);
				$("#vkwp").find('.bar').css("width", Math.round(data.step/data.total*100)+'%');
				
				if (data.step>=data.total) { 
					return;
				}
				
				
				
				setTimeout(exportAction, 6000, data);

			}
	});		

}


});