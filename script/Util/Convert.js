$(function()
{
	var form = $("#form");
	
	if (form.length)
	{
		var action = form.attr("action") + ".json";
		var list = [];
		var first = 0;
		var minimumBuffer = 10;
		var process = function(bar, obj)
		{
			$.ajax
			({
				type: "GET",
				url: action,
				dataType: "json",
				data:
				{
					p: obj.remaining.join(","),
					c: obj.count,
					b: obj.buffer,
					allowOverwrite: obj.allowOverwrite,
					whenNoConvertLineBreakFieldOnly: obj.whenNoConvertLineBreakFieldOnly,
					whenContainsWin31JOnly: obj.whenContainsWin31JOnly
				},
				success: function(data)
				{
					if (data.remaining)
					{
						var remainingBase = first == 0 ? (first = data.first) : data.first;
						var remainingFirst = data.remaining[0].split("-");
						var remainingLast = list[list.length - 2].split("-");
						var value = (remainingFirst[1] - remainingBase) / (remainingLast[2] - remainingBase) * 100;
						
						if (value < 99)
						{
							var container = bar.parent();
							var w = bar.width() / container.innerWidth() * 100;
							
							if (value > 0 && container.hasClass("pending"))
								container.removeClass("pending");
							
							if (value > w)
							{
								bar.stop(true, true);
								bar.animate({ width: value + "%" }, 1000);
							}
						}
						
						process(bar, data);
					}
					else
					{
						bar.animate({ width: "100%" }, 500, function()
						{
							bar.parent().removeClass("pending").addClass("complete");
						});
						$("#convert>div>p").remove();
						$("#convert>div")
							.append($("<p />")
								.text(data.count + " 作品の変換処理が完了しました。")
								.append($("<br />"))
								.append($("<br />"))
								.append($("<a />")
									.attr("href", "../")
									.text("ホームへ戻る")));
					}
				},
				error: function(xhr)
				{
					var data = xhr.responseText;
					
					if (data.indexOf("Maximum execution time") != -1 && data.indexOf("exceeded") != -1)
						if (obj.buffer / 2 > minimumBuffer)
						{
							obj.buffer /= 2;
							process(bar, obj);
							
							return;
						}
					
					$("#convert>div>*").remove();
					$("#convert>div")
						.append($("<p />")
							.text("不明なエラーが発生しました。")
							.append($("<br />"))
							.append($("<br />"))
							.append($("<a />")
								.attr("href", "convert")
								.text("戻る")))
						.append($("<p />")
							.html(data));
				}
			});
		};
		
		form.submit(function()
		{
			var optionElements = (function(obj) { return $.each(obj, function(k) { obj[k] = $("#" + k); }); })
			({
				allowOverwrite: null,
				whenNoConvertLineBreakFieldOnly: null,
				whenContainsWin31JOnly: null
			});
			var options = (function(obj) { $.each(optionElements, function(k, v) { obj[k] = v.prop("checked"); }); return obj; })({});
			var selectedOptions = $.map(optionElements, function(v) { return v.prop("checked") ? v.parent().text() : null; });
			
			form.remove();
			
			var div = $("<div />")
				.append($("<p />").html("現在変換処理中です。ページを移動しないでください..."))
				.append($("<p />").html("オプション: " + (selectedOptions.length ? selectedOptions.join("<br />") : "なし")))
				.appendTo($("#convert"));
			var bar = $("<div />")
				.width("0%")
				.appendTo($("<div />")
					.addClass("progress pending")
					.prependTo(div));
			
			$.ajax
			({
				type: "GET",
				url: action,
				dataType: "json",
				data:
				{
					p: "list",
					allowOverwrite: options.allowOverwrite ? "yes" : "no",
					whenNoConvertLineBreakFieldOnly: options.whenNoConvertLineBreakFieldOnly ? "yes" : "no",
					whenContainsWin31JOnly: options.whenContainsWin31JOnly ? "yes" : "no"
				},
				success: function(data)
				{
					list = data.remaining;
					process(bar, data);
				},
				error: function(xhr)
				{
					var data = xhr.responseText;
					
					$("#convert>div>*").remove();
					$("#convert>div")
						.append($("<p />")
							.text("不明なエラーが発生しました。")
							.append($("<br />"))
							.append($("<br />"))
							.append($("<a />")
								.attr("href", "convert")
								.text("戻る")))
						.append($("<p />")
							.html(data));
				}
			});
			
			return false;
		});
	}
});
