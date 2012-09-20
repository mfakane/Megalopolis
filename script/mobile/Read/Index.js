megalopolis.read =
{
	scroll: -1,
	renderPage: function()
	{
		$(document)
			.bind("pageinit", function(e)
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
	},
	adjustTextBox: function(href)
	{
		var rpost = $(".read[id='rpost']:last");
		
		$("label", rpost).each(function(k, v) { v = $(v); v.next().css("paddingLeft", v.width() + 16); });
		$(".ui-select .ui-btn", rpost).each(function (k, v) { v = $(v); v.width(v.parent().width()) });
		
		rpost.submit(function()
		{
			$.ajax
			({
				type: "POST",
				url: rpost.attr("action") + ".json",
				dataType: "json",
				data:
				{
					name: $("input[name='name']", rpost).val(),
					mail: $("input[name='mail']", rpost).val(),
					body: $("textarea", rpost).val(),
					password: $("input[name='password']", rpost).val(),
					postPassword: $("input[name='postPassword']", rpost).val(),
					point: $("select[name='point']", rpost).val()
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
};