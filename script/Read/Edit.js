$(function()
{
	var section = $(".edit section+section");
	var textAreas = $("textarea, input[type='text']", section).width(section.width() - 6);
	var setBodyBoxStyle = function(boxName, cssProperty, value, defaultValue)
	{
		var t = $("#" + boxName);
		
		if (typeof value == "string")
			t.val(value);
		else
			value = t.val();
		
		$("#body").css(cssProperty, value == "" ? defaultValue : cssProperty == "backgroundImage" ? "url('" + value + "')" : value);
	};
	var createPalette = function(selector, func, cssProperty)
	{
		var target = $(selector);
		
		if (target.length == 0)
			return;
		
		var map = (target.data("map") || "").split(" ");
		
		target.change(function() { func(); });
		
		if (map.length && map[0].length)
		{
			var list = $('<ul class="palette"></ul>');
		
			list.append($("<li />")
					.append($('<a href="#" />')
						.click(function()
						{
							func("");
							
							return false;
						})))
				.append($.map(map, function(_)
				{
					return $("<li />")
						.append($('<a href="#" />')
							.css(cssProperty, cssProperty == "backgroundImage" ? "url('" + _ + "')" : _)
							.click(function()
							{
								func(_);
								
								return false;
							}));
				}))
				.insertAfter(target);
		}
	};
	
	createPalette("#foreground", function(value) { setBodyBoxStyle("foreground", "color", value, "#000000"); }, "backgroundColor");
	createPalette("#background", function(value) { setBodyBoxStyle("background", "backgroundColor", value, "#ffffff"); }, "backgroundColor");
	createPalette("#backgroundImage", function(value) { setBodyBoxStyle("backgroundImage", "backgroundImage", value, "none"); }, "backgroundImage");
	createPalette("#border", function(value) { setBodyBoxStyle("border", "borderColor", value, "#999999"); }, "backgroundColor");
	
	$(window).resize(function()
	{
		textAreas.width(section.width() - 6);
	});
	$("#unpostCheck").change(function()
	{
		$("#unpostSubmit").attr("disabled", !$(this).is(":checked"));
	});
	$("#unpostSubmit").attr("disabled", true);
	$("#unpostForm").submit(function()
	{
		return window.confirm("本当に作品を削除してよろしいですか？");
	});
});
