$(function() {
	$("#info-modal").on('show.bs.modal', function (event) {
		var item = $(event.relatedTarget);
		$("#info-modal-title").html("<h2 class='modal-title'>Killer Snails: Developer Test</h2>");
		$("#info-modal-body").html("<h3>" + item.data("title") + "</h3><div id='modal-loader'></div>");
		$("#modal-loader").load(item.data("link"));
	});
});