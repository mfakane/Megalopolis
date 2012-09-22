megalopolis.read =
{
	scroll: -1
};

$(document).bind("pageinit", function(e)
{
	var page = e.target;
	
	if (page.id == "rhome")
	{
		var content = $(".content", page);
		var pageId = content.prop("class").split(" ").filter(function(_) { return _.indexOf("id") == 0; }).pop();
		var isVertical = content.hasClass("vertical");
		
		if (!content.hasClass("hasinit"))
			content
				.addClass("hasinit")
				.on("scroll", function()
				{
					megalopolis.read.scroll = isVertical ? content.scrollLeft() : content.scrollTop();
				})
				.find(".contentWrapper")
				.fadeTo(0, 0.0001)
				.on("mousewheel", function(e)
				{
					if (isVertical)
					{
						content.stop(true, true).animate
						({
							scrollLeft: content.scrollLeft() + e.originalEvent.wheelDelta,
						}, 20, "linear");
						
						return false;
					}
				});
	}
	else if (page.id == "rpost")
	{
		var rpost = $(page);
		
		rpost.submit(function()
		{
			$.ajax
			({
				type: "POST",
				url: content.attr("action") + ".json",
				dataType: "json",
				data:
				{
					name: content.find("input[name='name']").val(),
					mail: content.find("input[name='mail']").val(),
					body: content.find("textarea").val(),
					password: content.find("input[name='password']").val(),
					postPassword: content.find("input[name='postPassword']").val(),
					point: content.find("select[name='point']").val()
				},
				success: function(data)
				{
					history.back();
				},
				error: function(xhr)
				{
					var data = $.parseJSON(xhr.responseText);
					
					alert(data && data.error && $.isArray(data.error)
						? data.error.join(" ")
						: data.error);
				}
			});
			
			return false;
		});
	}
})
.bind("pageshow", function(e)
{
	var page = e.target;
	
	if (page.id == "rhome")
	{
		var content = $(".content", page);
		var pageId = content.prop("class").split(" ").filter(function(_) { return _.indexOf("id") == 0; }).pop();
		var isVertical = content.hasClass("vertical");
		
		if (!content.hasClass("shown"))
		{
			if (isVertical)
			{
				$(document.body).parent().css("overflow", "hidden");
				content.height(window.innerHeight);
			}
			
			content.addClass("shown");
			
			var saved = megalopolis.cookie("Scroll");
			var savedScroll = saved != null ? saved.split(",") : ["0", "0"];
			var offset = savedScroll[0] == pageId ? savedScroll[1]  - 0 : isVertical ? content.find(".contentWrapper").width() : 0;
			
			(isVertical ? content.scrollLeft(offset) : content.scrollTop(offset))
				.find(".contentWrapper")
				.fadeTo(250, 1);
		}
	}
	else if (page.id == "rpost")
	{
		var rpost = $(page);
		
		rpost.find("label").each(function(k, v) { v = $(v); v.next().css("paddingLeft", v.width() + 16); });
		rpost.find(".ui-select .ui-btn").each(function (k, v) { v = $(v); v.width(v.parent().width()) });
	}
})
.bind("pagehide", function(e)
{
	var page = e.target;
	
	if (page.id == "rhome")
	{
		var content = $(".content", page);
		var pageId = content.prop("class").split(" ").filter(function(_) { return _.indexOf("id") == 0; }).pop();
		
		megalopolis.cookie("Scroll", pageId + "," + megalopolis.read.scroll);
		$(document.body).parent().css("overflow", "auto");
	}
});