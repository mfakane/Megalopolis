$.extend(megalopolis,
{
	maxHistoryCount: 50,
	minHeight: 0,
	resizeMinHeight: function(e, orientation)
	{
		var portrait = orientation ? orientation == "portrait" : $("html").hasClass("portrait");
		
		$(document.body).css("minHeight", (this.minHeight = portrait ? window.innerHeight - 48 : 268) + "px");
		$(".read .content.vertical").height(window.innerHeight);
	}
});

$(document)
	.bind("mobileinit", function()
	{	
		$.extend($.mobile, 
		{
			pageLoadErrorMessage: "ページの読み込みに失敗しました",
			defaultPageTransition: "slide",
		});
	})
	.bind("pagebeforeload", function(e, data)
	{
		if (data.absUrl.indexOf($("link[rel='home']").prop("href")) != 0)
		{
			e.preventDefault();
			data.deferred.reject(data.absUrl, data.options);
		}
	})
	.bind("pageinit", function()
	{
		megalopolis.resizeMinHeight();
	})
	.bind("orientationchange", function()
	{
		megalopolis.resizeMinHeight();
		
		setTimeout(function()
		{
			if (document.body.scrollTop == 0)
				document.body.scrollTop = 1;
		}, 1);
	});
