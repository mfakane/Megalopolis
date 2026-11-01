$(function()
{
	var section = $(".edit section+section");
	var textAreas = $("textarea, input[type='text']", section);
	var resize = function()
	{
		// 幅の狭い画面では CSS で幅を決める
		if (window.matchMedia && window.matchMedia("(max-width: 600px)").matches)
			textAreas.css("width", "");
		else
			textAreas.width(section.width() - 6);
	};

	resize();
	var setBodyBoxStyle = function(boxName, cssProperty, value, defaultValue)
	{
		var t = $("#" + boxName);
		
		if (typeof value == "string")
			t.val(value);
		else
			value = t.val();
		
		$("#body").css(cssProperty, value == "" ? defaultValue : cssProperty == "backgroundImage" ? "url('" + value + "')" : value);
		// ダークモードでも作者の指定した配色で見えるよう、色を指定したときは明るい配色にする (style.css)
		$("#body").toggleClass("authorColors", $.grep(["#foreground", "#background", "#backgroundImage"], function(i) { return $(i).val() != ""; }).length > 0);
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
	
	createPalette("#foreground", function(value) { setBodyBoxStyle("foreground", "color", value, ""); }, "backgroundColor");
	createPalette("#background", function(value) { setBodyBoxStyle("background", "backgroundColor", value, ""); }, "backgroundColor");
	createPalette("#backgroundImage", function(value) { setBodyBoxStyle("backgroundImage", "backgroundImage", value, ""); }, "backgroundImage");
	createPalette("#border", function(value) { setBodyBoxStyle("border", "borderColor", value, ""); }, "backgroundColor");
	
	$(window).resize(resize);
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
