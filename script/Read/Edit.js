megalopolis.edit =
{
	paletteID: 0,
	setBodyBoxStyle: function(boxName, cssProperty, value, defaultValue)
	{
		var t = $("#" + boxName);
		
		if (typeof value == "string")
			t.val(value);
		else
			value = t.val();
		
		$("#body").css(cssProperty, value == "" ? defaultValue : cssProperty == "backgroundImage" ? "url('" + value + "')" : value);
	},
	updateForeground: function(value)
	{
		megalopolis.edit.setBodyBoxStyle("foreground", "color", value, "#000000");
	},
	updateBackground: function(value)
	{
		megalopolis.edit.setBodyBoxStyle("background", "backgroundColor", value, "#ffffff");
	},
	updateBackgroundImage: function(value)
	{
		megalopolis.edit.setBodyBoxStyle("backgroundImage", "backgroundImage", value, "none");
	},
	updateBorder: function(value)
	{
		megalopolis.edit.setBodyBoxStyle("border", "borderColor", value, "#999999");
	},
	palette: function(values, func, cssProperty)
	{
		var id = "a" + this.paletteID++;
		
		document.write($("<div />")
			.append($("<ul />")
				.addClass("palette")
				.attr("id", id))
			.html());
		
		$("#" + id).append($("<li />")
				.append($("<a />")
					.attr("href", "javascript:void(0);")
					.click(function()
					{
						func("");
						
						return false;
					})))
			.append($.map(values, function(_)
			{
				return $("<li />")
					.append($("<a />")
						.attr("href", "javascript:void(0);")
						.css(cssProperty, _)
						.click(function()
						{
							func(_);
							
							return false;
						}))[0];
			}));
	},
	foregroundPalette: function(values)
	{
		this.palette(values, this.updateForeground, "backgroundColor");
	},
	backgroundPalette: function(values)
	{
		this.palette(values, this.updateBackground, "backgroundColor");
	},
	backgroundImagePalette: function(values)
	{
		this.palette(values, this.updateBackgroundImage, "backgroundImage");
	},
	borderPalette: function(values)
	{
		this.palette(values, this.updateBorder, "backgroundColor");
	}
};

$(function()
{
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
