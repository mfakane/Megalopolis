$.extend(megalopolis,
{
	maxHistoryCount: 50,
	minHeight: 0,
	baseUrl: null,
	resizeMinHeight: function(e, orientation)
	{
		var portrait = orientation ? orientation == "portrait" : $("html").hasClass("portrait");
		
		$(document.body).css("minHeight", this.minHeight = portrait ? 460 - 44 : 268);
	},
	resetPage: function(cls, id, skipFirst)
	{
		var selector = "." + cls + "[id!='" + id + "']";
		var c = 0;
		
		$(selector).one("pagehide", function() { if (!skipFirst || c++) $(selector).remove(); });
	},
	getHistory: function()
	{
		var rt = this.cookie("History");
		
		if (rt &&
			(rt = JSON.parse(rt)))
			return rt;
		else
			return [];
	},
	addHistory: function(id, subject, title, name)
	{
		var entries = $.grep(this.getHistory(), function(_) { return _.id != id; });

		entries.push
		({
			id: id,
			subject: subject,
			title: title,
			name: name,
			historySet: $.now()
		});
		
		while (entries.length > this.maxHistoryCount)
			entries.shift();
		
		this.cookie("History", JSON.stringify(entries));
	},
	
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
		if (data.absUrl.indexOf(megalopolis.baseUrl) != 0)
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
