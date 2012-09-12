megalopolis.read =
{
	renderPage: function()
	{
		$(document)
			.bind("pageinit", function(e)
			{
				var page = e.target;
				
				if (page.id == "rhome")
				{
					var pageId = $(page).prop("data-id");
					var content = $(".content", page);
					
					if (!content.hasClass("hasinit"))
						content
							.addClass("hasinit")
							.css("visibility", "hidden")
							.find(".contentWrapper")
							.on("mousewheel", function(e)
							{
								content.stop(true, true).animate
								({
									scrollLeft: content.scrollLeft() + e.originalEvent.wheelDelta,
								}, 20, "linear");
								
								return false;
							});
				}
			})
			.bind("pageshow", function(e)
			{
				var page = e.target;
				
				if (page.id == "rhome")
				{
					var pageId = $(page).prop("data-id");
					var content = $(".content", this);
					
					$(document.body).css("overflowY", "hidden");
					
					content.css("visibility", "");
				}
			})
			.bind("pagehide", function(e)
			{
				if (e.target.id == "rhome")
					$(document.body).css("overflowY", "");
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